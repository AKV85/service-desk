<?php

namespace Tests\Feature\Tickets;

use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_ticket_edit_page(): void
    {
        $user = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $user->id,
            'title' => 'Ticket',
            'description' => 'Test',
        ]);

        $response = $this->get(route('tickets.edit', $ticket));

        $response->assertRedirect('/login');
    }

    public function test_requester_can_edit_own_ticket(): void
    {
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Old title',
            'description' => 'Old description',
        ]);

        $response = $this
            ->actingAs($requester)
            ->get(route('tickets.edit', $ticket));

        $response->assertOk();
        $response->assertSee('Old title');
    }

    public function test_requester_cannot_edit_another_users_ticket(): void
    {
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $anotherUser = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $anotherUser->id,
            'title' => 'Private ticket',
            'description' => 'Test',
        ]);

        $response = $this
            ->actingAs($requester)
            ->get(route('tickets.edit', $ticket));

        $response->assertForbidden();
    }

    public function test_agent_can_edit_any_ticket(): void
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
            ->get(route('tickets.edit', $ticket));

        $response->assertOk();
    }

    public function test_admin_can_edit_any_ticket(): void
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
            ->get(route('tickets.edit', $ticket));

        $response->assertOk();
    }

    public function test_requester_can_update_own_ticket(): void
    {
        $requester = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Old title',
            'description' => 'Old description',
        ]);

        $response = $this
            ->actingAs($requester)
            ->put(route('tickets.update', $ticket), [
                'title' => 'New title',
                'description' => 'New description',
            ]);

        $response->assertRedirect(route('tickets.show', $ticket));

        $ticket->refresh();

        $this->assertSame('New title', $ticket->title);
        $this->assertSame('New description', $ticket->description);
    }

    public function test_requester_cannot_update_another_users_ticket(): void
    {
        $requester = User::factory()->create();
        $anotherUser = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $anotherUser->id,
            'title' => 'Original title',
            'description' => 'Original description',
        ]);

        $response = $this
            ->actingAs($requester)
            ->put(route('tickets.update', $ticket), [
                'title' => 'Changed title',
                'description' => 'Changed description',
            ]);

        $response->assertForbidden();

        $ticket->refresh();

        $this->assertSame('Original title', $ticket->title);
        $this->assertSame('Original description', $ticket->description);
    }

    public function test_title_is_required(): void
    {
        $requester = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Original title',
            'description' => 'Original description',
        ]);

        $response = $this
            ->actingAs($requester)
            ->put(route('tickets.update', $ticket), [
                'description' => 'Updated description',
            ]);

        $response->assertSessionHasErrors('title');

        $ticket->refresh();

        $this->assertSame('Original title', $ticket->title);
    }

    public function test_description_is_required(): void
    {
        $requester = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Original title',
            'description' => 'Original description',
        ]);

        $response = $this
            ->actingAs($requester)
            ->put(route('tickets.update', $ticket), [
                'title' => 'Updated title',
            ]);

        $response->assertSessionHasErrors('description');

        $ticket->refresh();

        $this->assertSame('Original description', $ticket->description);
    }

    public function test_title_cannot_exceed_255_characters(): void
    {
        $requester = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Original title',
            'description' => 'Original description',
        ]);

        $response = $this
            ->actingAs($requester)
            ->put(route('tickets.update', $ticket), [
                'title' => str_repeat('a', 256),
                'description' => 'Updated description',
            ]);

        $response->assertSessionHasErrors('title');

        $ticket->refresh();

        $this->assertSame('Original title', $ticket->title);
    }
}
