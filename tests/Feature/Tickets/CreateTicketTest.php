<?php

namespace Tests\Feature\Tickets;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateTicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_ticket_creation_page(): void
    {
        $response = $this->get('/tickets/create');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_ticket_creation_page(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/tickets/create');

        $response->assertOk();
    }

    public function test_authenticated_user_can_create_ticket(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post('/tickets', [
                'title' => 'Printer is not working',
                'description' => 'The office printer shows an error.',
            ]);

        $ticket = Ticket::query()->firstOrFail();

        $this->assertSame($user->id, $ticket->created_by_id);
        $this->assertSame('Printer is not working', $ticket->title);
        $this->assertSame('The office printer shows an error.', $ticket->description);
        $this->assertSame(TicketStatus::New, $ticket->status);
        $this->assertSame(TicketPriority::Medium, $ticket->priority);

        $response->assertRedirect(route('tickets.show', $ticket));
    }

    public function test_title_is_required(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post('/tickets', [
                'description' => 'Description',
            ]);

        $response->assertSessionHasErrors('title');

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_description_is_required(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post('/tickets', [
                'title' => 'Title',
            ]);

        $response->assertSessionHasErrors('description');

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_title_cannot_exceed_255_characters(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post('/tickets', [
                'title' => str_repeat('a', 256),
                'description' => 'Description',
            ]);

        $response->assertSessionHasErrors('title');

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_requester_can_view_own_ticket(): void
    {
        $user = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $user->id,
            'title' => 'Own ticket',
            'description' => 'Test',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('tickets.show', $ticket));

        $response->assertOk();
        $response->assertSee('Own ticket');
    }

    public function test_requester_cannot_view_another_users_ticket(): void
    {
        $user = User::factory()->create();
        $anotherUser = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $anotherUser->id,
            'title' => 'Private ticket',
            'description' => 'Test',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('tickets.show', $ticket));

        $response->assertForbidden();
    }
}
