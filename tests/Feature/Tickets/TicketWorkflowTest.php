<?php

namespace Tests\Feature\Tickets;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Notifications\TicketStatusChangedNotification;
use Illuminate\Support\Facades\Notification;
use App\Notifications\TicketPriorityChangedNotification;

class TicketWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_cannot_change_priority(): void
    {
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Ticket',
            'description' => 'Test',
        ]);

        $response = $this
            ->actingAs($requester)
            ->patch(route('tickets.priority.update', $ticket), [
                'priority' => TicketPriority::High->value,
            ]);

        $response->assertForbidden();

        $ticket->refresh();

        $this->assertSame(TicketPriority::Medium, $ticket->priority);
    }

    public function test_agent_can_change_priority(): void
    {
        Notification::fake();
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
            ->actingAs($agent)
            ->patch(route('tickets.priority.update', $ticket), [
                'priority' => TicketPriority::High->value,
            ]);

        $response->assertRedirect(route('tickets.show', $ticket));

        $ticket->refresh();

        $this->assertSame(TicketPriority::High, $ticket->priority);

        $this->assertDatabaseHas('ticket_histories', [
            'ticket_id' => $ticket->id,
            'user_id' => $agent->id,
            'action' => 'priority_changed',
        ]);

        Notification::assertSentTo(
            $requester,
            TicketPriorityChangedNotification::class,
            function (TicketPriorityChangedNotification $notification) use ($ticket, $requester) {
                $data = $notification->toArray($requester);

                return $data['ticket_id'] === $ticket->id
                    && $data['old_priority'] === TicketPriority::Medium->value
                    && $data['new_priority'] === TicketPriority::High->value;
            }
        );
    }

    public function test_admin_can_change_priority(): void
    {
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
            ->actingAs($admin)
            ->patch(route('tickets.priority.update', $ticket), [
                'priority' => TicketPriority::Urgent->value,
            ]);

        $response->assertRedirect(route('tickets.show', $ticket));

        $ticket->refresh();

        $this->assertSame(TicketPriority::Urgent, $ticket->priority);
    }

    public function test_requester_can_reopen_own_resolved_ticket(): void
    {
        Notification::fake();
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Ticket',
            'description' => 'Test',
            'status' => TicketStatus::Resolved,
            'resolved_at' => now(),
        ]);

        $response = $this
            ->actingAs($requester)
            ->patch(route('tickets.status.update', $ticket), [
                'status' => TicketStatus::InProgress->value,
            ]);

        $response->assertRedirect(route('tickets.show', $ticket));

        $ticket->refresh();

        $this->assertSame(TicketStatus::InProgress, $ticket->status);
        $this->assertNull($ticket->resolved_at);

        $this->assertDatabaseHas('ticket_histories', [
            'ticket_id' => $ticket->id,
            'user_id' => $requester->id,
            'action' => 'status_changed',
        ]);

        Notification::assertNothingSent();
    }

    public function test_requester_cannot_change_status_of_non_resolved_ticket(): void
    {
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Ticket',
            'description' => 'Test',
            'status' => TicketStatus::New,
        ]);

        $response = $this
            ->actingAs($requester)
            ->patch(route('tickets.status.update', $ticket), [
                'status' => TicketStatus::InProgress->value,
            ]);

        $response->assertForbidden();

        $ticket->refresh();

        $this->assertSame(TicketStatus::New, $ticket->status);
    }

    public function test_agent_can_move_ticket_from_new_to_in_progress(): void
    {
        Notification::fake();
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $requester = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Ticket',
            'description' => 'Test',
            'status' => TicketStatus::New,
        ]);

        $response = $this
            ->actingAs($agent)
            ->patch(route('tickets.status.update', $ticket), [
                'status' => TicketStatus::InProgress->value,
            ]);

        $response->assertRedirect(route('tickets.show', $ticket));

        $ticket->refresh();

        $this->assertSame(TicketStatus::InProgress, $ticket->status);

        Notification::assertSentTo(
            $requester,
            TicketStatusChangedNotification::class,
            function (TicketStatusChangedNotification $notification) use ($ticket, $requester) {
                $data = $notification->toArray($requester);

                return $data['ticket_id'] === $ticket->id
                    && $data['old_status'] === TicketStatus::New->value
                    && $data['new_status'] === TicketStatus::InProgress->value;
            }
        );
    }

    public function test_agent_can_resolve_ticket_and_resolved_at_is_set(): void
    {
        Notification::fake();
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $requester = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Ticket',
            'description' => 'Test',
            'status' => TicketStatus::InProgress,
        ]);

        $response = $this
            ->actingAs($agent)
            ->patch(route('tickets.status.update', $ticket), [
                'status' => TicketStatus::Resolved->value,
            ]);

        $response->assertRedirect(route('tickets.show', $ticket));

        $ticket->refresh();

        $this->assertSame(TicketStatus::Resolved, $ticket->status);
        $this->assertNotNull($ticket->resolved_at);

        Notification::assertSentTo(
            $requester,
            TicketStatusChangedNotification::class
        );
    }

    public function test_agent_can_close_resolved_ticket_and_closed_at_is_set(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $requester = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Ticket',
            'description' => 'Test',
            'status' => TicketStatus::Resolved,
            'resolved_at' => now(),
        ]);

        $response = $this
            ->actingAs($agent)
            ->patch(route('tickets.status.update', $ticket), [
                'status' => TicketStatus::Closed->value,
            ]);

        $response->assertRedirect(route('tickets.show', $ticket));

        $ticket->refresh();

        $this->assertSame(TicketStatus::Closed, $ticket->status);
        $this->assertNotNull($ticket->closed_at);
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $requester = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Ticket',
            'description' => 'Test',
            'status' => TicketStatus::New,
        ]);

        $response = $this
            ->actingAs($agent)
            ->patch(route('tickets.status.update', $ticket), [
                'status' => TicketStatus::Closed->value,
            ]);

        $response->assertSessionHasErrors('status');

        $ticket->refresh();

        $this->assertSame(TicketStatus::New, $ticket->status);
    }

    public function test_invalid_priority_value_is_rejected(): void
    {
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
            ->actingAs($agent)
            ->patch(route('tickets.priority.update', $ticket), [
                'priority' => 'nuclear',
            ]);

        $response->assertSessionHasErrors('priority');

        $ticket->refresh();

        $this->assertSame(TicketPriority::Medium, $ticket->priority);
    }
}
