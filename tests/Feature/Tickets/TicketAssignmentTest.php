<?php

namespace Tests\Feature\Tickets;

use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_cannot_assign_ticket(): void
    {
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Ticket',
            'description' => 'Test',
        ]);

        $response = $this
            ->actingAs($requester)
            ->patch(route('tickets.assignee.update', $ticket), [
                'assigned_to_id' => $agent->id,
            ]);

        $response->assertForbidden();

        $ticket->refresh();

        $this->assertNull($ticket->assigned_to_id);
    }

    public function test_agent_can_assign_ticket_to_agent(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $targetAgent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $requester = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Ticket',
            'description' => 'Test',
        ]);

        $response = $this
            ->actingAs($agent)
            ->patch(route('tickets.assignee.update', $ticket), [
                'assigned_to_id' => $targetAgent->id,
            ]);

        $response->assertRedirect(route('tickets.show', $ticket));

        $ticket->refresh();

        $this->assertSame($targetAgent->id, $ticket->assigned_to_id);

        $this->assertDatabaseHas('ticket_histories', [
            'ticket_id' => $ticket->id,
            'user_id' => $agent->id,
            'action' => 'assignee_changed',
        ]);
    }

    public function test_admin_can_assign_ticket_to_agent(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $requester = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Ticket',
            'description' => 'Test',
        ]);

        $response = $this
            ->actingAs($admin)
            ->patch(route('tickets.assignee.update', $ticket), [
                'assigned_to_id' => $agent->id,
            ]);

        $response->assertRedirect(route('tickets.show', $ticket));

        $ticket->refresh();

        $this->assertSame($agent->id, $ticket->assigned_to_id);
    }

    public function test_ticket_cannot_be_assigned_to_requester(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Ticket',
            'description' => 'Test',
        ]);

        $response = $this
            ->actingAs($agent)
            ->patch(route('tickets.assignee.update', $ticket), [
                'assigned_to_id' => $requester->id,
            ]);

        $response->assertSessionHasErrors('assigned_to_id');

        $ticket->refresh();

        $this->assertNull($ticket->assigned_to_id);
    }

    public function test_ticket_cannot_be_assigned_to_admin(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $requester = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Ticket',
            'description' => 'Test',
        ]);

        $response = $this
            ->actingAs($agent)
            ->patch(route('tickets.assignee.update', $ticket), [
                'assigned_to_id' => $admin->id,
            ]);

        $response->assertSessionHasErrors('assigned_to_id');

        $ticket->refresh();

        $this->assertNull($ticket->assigned_to_id);
    }

    public function test_ticket_can_be_unassigned(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $assignedAgent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $requester = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'assigned_to_id' => $assignedAgent->id,
            'title' => 'Ticket',
            'description' => 'Test',
        ]);

        $response = $this
            ->actingAs($agent)
            ->patch(route('tickets.assignee.update', $ticket), [
                'assigned_to_id' => null,
            ]);

        $response->assertRedirect(route('tickets.show', $ticket));

        $ticket->refresh();

        $this->assertNull($ticket->assigned_to_id);
    }
}