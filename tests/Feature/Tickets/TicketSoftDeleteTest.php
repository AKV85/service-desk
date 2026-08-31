<?php

namespace Tests\Feature\Tickets;

use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Models\TicketHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketSoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_soft_delete_ticket(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $requester = User::factory()->create();

        $ticket = Ticket::factory()->create([
            'created_by_id' => $requester->id,
        ]);

        $response = $this
            ->actingAs($admin)
            ->delete(route('tickets.destroy', $ticket));

        $response->assertRedirect(route('tickets.index'));

        $this->assertSoftDeleted('tickets', [
            'id' => $ticket->id,
        ]);

        $this->assertNotNull(
            Ticket::withTrashed()->findOrFail($ticket->id)->deleted_at
        );
    }

    public function test_agent_cannot_delete_ticket(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $requester = User::factory()->create();

        $ticket = Ticket::factory()->create([
            'created_by_id' => $requester->id,
        ]);

        $response = $this
            ->actingAs($agent)
            ->delete(route('tickets.destroy', $ticket));

        $response->assertForbidden();

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'deleted_at' => null,
        ]);
    }

    public function test_requester_cannot_delete_ticket(): void
    {
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $ticket = Ticket::factory()->create([
            'created_by_id' => $requester->id,
        ]);

        $response = $this
            ->actingAs($requester)
            ->delete(route('tickets.destroy', $ticket));

        $response->assertForbidden();

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'deleted_at' => null,
        ]);
    }

    public function test_soft_deleted_ticket_is_excluded_from_normal_queries(): void
    {
        $requester = User::factory()->create();

        $ticket = Ticket::factory()->create([
            'created_by_id' => $requester->id,
        ]);

        $ticket->delete();

        $this->assertNull(
            Ticket::query()->find($ticket->id)
        );

        $this->assertNotNull(
            Ticket::withTrashed()->find($ticket->id)
        );
    }

    public function test_soft_deleting_ticket_preserves_related_data(): void
    {
        $requester = User::factory()->create();

        $ticket = Ticket::factory()->create([
            'created_by_id' => $requester->id,
        ]);

        $comment = TicketComment::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $requester->id,
        ]);

        $history = TicketHistory::factory()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $requester->id,
        ]);

        $attachment = TicketAttachment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $requester->id,
            'original_name' => 'test.txt',
            'path' => 'ticket-attachments/test.txt',
            'mime_type' => 'text/plain',
            'size' => 100,
        ]);

        $ticket->delete();

        $this->assertDatabaseHas('ticket_comments', [
            'id' => $comment->id,
        ]);

        $this->assertDatabaseHas('ticket_histories', [
            'id' => $history->id,
        ]);

        $this->assertDatabaseHas('ticket_attachments', [
            'id' => $attachment->id,
        ]);
    }

    public function test_admin_sees_delete_button_on_ticket_page(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $requester = User::factory()->create();

        $ticket = Ticket::factory()->create([
            'created_by_id' => $requester->id,
        ]);

        $response = $this
            ->actingAs($admin)
            ->get(route('tickets.show', $ticket));

        $response->assertOk();
        $response->assertSee('Delete ticket');
    }

    public function test_non_admin_users_do_not_see_delete_button(): void
    {
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $ticket = Ticket::factory()->create([
            'created_by_id' => $requester->id,
        ]);

        $this
            ->actingAs($requester)
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertDontSee('Delete ticket');

        $this
            ->actingAs($agent)
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertDontSee('Delete ticket');
    }
}
