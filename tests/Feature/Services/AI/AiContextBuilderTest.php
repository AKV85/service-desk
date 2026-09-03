<?php

namespace Tests\Feature\Services\AI;

use App\Data\AI\AiContextData;
use App\Enums\Integrations\GitHubResourceType;
use App\Enums\Integrations\IntegrationSyncStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\GitHubResource;
use App\Models\JiraIssue;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketHistory;
use App\Models\User;
use App\Services\AI\AiContextBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiContextBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_normalized_ai_context_from_local_ticket_data(): void
    {
        $creator = User::factory()->requester()->create([
            'name' => 'Requester User',
        ]);

        $assignee = User::factory()->agent()->create([
            'name' => 'Agent User',
        ]);

        $commentAuthor = User::factory()->admin()->create([
            'name' => 'Admin User',
        ]);

        $ticket = Ticket::factory()
            ->assignedTo($assignee)
            ->highPriority()
            ->inProgress()
            ->create([
                'created_by_id' => $creator->id,
                'title' => 'Production API failure',
                'description' => 'Orders cannot be created through the API.',
            ]);

        $comment = TicketComment::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $commentAuthor->id,
            'body' => 'The failure started after the latest deployment.',
        ]);

        $history = TicketHistory::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $assignee->id,
            'action' => 'status_changed',
            'old_values' => [
                'status' => TicketStatus::New->value,
            ],
            'new_values' => [
                'status' => TicketStatus::InProgress->value,
            ],
            'created_at' => now(),
        ]);

        $jiraIssue = JiraIssue::create([
            'ticket_id' => $ticket->id,
            'external_id' => '10001',
            'issue_key' => 'SD-100',
            'url' => 'https://example.atlassian.net/browse/SD-100',
            'external_status' => 'In Progress',
            'sync_status' => IntegrationSyncStatus::Synced,
            'external_updated_at' => now(),
            'last_synced_at' => now(),
            'metadata' => [
                'internal_provider_data' => 'must not enter AI context',
            ],
        ]);

        $githubResource = GitHubResource::create([
            'ticket_id' => $ticket->id,
            'resource_type' => GitHubResourceType::Issue,
            'external_id' => '123456',
            'repository' => 'AKV85/service-desk',
            'resource_number' => 42,
            'reference' => null,
            'url' => 'https://github.com/AKV85/service-desk/issues/42',
            'external_state' => 'open',
            'sync_status' => IntegrationSyncStatus::Synced,
            'external_updated_at' => now(),
            'last_synced_at' => now(),
            'metadata' => [
                'raw_webhook_data' => 'must not enter AI context',
            ],
        ]);

        $context = app(AiContextBuilder::class)->build($ticket);

        $this->assertInstanceOf(AiContextData::class, $context);

        $this->assertSame($ticket->id, $context->ticket->id);
        $this->assertSame('Production API failure', $context->ticket->title);
        $this->assertSame(
            'Orders cannot be created through the API.',
            $context->ticket->description,
        );
        $this->assertSame(
            TicketStatus::InProgress->value,
            $context->ticket->status,
        );
        $this->assertSame(
            TicketPriority::High->value,
            $context->ticket->priority,
        );

        $this->assertSame($creator->id, $context->ticket->creator->id);
        $this->assertSame('Requester User', $context->ticket->creator->name);
        $this->assertSame(
            UserRole::Requester->value,
            $context->ticket->creator->role,
        );

        $this->assertNotNull($context->ticket->assignee);
        $this->assertSame($assignee->id, $context->ticket->assignee->id);
        $this->assertSame('Agent User', $context->ticket->assignee->name);
        $this->assertSame(
            UserRole::Agent->value,
            $context->ticket->assignee->role,
        );

        $this->assertCount(1, $context->comments);
        $this->assertSame($comment->id, $context->comments[0]->id);
        $this->assertSame(
            'The failure started after the latest deployment.',
            $context->comments[0]->body,
        );
        $this->assertSame(
            $commentAuthor->id,
            $context->comments[0]->author->id,
        );

        $this->assertCount(1, $context->history);
        $this->assertSame($history->id, $context->history[0]->id);
        $this->assertSame('status_changed', $context->history[0]->action);
        $this->assertSame(
            ['status' => TicketStatus::New->value],
            $context->history[0]->oldValues,
        );
        $this->assertSame(
            ['status' => TicketStatus::InProgress->value],
            $context->history[0]->newValues,
        );

        $this->assertNotNull($context->jiraIssue);
        $this->assertSame('10001', $context->jiraIssue->externalId);
        $this->assertSame('SD-100', $context->jiraIssue->issueKey);
        $this->assertSame(
            $jiraIssue->external_status,
            $context->jiraIssue->status,
        );

        $this->assertCount(1, $context->githubResources);
        $this->assertSame(
            GitHubResourceType::Issue->value,
            $context->githubResources[0]->type,
        );
        $this->assertSame(
            $githubResource->repository,
            $context->githubResources[0]->repository,
        );
        $this->assertSame(42, $context->githubResources[0]->resourceNumber);
        $this->assertSame('open', $context->githubResources[0]->state);
    }

    public function test_it_handles_missing_optional_context_data(): void
    {
        $creator = User::factory()->requester()->create();

        $ticket = Ticket::factory()->create([
            'created_by_id' => $creator->id,
            'assigned_to_id' => null,
        ]);

        $context = app(AiContextBuilder::class)->build($ticket);

        $this->assertNull($context->ticket->assignee);
        $this->assertSame([], $context->comments);
        $this->assertSame([], $context->history);
        $this->assertNull($context->jiraIssue);
        $this->assertSame([], $context->githubResources);
    }

    public function test_it_handles_partially_synchronized_jira_issue(): void
    {
        $ticket = Ticket::factory()->create();

        JiraIssue::create([
            'ticket_id' => $ticket->id,
            'sync_status' => IntegrationSyncStatus::Pending,
        ]);

        $context = app(AiContextBuilder::class)->build($ticket);

        $this->assertNotNull($context->jiraIssue);
        $this->assertNull($context->jiraIssue->externalId);
        $this->assertNull($context->jiraIssue->issueKey);
        $this->assertNull($context->jiraIssue->url);
        $this->assertNull($context->jiraIssue->status);
    }

    public function test_it_orders_context_collections_chronologically(): void
    {
        $ticket = Ticket::factory()->create();

        $user = User::factory()->agent()->create();

        $olderComment = TicketComment::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'body' => 'Older comment',
            'created_at' => now()->subHour(),
        ]);

        $newerComment = TicketComment::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'body' => 'Newer comment',
            'created_at' => now(),
        ]);

        $olderHistory = TicketHistory::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'action' => 'older_action',
            'created_at' => now()->subHour(),
        ]);

        $newerHistory = TicketHistory::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'action' => 'newer_action',
            'created_at' => now(),
        ]);

        $olderGitHubResource = GitHubResource::create([
            'ticket_id' => $ticket->id,
            'resource_type' => GitHubResourceType::Issue,
            'repository' => 'AKV85/service-desk',
            'sync_status' => IntegrationSyncStatus::Pending,
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        $newerGitHubResource = GitHubResource::create([
            'ticket_id' => $ticket->id,
            'resource_type' => GitHubResourceType::PullRequest,
            'repository' => 'AKV85/service-desk',
            'sync_status' => IntegrationSyncStatus::Pending,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $context = app(AiContextBuilder::class)->build($ticket);

        $this->assertSame(
            [$olderComment->id, $newerComment->id],
            array_column($context->comments, 'id'),
        );

        $this->assertSame(
            [$olderHistory->id, $newerHistory->id],
            array_column($context->history, 'id'),
        );

        $this->assertSame(
            [
                GitHubResourceType::Issue->value,
                GitHubResourceType::PullRequest->value,
            ],
            array_map(
                fn ($resource) => $resource->type,
                $context->githubResources,
            ),
        );
    }
}
