<?php

namespace Tests\Feature\Notifications;

use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketCreatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketCreatedNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_created_notification_is_queued(): void
    {
        $user = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $user->id,
            'title' => 'Printer is not working',
            'description' => 'Office printer cannot print.',
        ]);

        $notification = new TicketCreatedNotification($ticket);

        $this->assertInstanceOf(
            ShouldQueue::class,
            $notification
        );
    }

    public function test_ticket_created_notification_contains_ticket_information(): void
    {
        $user = User::factory()->create([
            'name' => 'Andrej',
        ]);

        $ticket = Ticket::create([
            'created_by_id' => $user->id,
            'title' => 'Printer is not working',
            'description' => 'Office printer cannot print.',
        ]);

        $notification = new TicketCreatedNotification($ticket);

        $mail = $notification->toMail($user);

        $this->assertSame(
            'Ticket created: Printer is not working',
            $mail->subject
        );

        $this->assertSame(
            'Hello Andrej,',
            $mail->greeting
        );

        $this->assertContains(
            'Your ticket has been created successfully.',
            $mail->introLines
        );

        $this->assertContains(
            'Ticket: Printer is not working',
            $mail->introLines
        );

        $this->assertSame(
            'View ticket',
            $mail->actionText
        );

        $this->assertSame(
            route('tickets.show', $ticket),
            $mail->actionUrl
        );
    }

    public function test_ticket_created_notification_array_contains_ticket_data(): void
    {
        $user = User::factory()->create();

        $ticket = Ticket::create([
            'created_by_id' => $user->id,
            'title' => 'Printer is not working',
            'description' => 'Office printer cannot print.',
        ]);

        $notification = new TicketCreatedNotification($ticket);

        $this->assertSame([
            'ticket_id' => $ticket->id,
            'title' => 'Printer is not working',
        ], $notification->toArray($user));
    }
}
