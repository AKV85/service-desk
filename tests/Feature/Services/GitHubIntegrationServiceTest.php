<?php

namespace Tests\Feature\Services;

use App\Contracts\Integrations\GitHubClient;
use App\Data\Integrations\GitHub\CreateGitHubIssueData;
use App\Data\Integrations\GitHub\GitHubIssueWebhookData;
use App\Data\Integrations\GitHub\GitHubResourceData;
use App\Enums\Integrations\GitHubResourceType;
use App\Enums\Integrations\IntegrationSyncStatus;
use App\Exceptions\Integrations\IntegrationException;
use App\Models\GitHubResource;
use App\Models\Ticket;
use App\Services\Integrations\GitHubIntegrationService;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GitHubIntegrationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'integrations.github.repository' => 'AKV85/service-desk',
        ]);
    }

    public function test_it_creates_and_persists_synced_github_issue(): void
    {
        $ticket = Ticket::factory()->create([
            'title' => 'GitHub integration',
            'description' => 'Create GitHub issue from ticket.',
        ]);

        $client = Mockery::mock(GitHubClient::class);

        $client
            ->shouldReceive('createIssue')
            ->once()
            ->withArgs(function (CreateGitHubIssueData $data): bool {
                return $data->repository === 'AKV85/service-desk'
                    && $data->title === 'GitHub integration'
                    && $data->body === 'Create GitHub issue from ticket.';
            })
            ->andReturn(
                new GitHubResourceData(
                    type: GitHubResourceType::Issue,
                    externalId: '123456',
                    repository: 'AKV85/service-desk',
                    resourceNumber: 42,
                    reference: null,
                    url: 'https://github.com/AKV85/service-desk/issues/42',
                    title: 'GitHub integration',
                    state: 'open',
                    updatedAt: new DateTimeImmutable('2026-09-01T12:00:00Z'),
                    metadata: [
                        'id' => 123456,
                    ],
                ),
            );

        $service = new GitHubIntegrationService($client);

        $resource = $service->createIssueForTicket($ticket);

        $this->assertSame(
            GitHubResourceType::Issue,
            $resource->resource_type,
        );
        $this->assertSame('123456', $resource->external_id);
        $this->assertSame('AKV85/service-desk', $resource->repository);
        $this->assertSame(42, $resource->resource_number);
        $this->assertSame(
            'https://github.com/AKV85/service-desk/issues/42',
            $resource->url,
        );
        $this->assertSame('open', $resource->external_state);
        $this->assertSame(
            IntegrationSyncStatus::Synced,
            $resource->sync_status,
        );
        $this->assertNull($resource->last_error);
        $this->assertNotNull($resource->last_synced_at);
        $this->assertEquals(
            [
                'id' => 123456,
            ],
            $resource->metadata,
        );

        $this->assertDatabaseHas('github_resources', [
            'ticket_id' => $ticket->id,
            'external_id' => '123456',
            'repository' => 'AKV85/service-desk',
            'resource_number' => 42,
            'external_state' => 'open',
            'sync_status' => IntegrationSyncStatus::Synced->value,
        ]);
    }

    public function test_it_does_not_create_duplicate_issue_when_already_synced(): void
    {
        $ticket = Ticket::factory()->create();

        $existing = GitHubResource::create([
            'ticket_id' => $ticket->id,
            'resource_type' => GitHubResourceType::Issue,
            'external_id' => '123456',
            'repository' => 'AKV85/service-desk',
            'resource_number' => 42,
            'url' => 'https://github.com/AKV85/service-desk/issues/42',
            'sync_status' => IntegrationSyncStatus::Synced,
        ]);

        $client = Mockery::mock(GitHubClient::class);

        $client
            ->shouldNotReceive('createIssue');

        $service = new GitHubIntegrationService($client);

        $resource = $service->createIssueForTicket($ticket);

        $this->assertTrue($resource->is($existing));

        $this->assertSame(
            1,
            GitHubResource::query()->count(),
        );
    }

    public function test_failure_marks_resource_as_failed(): void
    {
        $ticket = Ticket::factory()->create();

        $client = Mockery::mock(GitHubClient::class);

        $client
            ->shouldReceive('createIssue')
            ->once()
            ->andThrow(
                new IntegrationException(
                    message: 'GitHub create issue request failed with HTTP 503.',
                    provider: 'github',
                    operation: 'create_issue',
                    retryable: true,
                ),
            );

        $service = new GitHubIntegrationService($client);

        try {
            $service->createIssueForTicket($ticket);

            $this->fail('Expected IntegrationException was not thrown.');
        } catch (IntegrationException $exception) {
            $this->assertTrue($exception->retryable);
        }

        $resource = GitHubResource::query()->firstOrFail();

        $this->assertSame(
            IntegrationSyncStatus::Failed,
            $resource->sync_status,
        );
        $this->assertSame(
            'GitHub create issue request failed with HTTP 503.',
            $resource->last_error,
        );
    }

    public function test_failed_resource_is_retried_and_error_is_cleared(): void
    {
        $ticket = Ticket::factory()->create();

        $existing = GitHubResource::create([
            'ticket_id' => $ticket->id,
            'resource_type' => GitHubResourceType::Issue,
            'repository' => 'AKV85/service-desk',
            'sync_status' => IntegrationSyncStatus::Failed,
            'last_error' => 'Previous failure.',
        ]);

        $client = Mockery::mock(GitHubClient::class);

        $client
            ->shouldReceive('createIssue')
            ->once()
            ->andReturn(
                new GitHubResourceData(
                    type: GitHubResourceType::Issue,
                    externalId: '123456',
                    repository: 'AKV85/service-desk',
                    resourceNumber: 42,
                    reference: null,
                    url: 'https://github.com/AKV85/service-desk/issues/42',
                    title: 'Retry successful',
                    state: 'open',
                    updatedAt: new DateTimeImmutable('2026-09-01T12:00:00Z'),
                    metadata: [],
                ),
            );

        $service = new GitHubIntegrationService($client);

        $resource = $service->createIssueForTicket($ticket);

        $this->assertTrue($resource->is($existing));
        $this->assertSame(
            IntegrationSyncStatus::Synced,
            $resource->sync_status,
        );
        $this->assertNull($resource->last_error);
        $this->assertSame('123456', $resource->external_id);

        $this->assertSame(
            1,
            GitHubResource::query()->count(),
        );
    }

    public function test_missing_repository_configuration_is_not_retryable(): void
    {
        config([
            'integrations.github.repository' => null,
        ]);

        $ticket = Ticket::factory()->create();

        $client = Mockery::mock(GitHubClient::class);

        $client
            ->shouldNotReceive('createIssue');

        $service = new GitHubIntegrationService($client);

        try {
            $service->createIssueForTicket($ticket);

            $this->fail('Expected IntegrationException was not thrown.');
        } catch (IntegrationException $exception) {
            $this->assertSame('github', $exception->provider);
            $this->assertSame(
                'create_issue',
                $exception->operation,
            );
            $this->assertFalse($exception->retryable);
        }

        $this->assertDatabaseCount('github_resources', 0);
    }

    public function test_it_synchronizes_existing_github_issue_from_webhook(): void
    {
        $ticket = Ticket::factory()->create();

        $resource = GitHubResource::create([
            'ticket_id' => $ticket->id,
            'resource_type' => GitHubResourceType::Issue,
            'external_id' => '123456',
            'repository' => 'AKV85/service-desk',
            'resource_number' => 42,
            'url' => 'https://github.com/AKV85/service-desk/issues/42',
            'external_state' => 'open',
            'external_updated_at' => new DateTimeImmutable('2026-09-01T12:00:00Z'),
            'sync_status' => IntegrationSyncStatus::Synced,
            'metadata' => [
                'id' => 123456,
                'created_by' => 'service-desk',
            ],
        ]);

        $client = Mockery::mock(GitHubClient::class);

        $client->shouldNotReceive('createIssue');

        $service = new GitHubIntegrationService($client);

        $result = $service->syncIssueFromWebhook(
            new GitHubIssueWebhookData(
                action: 'closed',
                externalId: '123456',
                repository: 'AKV85/service-desk',
                resourceNumber: 42,
                url: 'https://github.com/AKV85/service-desk/issues/42',
                state: 'closed',
                updatedAt: new DateTimeImmutable('2026-09-01T13:00:00Z'),
                metadata: [
                    'action' => 'closed',
                ],
            ),
        );

        $this->assertNotNull($result);
        $this->assertTrue($result->is($resource));
        $this->assertSame('closed', $result->external_state);
        $this->assertSame(
            IntegrationSyncStatus::Synced,
            $result->sync_status,
        );
        $this->assertNull($result->last_error);
        $this->assertNotNull($result->last_synced_at);
        $this->assertSame(123456, $result->metadata['id']);
        $this->assertSame(
            'service-desk',
            $result->metadata['created_by'],
        );
        $this->assertSame(
            'closed',
            $result->metadata['action'],
        );

        $this->assertDatabaseHas('github_resources', [
            'id' => $resource->id,
            'external_state' => 'closed',
            'sync_status' => IntegrationSyncStatus::Synced->value,
        ]);
    }

    public function test_it_ignores_webhook_for_unknown_github_issue(): void
    {
        $client = Mockery::mock(GitHubClient::class);

        $client->shouldNotReceive('createIssue');

        $service = new GitHubIntegrationService($client);

        $result = $service->syncIssueFromWebhook(
            new GitHubIssueWebhookData(
                action: 'edited',
                externalId: '999999',
                repository: 'AKV85/service-desk',
                resourceNumber: 999,
                url: 'https://github.com/AKV85/service-desk/issues/999',
                state: 'open',
                updatedAt: new DateTimeImmutable('2026-09-01T13:00:00Z'),
            ),
        );

        $this->assertNull($result);
        $this->assertDatabaseCount('github_resources', 0);
    }

    public function test_older_webhook_does_not_overwrite_newer_github_state(): void
    {
        $ticket = Ticket::factory()->create();

        $resource = GitHubResource::create([
            'ticket_id' => $ticket->id,
            'resource_type' => GitHubResourceType::Issue,
            'external_id' => '123456',
            'repository' => 'AKV85/service-desk',
            'resource_number' => 42,
            'url' => 'https://github.com/AKV85/service-desk/issues/42',
            'external_state' => 'closed',
            'external_updated_at' => new DateTimeImmutable('2026-09-01T14:00:00Z'),
            'sync_status' => IntegrationSyncStatus::Synced,
            'metadata' => [
                'action' => 'closed',
            ],
        ]);

        $client = Mockery::mock(GitHubClient::class);

        $client->shouldNotReceive('createIssue');

        $service = new GitHubIntegrationService($client);

        $result = $service->syncIssueFromWebhook(
            new GitHubIssueWebhookData(
                action: 'reopened',
                externalId: '123456',
                repository: 'AKV85/service-desk',
                resourceNumber: 42,
                url: 'https://github.com/AKV85/service-desk/issues/42',
                state: 'open',
                updatedAt: new DateTimeImmutable('2026-09-01T13:00:00Z'),
                metadata: [
                    'action' => 'reopened',
                ],
            ),
        );

        $this->assertNotNull($result);

        $resource->refresh();

        $this->assertSame('closed', $resource->external_state);
        $this->assertSame(
            '2026-09-01 14:00:00',
            $resource->external_updated_at->utc()->format('Y-m-d H:i:s'),
        );
        $this->assertSame(
            ['action' => 'closed'],
            $resource->metadata,
        );
    }
}
