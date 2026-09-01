<?php

namespace App\Integrations\GitHub;

use App\Contracts\Integrations\GitHubClient;
use App\Data\Integrations\GitHub\CreateGitHubIssueData;
use App\Data\Integrations\GitHub\GitHubResourceData;
use App\Enums\Integrations\GitHubResourceType;
use App\Exceptions\Integrations\IntegrationException;
use DateTimeImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;

final class GitHubApiClient implements GitHubClient
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    public function createIssue(
        CreateGitHubIssueData $data
    ): GitHubResourceData {
        $this->ensureConfigured('create_issue');

        try {
            $response = $this->http
                ->withToken(
                    (string) config('integrations.github.token'),
                )
                ->withHeaders([
                    'X-GitHub-Api-Version' => '2026-03-10',
                ])
                ->acceptJson()
                ->asJson()
                ->connectTimeout(5)
                ->timeout(15)
                ->post(
                    'https://api.github.com/repos/'
                        .$data->repository
                        .'/issues',
                    [
                        'title' => $data->title,
                        'body' => $data->body,
                    ],
                )
                ->throw();
        } catch (ConnectionException $exception) {
            throw new IntegrationException(
                message: 'Unable to connect to GitHub.',
                provider: 'github',
                operation: 'create_issue',
                retryable: true,
                previous: $exception,
            );
        } catch (RequestException $exception) {
            $status = $exception->response->status();

            throw new IntegrationException(
                message: "GitHub create issue request failed with HTTP {$status}.",
                provider: 'github',
                operation: 'create_issue',
                retryable: $status === 429 || $status >= 500,
                previous: $exception,
            );
        }

        return new GitHubResourceData(
            type: GitHubResourceType::Issue,
            externalId: (string) $response->json('id'),
            repository: $data->repository,
            resourceNumber: (int) $response->json('number'),
            reference: null,
            url: (string) $response->json('html_url'),
            title: $response->json('title'),
            state: $response->json('state'),
            updatedAt: $response->json('updated_at')
                ? new DateTimeImmutable(
                    (string) $response->json('updated_at')
                )
                : null,
            metadata: $response->json(),
        );
    }

    private function ensureConfigured(string $operation): void
    {
        $required = [
            'token',
            'repository',
        ];

        foreach ($required as $key) {
            if (blank(config("integrations.github.{$key}"))) {
                throw new IntegrationException(
                    message: "GitHub integration configuration is incomplete: {$key} is missing.",
                    provider: 'github',
                    operation: $operation,
                    retryable: false,
                );
            }
        }
    }
}
