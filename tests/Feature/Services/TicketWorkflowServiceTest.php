<?php

namespace Tests\Feature\Services;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketWorkflowService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TicketWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    private TicketWorkflowService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TicketWorkflowService::class);
    }

    public function test_status_can_change_from_new_to_in_progress(): void
    {
        $user = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $user->id,
            'title' => 'Ticket',
            'description' => 'Test',
        ]);

        $this->service->changeStatus(
            $ticket,
            TicketStatus::InProgress,
            $user
        );

        $ticket->refresh();

        $this->assertSame(TicketStatus::InProgress, $ticket->status);

        $this->assertDatabaseHas('ticket_histories', [
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'action' => 'status_changed',
        ]);
    }

    public function test_resolved_status_sets_resolved_at(): void
    {
        $user = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $user->id,
            'title' => 'Ticket',
            'description' => 'Test',
            'status' => TicketStatus::InProgress,
        ]);

        $this->service->changeStatus(
            $ticket,
            TicketStatus::Resolved,
            $user
        );

        $ticket->refresh();

        $this->assertSame(TicketStatus::Resolved, $ticket->status);
        $this->assertNotNull($ticket->resolved_at);
    }

    public function test_reopening_resolved_ticket_clears_resolved_at(): void
    {
        $user = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $user->id,
            'title' => 'Ticket',
            'description' => 'Test',
            'status' => TicketStatus::Resolved,
            'resolved_at' => now(),
        ]);

        $this->service->changeStatus(
            $ticket,
            TicketStatus::InProgress,
            $user
        );

        $ticket->refresh();

        $this->assertSame(TicketStatus::InProgress, $ticket->status);
        $this->assertNull($ticket->resolved_at);
    }

    public function test_closed_status_sets_closed_at(): void
    {
        $user = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $user->id,
            'title' => 'Ticket',
            'description' => 'Test',
            'status' => TicketStatus::Resolved,
            'resolved_at' => now(),
        ]);

        $this->service->changeStatus(
            $ticket,
            TicketStatus::Closed,
            $user
        );

        $ticket->refresh();

        $this->assertSame(TicketStatus::Closed, $ticket->status);
        $this->assertNotNull($ticket->closed_at);
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $user = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $user->id,
            'title' => 'Ticket',
            'description' => 'Test',
            'status' => TicketStatus::New,
        ]);

        $this->service->changeStatus(
            $ticket,
            TicketStatus::Closed,
            $user
        );
    }

    public function test_priority_can_be_changed_and_history_is_recorded(): void
    {
        $user = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $user->id,
            'title' => 'Ticket',
            'description' => 'Test',
            'priority' => TicketPriority::Medium,
        ]);

        $this->service->changePriority(
            $ticket,
            TicketPriority::High,
            $user
        );

        $ticket->refresh();

        $this->assertSame(TicketPriority::High, $ticket->priority);

        $this->assertDatabaseHas('ticket_histories', [
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'action' => 'priority_changed',
        ]);
    }

    public function test_same_priority_does_not_create_history(): void
    {
        $user = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $user->id,
            'title' => 'Ticket',
            'description' => 'Test',
            'priority' => TicketPriority::Medium,
        ]);

        $this->service->changePriority(
            $ticket,
            TicketPriority::Medium,
            $user
        );

        $this->assertDatabaseCount('ticket_histories', 0);
    }

    public function test_ticket_has_default_status_and_priority_immediately_after_creation(): void
    {
        $user = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $user->id,
            'title' => 'Default values test',
            'description' => 'Test',
        ]);

        $this->assertSame(TicketStatus::New, $ticket->status);
        $this->assertSame(TicketPriority::Medium, $ticket->priority);
    }

    public function test_ticket_can_be_assigned_to_agent_and_history_is_recorded(): void
    {
        $user = User::factory()->create();
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $ticket = Ticket::create([
            'created_by_id' => $user->id,
            'title' => 'Ticket',
            'description' => 'Test',
        ]);

        $this->service->assign($ticket, $agent, $user);

        $ticket->refresh();

        $this->assertSame($agent->id, $ticket->assigned_to_id);

        $this->assertDatabaseHas('ticket_histories', [
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'action' => 'assignee_changed',
        ]);
    }

    public function test_ticket_can_be_reassigned_to_another_agent(): void
    {
        $user = User::factory()->create();

        $firstAgent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $secondAgent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $ticket = Ticket::create([
            'created_by_id' => $user->id,
            'assigned_to_id' => $firstAgent->id,
            'title' => 'Ticket',
            'description' => 'Test',
        ]);

        $this->service->assign($ticket, $secondAgent, $user);

        $ticket->refresh();

        $this->assertSame($secondAgent->id, $ticket->assigned_to_id);

        $history = $ticket->history()->latest('id')->first();

        $this->assertSame(
            $firstAgent->id,
            $history->old_values['assigned_to_id']
        );

        $this->assertSame(
            $secondAgent->id,
            $history->new_values['assigned_to_id']
        );
    }

    public function test_ticket_can_be_unassigned(): void
    {
        $user = User::factory()->create();

        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $ticket = Ticket::create([
            'created_by_id' => $user->id,
            'assigned_to_id' => $agent->id,
            'title' => 'Ticket',
            'description' => 'Test',
        ]);

        $this->service->assign($ticket, null, $user);

        $ticket->refresh();

        $this->assertNull($ticket->assigned_to_id);

        $history = $ticket->history()->latest('id')->first();

        $this->assertSame(
            $agent->id,
            $history->old_values['assigned_to_id']
        );

        $this->assertNull(
            $history->new_values['assigned_to_id']
        );
    }

    public function test_priority_change_is_rolled_back_when_history_creation_fails(): void
    {
        $creator = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $creator->id,
            'title' => 'Ticket',
            'description' => 'Test',
            'priority' => TicketPriority::Medium,
        ]);

        $invalidUser = new User;
        $invalidUser->id = 999999;

        try {
            $this->service->changePriority(
                $ticket,
                TicketPriority::High,
                $invalidUser
            );

            $this->fail('Expected history creation to fail.');
        } catch (QueryException) {
            // Expected: ticket_histories.user_id violates the foreign key.
        }

        $ticket->refresh();

        $this->assertSame(
            TicketPriority::Medium,
            $ticket->priority
        );

        $this->assertDatabaseMissing('ticket_histories', [
            'ticket_id' => $ticket->id,
            'action' => 'priority_changed',
        ]);
    }
}
