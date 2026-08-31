<?php

namespace App\Notifications;

use App\Enums\TicketPriority;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketPriorityChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Ticket $ticket,
        private readonly TicketPriority $oldPriority,
        private readonly TicketPriority $newPriority
    ) {
        $this->afterCommit();
    }

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
            ->subject('Ticket priority changed: '.$this->ticket->title)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('The priority of your ticket has changed.')
            ->line('Ticket: '.$this->ticket->title)
            ->line(
                'Priority: '
                    .$this->oldPriority->value
                    .' → '
                    .$this->newPriority->value
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
            'old_priority' => $this->oldPriority->value,
            'new_priority' => $this->newPriority->value,
        ];
    }
}
