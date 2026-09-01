<?php

namespace Tests\Feature\Jobs;

use App\Contracts\Integrations\GitHubClient;
use App\Enums\Integrations\GitHubResourceType;
use App\Enums\Integrations\IntegrationSyncStatus;
use App\Enums\Integrations\IntegrationWebhookStatus;
use App\Jobs\ProcessGitHubWebhookJob;
use App\Models\GitHubResource;
use App\Models\IntegrationWebhookEvent;
use App\Models\Ticket;
use App\Services\Integrations\GitHubIntegrationService;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ProcessGitHubWebhookJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_processes_linked_github_issue_webhook(): void
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
        ]);

        $event = $this->createWebhookEvent();

        $job = new ProcessGitHubWebhookJob($event->id);

        $job->handle($this->githubIntegrationService());

        $event->refresh();
        $resource->refresh();

        $this->assertSame(
            IntegrationWebhookStatus::Processed,
            $event->status,
        );
        $this->assertNotNull($event->processed_at);
        $this->assertNull($event->last_error);

        $this->assertSame('closed', $resource->external_state);
        $this->assertSame(
            IntegrationSyncStatus::Synced,
            $resource->sync_status,
        );
    }

    public function test_unknown_github_issue_is_ignored(): void
    {
        $event = $this->createWebhookEvent();

        $job = new ProcessGitHubWebhookJob($event->id);

        $job->handle($this->githubIntegrationService());

        $event->refresh();

        $this->assertSame(
            IntegrationWebhookStatus::Ignored,
            $event->status,
        );
        $this->assertNotNull($event->processed_at);
        $this->assertNull($event->last_error);

        $this->assertDatabaseCount('github_resources', 0);
    }

    public function test_unsupported_event_type_is_ignored(): void
    {
        $event = IntegrationWebhookEvent::create([
            'provider' => 'github',
            'external_event_id' => 'delivery-ping',
            'event_type' => 'ping',
            'status' => IntegrationWebhookStatus::Pending,
            'payload' => [
                'zen' => 'Keep it logically awesome.',
            ],
            'received_at' => now(),
        ]);

        $job = new ProcessGitHubWebhookJob($event->id);

        $job->handle($this->githubIntegrationService());

        $event->refresh();

        $this->assertSame(
            IntegrationWebhookStatus::Ignored,
            $event->status,
        );
        $this->assertNotNull($event->processed_at);
        $this->assertNull($event->last_error);
    }

    public function test_incomplete_issues_payload_is_marked_as_failed(): void
    {
        $event = IntegrationWebhookEvent::create([
            'provider' => 'github',
            'external_event_id' => 'delivery-invalid',
            'event_type' => 'issues',
            'status' => IntegrationWebhookStatus::Pending,
            'payload' => [
                'action' => 'closed',
            ],
            'received_at' => now(),
        ]);

        $job = new ProcessGitHubWebhookJob($event->id);

        $job->handle($this->githubIntegrationService());

        $event->refresh();

        $this->assertSame(
            IntegrationWebhookStatus::Failed,
            $event->status,
        );
        $this->assertNotNull($event->processed_at);
        $this->assertSame(
            'GitHub issues webhook payload is incomplete.',
            $event->last_error,
        );
    }

    public function test_already_processed_event_is_not_processed_again(): void
    {
        $event = $this->createWebhookEvent([
            'status' => IntegrationWebhookStatus::Processed,
            'processed_at' => now(),
        ]);

        $job = new ProcessGitHubWebhookJob($event->id);

        $job->handle($this->githubIntegrationService());

        $event->refresh();

        $this->assertSame(
            IntegrationWebhookStatus::Processed,
            $event->status,
        );

        $this->assertDatabaseCount('github_resources', 0);
    }

    private function createWebhookEvent(array $attributes = []): IntegrationWebhookEvent
    {
        return IntegrationWebhookEvent::create(array_merge([
            'provider' => 'github',
            'external_event_id' => 'delivery-123',
            'event_type' => 'issues',
            'status' => IntegrationWebhookStatus::Pending,
            'payload' => [
                'action' => 'closed',
                'issue' => [
                    'id' => 123456,
                    'number' => 42,
                    'html_url' => 'https://github.com/AKV85/service-desk/issues/42',
                    'state' => 'closed',
                    'updated_at' => '2026-09-01T13:00:00Z',
                ],
                'repository' => [
                    'full_name' => 'AKV85/service-desk',
                ],
            ],
            'received_at' => now(),
        ], $attributes));
    }

    private function githubIntegrationService(): GitHubIntegrationService
    {
        $client = Mockery::mock(GitHubClient::class);

        $client->shouldNotReceive('createIssue');

        return new GitHubIntegrationService($client);
    }

    public function test_webhook_remains_pending_when_processing_throws_for_retry(): void
    {
        $event = IntegrationWebhookEvent::query()->create([
            'provider' => 'github',
            'external_event_id' => 'delivery-retry',
            'event_type' => 'issues',
            'status' => IntegrationWebhookStatus::Pending,
            'payload' => [
                'action' => 'closed',
                'issue' => [
                    'id' => 123456,
                    'number' => 42,
                    'html_url' => 'https://github.com/AKV85/service-desk/issues/42',
                    'state' => 'closed',
                    'updated_at' => '2026-09-01T20:00:00Z',
                ],
                'repository' => [
                    'full_name' => 'AKV85/service-desk',
                ],
            ],
            'received_at' => now(),
        ]);

        $service = Mockery::mock(GitHubIntegrationService::class);
        $service
            ->shouldReceive('syncIssueFromWebhook')
            ->once()
            ->andThrow(new RuntimeException('Temporary GitHub processing failure.'));

        $job = new ProcessGitHubWebhookJob($event->id);

        try {
            $job->handle($service);

            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Temporary GitHub processing failure.',
                $exception->getMessage(),
            );
        }

        $event->refresh();

        $this->assertSame(
            IntegrationWebhookStatus::Pending,
            $event->status,
        );
        $this->assertSame(
            'Temporary GitHub processing failure.',
            $event->last_error,
        );
        $this->assertNull($event->processed_at);
    }

    public function test_failed_callback_marks_webhook_as_failed(): void
    {
        $event = IntegrationWebhookEvent::query()->create([
            'provider' => 'github',
            'external_event_id' => 'delivery-final-failure',
            'event_type' => 'issues',
            'status' => IntegrationWebhookStatus::Pending,
            'payload' => [
                'action' => 'closed',
                'issue' => [
                    'id' => 123456,
                    'number' => 42,
                    'html_url' => 'https://github.com/AKV85/service-desk/issues/42',
                    'state' => 'closed',
                    'updated_at' => '2026-09-01T20:00:00Z',
                ],
                'repository' => [
                    'full_name' => 'AKV85/service-desk',
                ],
            ],
            'received_at' => now(),
        ]);

        $job = new ProcessGitHubWebhookJob($event->id);

        $job->failed(
            new RuntimeException('GitHub webhook processing exhausted retries.'),
        );

        $event->refresh();

        $this->assertSame(
            IntegrationWebhookStatus::Failed,
            $event->status,
        );
        $this->assertNotNull($event->processed_at);
        $this->assertSame(
            'GitHub webhook processing exhausted retries.',
            $event->last_error,
        );
    }
}
