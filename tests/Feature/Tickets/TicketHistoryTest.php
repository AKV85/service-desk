<?php

namespace Tests\Feature\Tickets;

use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Models\TicketHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_can_view_history_of_own_ticket(): void
    {
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Ticket',
            'description' => 'Test',
        ]);

        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id' => $requester->id,
            'action' => 'status_changed',
            'old_values' => ['status' => 'new'],
            'new_values' => ['status' => 'in_progress'],
        ]);

        $response = $this
            ->actingAs($requester)
            ->get(route('tickets.show', $ticket));

        $response->assertOk();
        $response->assertSee('Changed status from');
        $response->assertSee('New');
        $response->assertSee('In Progress');
    }

    public function test_requester_cannot_view_history_of_another_users_ticket(): void
    {
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $owner = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $owner->id,
            'title' => 'Ticket',
            'description' => 'Test',
        ]);

        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id' => $owner->id,
            'action' => 'status_changed',
            'old_values' => ['status' => 'new'],
            'new_values' => ['status' => 'in_progress'],
        ]);

        $response = $this
            ->actingAs($requester)
            ->get(route('tickets.show', $ticket));

        $response->assertForbidden();
    }

    public function test_agent_can_view_ticket_history(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $owner = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $owner->id,
            'title' => 'Ticket',
            'description' => 'Test',
        ]);

        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id' => $agent->id,
            'action' => 'priority_changed',
            'old_values' => ['priority' => 'medium'],
            'new_values' => ['priority' => 'high'],
        ]);

        $response = $this
            ->actingAs($agent)
            ->get(route('tickets.show', $ticket));

        $response->assertOk();
        $response->assertSee('Changed priority from');
        $response->assertSee('Medium');
        $response->assertSee('High');
    }

    public function test_admin_can_view_ticket_history(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $owner = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $owner->id,
            'title' => 'Ticket',
            'description' => 'Test',
        ]);

        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'action' => 'status_changed',
            'old_values' => ['status' => 'new'],
            'new_values' => ['status' => 'in_progress'],
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('tickets.show', $ticket));

        $response->assertOk();
        $response->assertSee('Changed status from');
    }

    public function test_assignee_change_is_displayed(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
            'name' => 'Demo Agent',
        ]);

        $owner = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $owner->id,
            'title' => 'Ticket',
            'description' => 'Test',
        ]);

        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id' => $agent->id,
            'action' => 'assignee_changed',
            'old_values' => ['assigned_to_id' => null],
            'new_values' => ['assigned_to_id' => $agent->id],
        ]);

        $response = $this
            ->actingAs($agent)
            ->get(route('tickets.show', $ticket));

        $response->assertOk();
        $response->assertSee('Changed assignee from');
        $response->assertSee('Unassigned');
        $response->assertSee('Demo Agent');
    }

    public function test_history_displays_user_name_and_created_at(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
            'name' => 'History Agent',
        ]);

        $owner = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $owner->id,
            'title' => 'Ticket',
            'description' => 'Test',
        ]);

        $history = TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id' => $agent->id,
            'action' => 'priority_changed',
            'old_values' => ['priority' => 'medium'],
            'new_values' => ['priority' => 'high'],
            'created_at' => now(),
        ]);

        $response = $this
            ->actingAs($agent)
            ->get(route('tickets.show', $ticket));

        $response->assertOk();
        $response->assertSee('History Agent');
        $response->assertSee(
            $history->created_at->format('Y-m-d H:i')
        );
    }

    public function test_history_handles_unknown_user(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $owner = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $owner->id,
            'title' => 'Ticket',
            'description' => 'Test',
        ]);

        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id' => null,
            'action' => 'status_changed',
            'old_values' => ['status' => 'new'],
            'new_values' => ['status' => 'in_progress'],
        ]);

        $response = $this
            ->actingAs($agent)
            ->get(route('tickets.show', $ticket));

        $response->assertOk();
        $response->assertSee('Unknown user');
    }

    public function test_history_is_displayed_newest_first(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $owner = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $owner->id,
            'title' => 'Ticket',
            'description' => 'Test',
        ]);

        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id' => $agent->id,
            'action' => 'status_changed',
            'old_values' => ['status' => 'new'],
            'new_values' => ['status' => 'in_progress'],
            'created_at' => now()->subHour(),
        ]);

        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id' => $agent->id,
            'action' => 'priority_changed',
            'old_values' => ['priority' => 'medium'],
            'new_values' => ['priority' => 'high'],
            'created_at' => now(),
        ]);

        $response = $this
            ->actingAs($agent)
            ->get(route('tickets.show', $ticket));

        $response->assertOk();

        $response->assertSeeInOrder([
            'Changed priority from',
            'Changed status from',
        ]);
    }

    public function test_comment_history_is_displayed_without_duplicating_comment_body(): void
    {
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Ticket',
            'description' => 'Test',
        ]);

        $comment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $requester->id,
            'body' => 'Unique comment body for audit test',
        ]);

        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id' => $requester->id,
            'action' => 'comment_added',
            'old_values' => null,
            'new_values' => [
                'comment_id' => $comment->id,
            ],
            'created_at' => now(),
        ]);

        $response = $this
            ->actingAs($requester)
            ->get(route('tickets.show', $ticket));

        $response->assertOk();
        $response->assertSee('Added comment');

        $this->assertSame(
            1,
            substr_count(
                $response->getContent(),
                'Unique comment body for audit test'
            )
        );
    }

    public function test_attachment_history_displays_uploaded_file_name(): void
    {
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Ticket',
            'description' => 'Test',
        ]);

        $attachment = TicketAttachment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $requester->id,
            'original_name' => 'screenshot.png',
            'path' => 'ticket-attachments/1/screenshot.png',
            'mime_type' => 'image/png',
            'size' => 1024,
        ]);

        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id' => $requester->id,
            'action' => 'attachment_added',
            'old_values' => null,
            'new_values' => [
                'attachment_id' => $attachment->id,
                'original_name' => $attachment->original_name,
            ],
            'created_at' => now(),
        ]);

        $response = $this
            ->actingAs($requester)
            ->get(route('tickets.show', $ticket));

        $response->assertOk();

        $response->assertSeeInOrder([
            'Uploaded attachment',
            'screenshot.png',
        ]);
    }

    public function test_ticket_history_page_does_not_lazy_load_relations(): void
    {
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Ticket',
            'description' => 'Test',
        ]);

        for ($i = 1; $i <= 3; $i++) {
            TicketHistory::create([
                'ticket_id' => $ticket->id,
                'user_id' => User::factory()->create()->id,
                'action' => 'status_changed',
                'old_values' => [
                    'status' => 'new',
                ],
                'new_values' => [
                    'status' => 'in_progress',
                ],
                'created_at' => now()->addSeconds($i),
            ]);
        }

        Model::preventLazyLoading();

        try {
            $response = $this
                ->actingAs($requester)
                ->get(route('tickets.show', $ticket));

            $response->assertOk();
        } finally {
            Model::preventLazyLoading(false);
        }
    }

    public function test_legacy_assignee_history_resolves_user_names_from_ids(): void
    {
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $oldAssignee = User::factory()->create([
            'name' => 'Old Demo Agent',
            'role' => UserRole::Agent,
        ]);

        $newAssignee = User::factory()->create([
            'name' => 'New Demo Agent',
            'role' => UserRole::Agent,
        ]);

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Ticket',
            'description' => 'Test',
        ]);

        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id' => $requester->id,
            'action' => 'assignee_changed',
            'old_values' => [
                'assigned_to_id' => $oldAssignee->id,
            ],
            'new_values' => [
                'assigned_to_id' => $newAssignee->id,
            ],
            'created_at' => now(),
        ]);

        $response = $this
            ->actingAs($requester)
            ->get(route('tickets.show', $ticket));

        $response->assertOk();

        $response->assertSeeInOrder([
            'Changed assignee from',
            'Old Demo Agent',
            'to',
            'New Demo Agent',
        ]);
    }
}
