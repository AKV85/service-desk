<?php

namespace Tests\Feature\Tickets;

use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Notifications\TicketCommentAddedNotification;
use Illuminate\Support\Facades\Notification;

class TicketCommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_add_comment(): void
    {
        $user = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $user->id,
            'title' => 'Ticket',
            'description' => 'Test',
        ]);

        $response = $this->post(
            route('tickets.comments.store', $ticket),
            ['body' => 'Comment']
        );

        $response->assertRedirect('/login');

        $this->assertDatabaseCount('ticket_comments', 0);
    }

    public function test_requester_can_comment_on_own_ticket(): void
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
            ->post(route('tickets.comments.store', $ticket), [
                'body' => 'Requester comment',
            ]);

        $response->assertRedirect(route('tickets.show', $ticket));

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'user_id' => $requester->id,
            'body' => 'Requester comment',
        ]);
    }

    public function test_requester_cannot_comment_on_another_users_ticket(): void
    {
        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $anotherUser = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $anotherUser->id,
            'title' => 'Ticket',
            'description' => 'Test',
        ]);

        $response = $this
            ->actingAs($requester)
            ->post(route('tickets.comments.store', $ticket), [
                'body' => 'Forbidden comment',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseCount('ticket_comments', 0);
    }

    public function test_agent_can_comment_on_any_ticket(): void
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
            ->post(route('tickets.comments.store', $ticket), [
                'body' => 'Agent comment',
            ]);

        $response->assertRedirect(route('tickets.show', $ticket));

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'user_id' => $agent->id,
            'body' => 'Agent comment',
        ]);

        Notification::assertSentTo(
            $requester,
            TicketCommentAddedNotification::class,
            function (TicketCommentAddedNotification $notification) use ($ticket, $requester) {
                $data = $notification->toArray($requester);

                return $data['ticket_id'] === $ticket->id
                    && $data['title'] === $ticket->title;
            }
        );

        Notification::assertNotSentTo(
            $agent,
            TicketCommentAddedNotification::class
        );
    }

    public function test_admin_can_comment_on_any_ticket(): void
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
            ->post(route('tickets.comments.store', $ticket), [
                'body' => 'Admin comment',
            ]);

        $response->assertRedirect(route('tickets.show', $ticket));

        $this->assertDatabaseHas('ticket_comments', [
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'body' => 'Admin comment',
        ]);
    }

    public function test_comment_body_is_required(): void
    {
        $requester = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Ticket',
            'description' => 'Test',
        ]);

        $response = $this
            ->actingAs($requester)
            ->post(route('tickets.comments.store', $ticket), []);

        $response->assertSessionHasErrors('body');

        $this->assertDatabaseCount('ticket_comments', 0);
    }

    public function test_comment_body_cannot_exceed_5000_characters(): void
    {
        $requester = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Ticket',
            'description' => 'Test',
        ]);

        $response = $this
            ->actingAs($requester)
            ->post(route('tickets.comments.store', $ticket), [
                'body' => str_repeat('a', 5001),
            ]);

        $response->assertSessionHasErrors('body');

        $this->assertDatabaseCount('ticket_comments', 0);
    }

    public function test_comments_are_displayed_oldest_first_with_author_and_date(): void
    {
        $requester = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'title' => 'Ticket',
            'description' => 'Test',
        ]);

        $firstComment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $requester->id,
            'body' => 'First comment',
        ]);

        $firstComment->created_at = now()->subHour();
        $firstComment->save();

        $secondComment = TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $requester->id,
            'body' => 'Second comment',
        ]);

        $secondComment->created_at = now();
        $secondComment->save();

        $response = $this
            ->actingAs($requester)
            ->get(route('tickets.show', $ticket));

        $response->assertOk();

        $response->assertSeeInOrder([
            'First comment',
            'Second comment',
        ]);

        $response->assertSee($requester->name);
        $response->assertSee(
            $firstComment->created_at->format('Y-m-d H:i')
        );
    }

    public function test_requester_comment_notifies_assigned_agent_but_not_requester(): void
    {
        Notification::fake();

        $requester = User::factory()->create([
            'role' => UserRole::Requester,
        ]);

        $agent = User::factory()->create([
            'role' => UserRole::Agent,
        ]);

        $ticket = Ticket::create([
            'created_by_id' => $requester->id,
            'assigned_to_id' => $agent->id,
            'title' => 'Ticket',
            'description' => 'Test',
        ]);

        $response = $this
            ->actingAs($requester)
            ->post(route('tickets.comments.store', $ticket), [
                'body' => 'Requester comment',
            ]);

        $response->assertRedirect(route('tickets.show', $ticket));

        $comment = TicketComment::query()->firstOrFail();

        Notification::assertSentTo(
            $agent,
            TicketCommentAddedNotification::class,
            function (TicketCommentAddedNotification $notification) use ($ticket, $comment, $agent) {
                $data = $notification->toArray($agent);

                return $data['ticket_id'] === $ticket->id
                    && $data['comment_id'] === $comment->id;
            }
        );

        Notification::assertNotSentTo(
            $requester,
            TicketCommentAddedNotification::class
        );
    }
}
