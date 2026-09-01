<?php

namespace Tests\Feature\Integrations\Jira;

use App\Data\Integrations\Jira\CreateJiraIssueData;
use App\Integrations\Jira\AtlassianJiraClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use App\Exceptions\Integrations\IntegrationException;

class AtlassianJiraClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'integrations.jira.base_url' => 'https://example.atlassian.net',
            'integrations.jira.email' => 'agent@example.com',
            'integrations.jira.api_token' => 'test-api-token',
        ]);

        Http::preventStrayRequests();
    }

    public function test_it_creates_jira_issue(): void
    {
        Http::fake([
            'https://example.atlassian.net/rest/api/3/issue' => Http::response([
                'id' => '10001',
                'key' => 'SD-123',
                'self' => 'https://example.atlassian.net/rest/api/3/issue/10001',
            ], 201),
        ]);

        $client = app(AtlassianJiraClient::class);

        $result = $client->createIssue(
            new CreateJiraIssueData(
                projectKey: 'SD',
                issueTypeId: '10000',
                summary: 'Printer is not working',
                description: 'The office printer stopped working.',
            ),
        );

        $this->assertSame('10001', $result->externalId);
        $this->assertSame('SD-123', $result->issueKey);
        $this->assertSame(
            'https://example.atlassian.net/browse/SD-123',
            $result->url,
        );

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->url() === 'https://example.atlassian.net/rest/api/3/issue'
                && $request->hasHeader(
                    'Authorization',
                    'Basic ' . base64_encode('agent@example.com:test-api-token'),
                )
                && $request['fields']['project']['key'] === 'SD'
                && $request['fields']['issuetype']['id'] === '10000'
                && $request['fields']['summary'] === 'Printer is not working'
                && $request['fields']['description'] === [
                    'type' => 'doc',
                    'version' => 1,
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => 'The office printer stopped working.',
                                ],
                            ],
                        ],
                    ],
                ];
        });
    }

    public function test_it_gets_jira_issue(): void
    {
        Http::fake([
            'https://example.atlassian.net/rest/api/3/issue/10001' => Http::response([
                'id' => '10001',
                'key' => 'SD-123',
                'fields' => [
                    'status' => [
                        'name' => 'In Progress',
                    ],
                ],
            ]),
        ]);

        $client = app(AtlassianJiraClient::class);

        $result = $client->getIssue('10001');

        $this->assertSame('10001', $result->externalId);
        $this->assertSame('SD-123', $result->issueKey);
        $this->assertSame(
            'https://example.atlassian.net/browse/SD-123',
            $result->url,
        );
        $this->assertSame('In Progress', $result->status);

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'GET'
                && $request->url() === 'https://example.atlassian.net/rest/api/3/issue/10001'
                && $request->hasHeader(
                    'Authorization',
                    'Basic ' . base64_encode('agent@example.com:test-api-token'),
                );
        });
    }

    public function test_create_issue_marks_server_error_as_retryable(): void
    {
        Http::fake([
            'https://example.atlassian.net/rest/api/3/issue' =>
            Http::response([], 503),
        ]);

        try {
            app(AtlassianJiraClient::class)->createIssue(
                new CreateJiraIssueData(
                    projectKey: 'SD',
                    issueTypeId: '10000',
                    summary: 'Test',
                    description: 'Test description',
                ),
            );

            $this->fail('Expected Jira request to fail.');
        } catch (\App\Exceptions\Integrations\IntegrationException $exception) {
            $this->assertTrue($exception->retryable);
            $this->assertSame('jira', $exception->provider);
            $this->assertSame('create_issue', $exception->operation);
        }
    }

    public function test_create_issue_marks_authentication_error_as_non_retryable(): void
    {
        Http::fake([
            'https://example.atlassian.net/rest/api/3/issue' =>
            Http::response([], 401),
        ]);

        try {
            app(AtlassianJiraClient::class)->createIssue(
                new CreateJiraIssueData(
                    projectKey: 'SD',
                    issueTypeId: '10000',
                    summary: 'Test',
                    description: 'Test description',
                ),
            );

            $this->fail('Expected Jira request to fail.');
        } catch (\App\Exceptions\Integrations\IntegrationException $exception) {
            $this->assertFalse($exception->retryable);
            $this->assertSame('jira', $exception->provider);
            $this->assertSame('create_issue', $exception->operation);
        }
    }

    public function test_create_issue_fails_without_http_request_when_configuration_is_incomplete(): void
    {
        Http::preventStrayRequests();

        config([
            'integrations.jira.base_url' => 'https://example.atlassian.net',
            'integrations.jira.email' => 'developer@example.com',
            'integrations.jira.api_token' => null,
            'integrations.jira.project_key' => 'SD',
            'integrations.jira.issue_type_id' => '10000',
        ]);

        try {
            app(AtlassianJiraClient::class)->createIssue(
                new CreateJiraIssueData(
                    projectKey: 'SD',
                    issueTypeId: '10000',
                    summary: 'Test',
                    description: 'Test description',
                ),
            );

            $this->fail('Expected Jira configuration validation to fail.');
        } catch (IntegrationException $exception) {
            $this->assertFalse($exception->retryable);
            $this->assertSame('jira', $exception->provider);
            $this->assertSame('create_issue', $exception->operation);
            $this->assertStringContainsString(
                'api_token',
                $exception->getMessage(),
            );
        }

        Http::assertNothingSent();
    }
}
