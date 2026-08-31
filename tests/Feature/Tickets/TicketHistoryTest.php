<?php

namespace Tests\Feature\Tickets;

use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
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
        $response->assertSee('new');
        $response->assertSee('in_progress');
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
        $response->assertSee('medium');
        $response->assertSee('high');
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
        $response->assertSee((string) $agent->id);
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

    public function test_history_is_displayed_oldest_first(): void
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
            'Changed status from',
            'Changed priority from',
        ]);
    }
}
