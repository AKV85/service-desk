<?php

namespace Tests\Feature\Api;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketCreatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TicketApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_list_tickets(): void
    {
        $response = $this->getJson('/api/tickets');

        $response->assertUnauthorized();
    }

    public function test_requester_sees_only_own_tickets(): void
    {
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $otherRequester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $ownTicket = Ticket::factory()->create([
            'created_by_id' => $requester->id,
            'title' => 'My API ticket',
        ]);

        Ticket::factory()->create([
            'created_by_id' => $otherRequester->id,
            'title' => 'Another requester ticket',
        ]);

        $response = $this
            ->actingAs($requester)
            ->getJson('/api/tickets');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'id' => $ownTicket->id,
                'title' => 'My API ticket',
            ])
            ->assertJsonMissing([
                'title' => 'Another requester ticket',
            ]);
    }

    public function test_agent_sees_all_tickets(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $firstRequester = User::factory()->create();
        $secondRequester = User::factory()->create();

        Ticket::factory()->create([
            'created_by_id' => $firstRequester->id,
            'title' => 'First API ticket',
        ]);

        Ticket::factory()->create([
            'created_by_id' => $secondRequester->id,
            'title' => 'Second API ticket',
        ]);

        $response = $this
            ->actingAs($agent)
            ->getJson('/api/tickets');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'title' => 'First API ticket',
            ])
            ->assertJsonFragment([
                'title' => 'Second API ticket',
            ]);
    }

    public function test_requester_can_view_own_ticket(): void
    {
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $ticket = Ticket::factory()->create([
            'created_by_id' => $requester->id,
            'title' => 'Visible API ticket',
        ]);

        $response = $this
            ->actingAs($requester)
            ->getJson("/api/tickets/{$ticket->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $ticket->id)
            ->assertJsonPath('data.title', 'Visible API ticket');
    }

    public function test_requester_cannot_view_another_users_ticket(): void
    {
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $otherRequester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $ticket = Ticket::factory()->create([
            'created_by_id' => $otherRequester->id,
        ]);

        $response = $this
            ->actingAs($requester)
            ->getJson("/api/tickets/{$ticket->id}");

        $response->assertForbidden();
    }

    public function test_agent_can_view_any_ticket(): void
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
            ->getJson("/api/tickets/{$ticket->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $ticket->id);
    }

    public function test_authenticated_user_can_create_ticket_via_api(): void
    {
        Notification::fake();

        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $response = $this
            ->actingAs($requester)
            ->postJson('/api/tickets', [
                'title' => 'API printer problem',
                'description' => 'Printer does not work.',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.title', 'API printer problem')
            ->assertJsonPath('data.description', 'Printer does not work.')
            ->assertJsonPath('data.created_by_id', $requester->id);

        $ticket = Ticket::query()->firstOrFail();

        $this->assertSame($requester->id, $ticket->created_by_id);

        Notification::assertSentTo(
            $requester,
            TicketCreatedNotification::class
        );
    }

    public function test_ticket_creation_validation_errors_are_returned_as_json(): void
    {
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $response = $this
            ->actingAs($requester)
            ->postJson('/api/tickets', [
                'title' => '',
                'description' => '',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'title',
                'description',
            ]);

        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_requester_can_update_own_ticket_via_api(): void
    {
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $ticket = Ticket::factory()->create([
            'created_by_id' => $requester->id,
            'title' => 'Old title',
            'description' => 'Old description',
        ]);

        $response = $this
            ->actingAs($requester)
            ->putJson("/api/tickets/{$ticket->id}", [
                'title' => 'Updated title',
                'description' => 'Updated description',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated title')
            ->assertJsonPath('data.description', 'Updated description');

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'title' => 'Updated title',
            'description' => 'Updated description',
        ]);
    }

    public function test_requester_cannot_update_another_users_ticket_via_api(): void
    {
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $otherRequester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $ticket = Ticket::factory()->create([
            'created_by_id' => $otherRequester->id,
        ]);

        $response = $this
            ->actingAs($requester)
            ->putJson("/api/tickets/{$ticket->id}", [
                'title' => 'Forbidden update',
                'description' => 'Should not be saved',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('tickets', [
            'id' => $ticket->id,
            'title' => 'Forbidden update',
        ]);
    }

    public function test_ticket_update_validation_errors_are_returned_as_json(): void
    {
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $ticket = Ticket::factory()->create([
            'created_by_id' => $requester->id,
        ]);

        $response = $this
            ->actingAs($requester)
            ->putJson("/api/tickets/{$ticket->id}", [
                'title' => '',
                'description' => '',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'title',
                'description',
            ]);
    }

    public function test_agent_can_change_ticket_status_via_api(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $requester = User::factory()->create();

        $ticket = Ticket::factory()->create([
            'created_by_id' => $requester->id,
            'status' => TicketStatus::New,
        ]);

        $response = $this
            ->actingAs($agent)
            ->patchJson("/api/tickets/{$ticket->id}/status", [
                'status' => TicketStatus::InProgress->value,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.status', TicketStatus::InProgress->value);

        $this->assertDatabaseHas('ticket_histories', [
            'ticket_id' => $ticket->id,
            'action' => 'status_changed',
        ]);
    }

    public function test_invalid_status_transition_returns_validation_error(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $requester = User::factory()->create();

        $ticket = Ticket::factory()->create([
            'created_by_id' => $requester->id,
            'status' => TicketStatus::New,
        ]);

        $response = $this
            ->actingAs($agent)
            ->patchJson("/api/tickets/{$ticket->id}/status", [
                'status' => TicketStatus::Closed->value,
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_agent_can_change_ticket_priority_via_api(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $requester = User::factory()->create();

        $ticket = Ticket::factory()->create([
            'created_by_id' => $requester->id,
            'priority' => TicketPriority::Medium,
        ]);

        $response = $this
            ->actingAs($agent)
            ->patchJson("/api/tickets/{$ticket->id}/priority", [
                'priority' => TicketPriority::High->value,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.priority', TicketPriority::High->value);
    }

    public function test_agent_can_assign_ticket_via_api(): void
    {
        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $targetAgent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $requester = User::factory()->create();

        $ticket = Ticket::factory()->create([
            'created_by_id' => $requester->id,
        ]);

        $response = $this
            ->actingAs($agent)
            ->patchJson("/api/tickets/{$ticket->id}/assignee", [
                'assigned_to_id' => $targetAgent->id,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.assigned_to_id', $targetAgent->id);
    }

    public function test_requester_cannot_assign_ticket_via_api(): void
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

        $response = $this
            ->actingAs($requester)
            ->patchJson("/api/tickets/{$ticket->id}/assignee", [
                'assigned_to_id' => $agent->id,
            ]);

        $response->assertForbidden();
    }

    public function test_requester_can_add_comment_to_own_ticket_via_api(): void
    {
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $ticket = Ticket::factory()->create([
            'created_by_id' => $requester->id,
        ]);

        $response = $this
            ->actingAs($requester)
            ->postJson("/api/tickets/{$ticket->id}/comments", [
                'body' => 'API comment',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.body', 'API comment')
            ->assertJsonPath('data.user_id', $requester->id);

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'user_id' => $requester->id,
            'body' => 'API comment',
        ]);
    }

    public function test_requester_cannot_comment_on_another_users_ticket_via_api(): void
    {
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $otherRequester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $ticket = Ticket::factory()->create([
            'created_by_id' => $otherRequester->id,
        ]);

        $response = $this
            ->actingAs($requester)
            ->postJson("/api/tickets/{$ticket->id}/comments", [
                'body' => 'Forbidden API comment',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseCount('ticket_comments', 0);
    }

    public function test_comment_validation_errors_are_returned_as_json(): void
    {
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $ticket = Ticket::factory()->create([
            'created_by_id' => $requester->id,
        ]);

        $response = $this
            ->actingAs($requester)
            ->postJson("/api/tickets/{$ticket->id}/comments", [
                'body' => '',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');
    }
}
