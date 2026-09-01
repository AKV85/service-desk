<?php

namespace Tests\Feature\Integrations\GitHub;

use App\Data\Integrations\GitHub\CreateGitHubIssueData;
use App\Enums\Integrations\GitHubResourceType;
use App\Exceptions\Integrations\IntegrationException;
use App\Integrations\GitHub\GitHubApiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GitHubApiClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'integrations.github.token' => 'test-token',
            'integrations.github.repository' => 'AKV85/service-desk',
        ]);

        Http::preventStrayRequests();
    }

    public function test_it_creates_github_issue(): void
    {
        Http::fake([
            'api.github.com/repos/AKV85/service-desk/issues' => Http::response([
                'id' => 123456,
                'number' => 42,
                'html_url' => 'https://github.com/AKV85/service-desk/issues/42',
                'title' => 'Test issue',
                'state' => 'open',
                'updated_at' => '2026-09-01T12:00:00Z',
            ], 201),
        ]);

        $client = app(GitHubApiClient::class);

        $result = $client->createIssue(
            new CreateGitHubIssueData(
                repository: 'AKV85/service-desk',
                title: 'Test issue',
                body: 'Test body',
            ),
        );

        $this->assertSame(
            GitHubResourceType::Issue,
            $result->type,
        );
        $this->assertSame('123456', $result->externalId);
        $this->assertSame('AKV85/service-desk', $result->repository);
        $this->assertSame(42, $result->resourceNumber);
        $this->assertNull($result->reference);
        $this->assertSame(
            'https://github.com/AKV85/service-desk/issues/42',
            $result->url,
        );
        $this->assertSame('Test issue', $result->title);
        $this->assertSame('open', $result->state);

        Http::assertSent(function (Request $request): bool {
            return $request->url()
                === 'https://api.github.com/repos/AKV85/service-desk/issues'
                && $request->method() === 'POST'
                && $request->hasHeader(
                    'Authorization',
                    'Bearer test-token',
                )
                && $request->hasHeader(
                    'X-GitHub-Api-Version',
                    '2026-03-10',
                )
                && $request['title'] === 'Test issue'
                && $request['body'] === 'Test body';
        });
    }

    public function test_server_error_is_retryable(): void
    {
        Http::fake([
            '*' => Http::response([], 503),
        ]);

        $client = app(GitHubApiClient::class);

        try {
            $client->createIssue(
                new CreateGitHubIssueData(
                    repository: 'AKV85/service-desk',
                    title: 'Test issue',
                    body: 'Test body',
                ),
            );

            $this->fail('Expected IntegrationException was not thrown.');
        } catch (IntegrationException $exception) {
            $this->assertSame('github', $exception->provider);
            $this->assertSame(
                'create_issue',
                $exception->operation,
            );
            $this->assertTrue($exception->retryable);
        }
    }

    public function test_rate_limit_error_is_retryable(): void
    {
        Http::fake([
            '*' => Http::response([], 429),
        ]);

        $client = app(GitHubApiClient::class);

        try {
            $client->createIssue(
                new CreateGitHubIssueData(
                    repository: 'AKV85/service-desk',
                    title: 'Test issue',
                    body: 'Test body',
                ),
            );

            $this->fail('Expected IntegrationException was not thrown.');
        } catch (IntegrationException $exception) {
            $this->assertTrue($exception->retryable);
        }
    }

    public function test_validation_error_is_not_retryable(): void
    {
        Http::fake([
            '*' => Http::response([], 422),
        ]);

        $client = app(GitHubApiClient::class);

        try {
            $client->createIssue(
                new CreateGitHubIssueData(
                    repository: 'AKV85/service-desk',
                    title: 'Test issue',
                    body: 'Test body',
                ),
            );

            $this->fail('Expected IntegrationException was not thrown.');
        } catch (IntegrationException $exception) {
            $this->assertFalse($exception->retryable);
        }
    }

    public function test_missing_configuration_is_not_retryable(): void
    {
        config([
            'integrations.github.token' => null,
        ]);

        $client = app(GitHubApiClient::class);

        try {
            $client->createIssue(
                new CreateGitHubIssueData(
                    repository: 'AKV85/service-desk',
                    title: 'Test issue',
                    body: 'Test body',
                ),
            );

            $this->fail('Expected IntegrationException was not thrown.');
        } catch (IntegrationException $exception) {
            $this->assertSame('github', $exception->provider);
            $this->assertSame(
                'create_issue',
                $exception->operation,
            );
            $this->assertFalse($exception->retryable);
        }

        Http::assertNothingSent();
    }
}
