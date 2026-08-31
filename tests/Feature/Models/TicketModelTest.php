<?php

namespace Tests\Feature\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_casts_status_and_priority_to_enums(): void
    {
        $user = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $user->id,
            'title' => 'Test ticket',
            'description' => 'Test description',
            'status' => TicketStatus::InProgress,
            'priority' => TicketPriority::High,
        ]);

        $ticket->refresh();

        $this->assertSame(TicketStatus::InProgress, $ticket->status);
        $this->assertSame(TicketPriority::High, $ticket->priority);
    }

    public function test_user_role_is_cast_to_enum(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $user->refresh();

        $this->assertSame(UserRole::Agent, $user->role);
    }

    public function test_ticket_relationships_work(): void
    {
        $creator = User::factory()->create();
        $assignee = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $ticket = Ticket::create([
            'created_by_id' => $creator->id,
            'assigned_to_id' => $assignee->id,
            'title' => 'Relationship test',
            'description' => 'Testing Eloquent relationships',
        ]);

        TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $creator->id,
            'body' => 'Test comment',
        ]);

        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id' => $assignee->id,
            'action' => 'assigned',
            'old_values' => null,
            'new_values' => [
                'assigned_to_id' => $assignee->id,
            ],
        ]);

        $this->assertTrue($ticket->creator->is($creator));
        $this->assertTrue($ticket->assignee->is($assignee));

        $this->assertCount(1, $ticket->comments);
        $this->assertCount(1, $ticket->history);

        $this->assertTrue($creator->createdTickets->contains($ticket));
        $this->assertTrue($assignee->assignedTickets->contains($ticket));
    }
}
