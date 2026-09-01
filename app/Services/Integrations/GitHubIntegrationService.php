<?php

namespace App\Services\Integrations;

use App\Contracts\Integrations\GitHubClient;
use App\Data\Integrations\GitHub\CreateGitHubIssueData;
use App\Enums\Integrations\GitHubResourceType;
use App\Enums\Integrations\IntegrationSyncStatus;
use App\Exceptions\Integrations\IntegrationException;
use App\Models\GitHubResource;
use App\Models\Ticket;
use Throwable;

class GitHubIntegrationService
{
    public function __construct(
        private readonly GitHubClient $githubClient,
    ) {}

    public function createIssueForTicket(Ticket $ticket): GitHubResource
    {
        $repository = config('integrations.github.repository');

        if (blank($repository)) {
            throw new IntegrationException(
                message: 'GitHub integration configuration is incomplete: repository is missing.',
                provider: 'github',
                operation: 'create_issue',
                retryable: false,
            );
        }

        $githubResource = GitHubResource::query()
            ->where('ticket_id', $ticket->id)
            ->where('repository', $repository)
            ->where('resource_type', GitHubResourceType::Issue)
            ->first();

        if (
            $githubResource !== null
            && $githubResource->sync_status === IntegrationSyncStatus::Synced
        ) {
            return $githubResource;
        }

        if ($githubResource === null) {
            $githubResource = GitHubResource::create([
                'ticket_id' => $ticket->id,
                'resource_type' => GitHubResourceType::Issue,
                'repository' => $repository,
                'sync_status' => IntegrationSyncStatus::Pending,
            ]);
        } else {
            $githubResource->update([
                'sync_status' => IntegrationSyncStatus::Pending,
                'last_error' => null,
            ]);
        }

        try {
            $result = $this->githubClient->createIssue(
                new CreateGitHubIssueData(
                    repository: (string) $repository,
                    title: $ticket->title,
                    body: $ticket->description,
                ),
            );
        } catch (Throwable $exception) {
            $githubResource->update([
                'sync_status' => IntegrationSyncStatus::Failed,
                'last_error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $githubResource->update([
            'external_id' => $result->externalId,
            'resource_number' => $result->resourceNumber,
            'reference' => $result->reference,
            'url' => $result->url,
            'external_state' => $result->state,
            'external_updated_at' => $result->updatedAt,
            'last_synced_at' => now(),
            'sync_status' => IntegrationSyncStatus::Synced,
            'last_error' => null,
            'metadata' => $result->metadata,
        ]);

        return $githubResource->refresh();
    }
}
