<?php

namespace App\Jobs;

use App\Data\Integrations\GitHub\GitHubIssueWebhookData;
use App\Enums\Integrations\IntegrationWebhookStatus;
use App\Models\IntegrationWebhookEvent;
use App\Services\Integrations\GitHubIntegrationService;
use DateTimeImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessGitHubWebhookJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly int $webhookEventId,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->webhookEventId;
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(
        GitHubIntegrationService $githubIntegrationService,
    ): void {
        $event = IntegrationWebhookEvent::query()
            ->find($this->webhookEventId);

        if ($event === null) {
            return;
        }

        if ($event->status !== IntegrationWebhookStatus::Pending) {
            return;
        }

        if ($event->event_type !== 'issues') {
            $event->update([
                'status' => IntegrationWebhookStatus::Ignored,
                'processed_at' => now(),
                'last_error' => null,
            ]);

            return;
        }

        try {
            $payload = $event->payload ?? [];

            $issue = $payload['issue'] ?? null;
            $repository = $payload['repository'] ?? null;

            if (
                ! is_array($issue)
                || ! is_array($repository)
                || ! isset(
                    $payload['action'],
                    $issue['id'],
                    $issue['number'],
                    $issue['html_url'],
                    $issue['state'],
                    $issue['updated_at'],
                    $repository['full_name'],
                )
            ) {
                $event->update([
                    'status' => IntegrationWebhookStatus::Failed,
                    'processed_at' => now(),
                    'last_error' => 'GitHub issues webhook payload is incomplete.',
                ]);

                return;
            }

            $resource = $githubIntegrationService->syncIssueFromWebhook(
                new GitHubIssueWebhookData(
                    action: (string) $payload['action'],
                    externalId: (string) $issue['id'],
                    repository: (string) $repository['full_name'],
                    resourceNumber: (int) $issue['number'],
                    url: (string) $issue['html_url'],
                    state: (string) $issue['state'],
                    updatedAt: new DateTimeImmutable(
                        (string) $issue['updated_at'],
                    ),
                    metadata: [
                        'action' => (string) $payload['action'],
                    ],
                ),
            );

            $event->update([
                'status' => $resource === null
                    ? IntegrationWebhookStatus::Ignored
                    : IntegrationWebhookStatus::Processed,
                'processed_at' => now(),
                'last_error' => null,
            ]);
        } catch (Throwable $exception) {
            $event->update([
                'last_error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $event = IntegrationWebhookEvent::query()
            ->find($this->webhookEventId);

        if ($event === null) {
            return;
        }

        $event->update([
            'status' => IntegrationWebhookStatus::Failed,
            'processed_at' => now(),
            'last_error' => $exception?->getMessage(),
        ]);
    }
}
