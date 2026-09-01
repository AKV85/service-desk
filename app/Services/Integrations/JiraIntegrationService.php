<?php

namespace App\Services\Integrations;

use App\Contracts\Integrations\JiraClient;
use App\Data\Integrations\Jira\CreateJiraIssueData;
use App\Enums\Integrations\IntegrationSyncStatus;
use App\Exceptions\Integrations\IntegrationException;
use App\Models\JiraIssue;
use App\Models\Ticket;
use Throwable;

class JiraIntegrationService
{
    public function __construct(
        private readonly JiraClient $jiraClient,
    ) {}

    public function createIssueForTicket(Ticket $ticket): JiraIssue
    {
        $jiraIssue = $ticket->jiraIssue;

        if (
            $jiraIssue !== null
            && $jiraIssue->sync_status === IntegrationSyncStatus::Synced
        ) {
            return $jiraIssue;
        }

        if ($jiraIssue === null) {
            $jiraIssue = JiraIssue::create([
                'ticket_id' => $ticket->id,
                'sync_status' => IntegrationSyncStatus::Pending,
            ]);
        } else {
            $jiraIssue->update([
                'sync_status' => IntegrationSyncStatus::Pending,
                'last_error' => null,
            ]);
        }

        try {
            $projectKey = config('integrations.jira.project_key');
            $issueTypeId = config('integrations.jira.issue_type_id');

            if (blank($projectKey)) {
                throw new IntegrationException(
                    message: 'Jira integration configuration is incomplete: project_key is missing.',
                    provider: 'jira',
                    operation: 'create_issue',
                    retryable: false,
                );
            }

            if (blank($issueTypeId)) {
                throw new IntegrationException(
                    message: 'Jira integration configuration is incomplete: issue_type_id is missing.',
                    provider: 'jira',
                    operation: 'create_issue',
                    retryable: false,
                );
            }

            $result = $this->jiraClient->createIssue(
                new CreateJiraIssueData(
                    projectKey: (string) $projectKey,
                    issueTypeId: (string) $issueTypeId,
                    summary: $ticket->title,
                    description: $ticket->description,
                ),
            );
        } catch (Throwable $exception) {
            $jiraIssue->update([
                'sync_status' => IntegrationSyncStatus::Failed,
                'last_error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $jiraIssue->update([
            'external_id' => $result->externalId,
            'issue_key' => $result->issueKey,
            'url' => $result->url,
            'external_status' => $result->status,
            'external_updated_at' => $result->updatedAt,
            'last_synced_at' => now(),
            'sync_status' => IntegrationSyncStatus::Synced,
            'last_error' => null,
            'metadata' => $result->metadata,
        ]);

        return $jiraIssue->refresh();
    }
}
