<?php

namespace App\Notifications;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Ticket $ticket,
        private readonly TicketStatus $oldStatus,
        private readonly TicketStatus $newStatus
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Ticket status changed: ' . $this->ticket->title)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('The status of your ticket has changed.')
            ->line('Ticket: ' . $this->ticket->title)
            ->line(
                'Status: '
                    . $this->oldStatus->value
                    . ' → '
                    . $this->newStatus->value
            )
            ->action(
                'View ticket',
                route('tickets.show', $this->ticket)
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'title' => $this->ticket->title,
            'old_status' => $this->oldStatus->value,
            'new_status' => $this->newStatus->value,
        ];
    }
}
