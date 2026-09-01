<?php

namespace App\Integrations\Jira;

use App\Contracts\Integrations\JiraClient;
use App\Data\Integrations\Jira\CreateJiraIssueData;
use App\Data\Integrations\Jira\JiraIssueData;
use Illuminate\Http\Client\Factory as HttpFactory;
use App\Exceptions\Integrations\IntegrationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

final class AtlassianJiraClient implements JiraClient
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    public function createIssue(CreateJiraIssueData $data): JiraIssueData
    {
        $this->ensureConfigured('create_issue');

        try {
            $response = $this->http
                ->withBasicAuth(
                    (string) config('integrations.jira.email'),
                    (string) config('integrations.jira.api_token'),
                )
                ->acceptJson()
                ->asJson()
                ->connectTimeout(5)
                ->timeout(15)
                ->post(
                    rtrim((string) config('integrations.jira.base_url'), '/') . '/rest/api/3/issue',
                    [
                        'fields' => [
                            'project' => [
                                'key' => $data->projectKey,
                            ],
                            'issuetype' => [
                                'id' => $data->issueTypeId,
                            ],
                            'summary' => $data->summary,
                            'description' => [
                                'type' => 'doc',
                                'version' => 1,
                                'content' => [
                                    [
                                        'type' => 'paragraph',
                                        'content' => [
                                            [
                                                'type' => 'text',
                                                'text' => $data->description,
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                )
                ->throw();
        } catch (ConnectionException $exception) {
            throw new IntegrationException(
                message: 'Unable to connect to Jira.',
                provider: 'jira',
                operation: 'create_issue',
                retryable: true,
                previous: $exception,
            );
        } catch (RequestException $exception) {
            $status = $exception->response->status();

            throw new IntegrationException(
                message: "Jira create issue request failed with HTTP {$status}.",
                provider: 'jira',
                operation: 'create_issue',
                retryable: $status === 429 || $status >= 500,
                previous: $exception,
            );
        }

        return new JiraIssueData(
            externalId: (string) $response->json('id'),
            issueKey: (string) $response->json('key'),
            url: rtrim((string) config('integrations.jira.base_url'), '/')
                . '/browse/'
                . $response->json('key'),
        );
    }

    public function getIssue(string $externalId): JiraIssueData
    {
        $this->ensureConfigured('get_issue');

        $response = $this->http
            ->withBasicAuth(
                (string) config('integrations.jira.email'),
                (string) config('integrations.jira.api_token'),
            )
            ->acceptJson()
            ->get(
                rtrim((string) config('integrations.jira.base_url'), '/')
                    . '/rest/api/3/issue/'
                    . $externalId,
            )
            ->throw();

        return new JiraIssueData(
            externalId: (string) $response->json('id'),
            issueKey: (string) $response->json('key'),
            url: rtrim((string) config('integrations.jira.base_url'), '/')
                . '/browse/'
                . $response->json('key'),
            status: $response->json('fields.status.name'),
            metadata: $response->json(),
        );
    }

    private function ensureConfigured(string $operation): void
    {
        $required = [
            'base_url',
            'email',
            'api_token',
        ];

        foreach ($required as $key) {
            if (blank(config("integrations.jira.{$key}"))) {
                throw new IntegrationException(
                    message: "Jira integration configuration is incomplete: {$key} is missing.",
                    provider: 'jira',
                    operation: $operation,
                    retryable: false,
                );
            }
        }
    }
}
