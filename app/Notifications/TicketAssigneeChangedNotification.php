<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketAssigneeChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Ticket $ticket,
        private readonly User $assignee
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
            ->subject('Ticket assignment updated: '.$this->ticket->title)
            ->greeting('Hello '.$notifiable->name.',')
            ->line(
                'Your ticket has been assigned to '.$this->assignee->name.'.'
            )
            ->line('Ticket: '.$this->ticket->title)
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
            'assignee_id' => $this->assignee->id,
            'assignee_name' => $this->assignee->name,
        ];
    }
}
