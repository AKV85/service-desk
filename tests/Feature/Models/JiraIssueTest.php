<?php

namespace Tests\Feature\Models;

use App\Enums\Integrations\IntegrationSyncStatus;
use App\Models\JiraIssue;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JiraIssueTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_has_one_jira_issue(): void
    {
        $ticket = Ticket::factory()->create();

        $jiraIssue = JiraIssue::create([
            'ticket_id' => $ticket->id,
            'external_id' => '10001',
            'issue_key' => 'SD-123',
            'url' => 'https://example.atlassian.net/browse/SD-123',
            'sync_status' => IntegrationSyncStatus::Synced,
        ]);

        $this->assertTrue($ticket->jiraIssue->is($jiraIssue));
    }

    public function test_jira_issue_belongs_to_ticket(): void
    {
        $ticket = Ticket::factory()->create();

        $jiraIssue = JiraIssue::create([
            'ticket_id' => $ticket->id,
            'sync_status' => IntegrationSyncStatus::Pending,
        ]);

        $this->assertTrue($jiraIssue->ticket->is($ticket));
    }

    public function test_sync_status_is_cast_to_enum(): void
    {
        $ticket = Ticket::factory()->create();

        $jiraIssue = JiraIssue::create([
            'ticket_id' => $ticket->id,
            'sync_status' => IntegrationSyncStatus::Failed,
        ]);

        $jiraIssue->refresh();

        $this->assertSame(
            IntegrationSyncStatus::Failed,
            $jiraIssue->sync_status,
        );
    }

    public function test_metadata_is_cast_to_array(): void
    {
        $ticket = Ticket::factory()->create();

        $metadata = [
            'project' => 'SD',
            'issue_type' => 'Task',
        ];

        $jiraIssue = JiraIssue::create([
            'ticket_id' => $ticket->id,
            'sync_status' => IntegrationSyncStatus::Synced,
            'metadata' => $metadata,
        ]);

        $jiraIssue->refresh();

        $this->assertSame($metadata, $jiraIssue->metadata);
    }
}
