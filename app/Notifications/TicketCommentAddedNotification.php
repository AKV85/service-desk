<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Models\TicketComment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketCommentAddedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Ticket $ticket,
        private readonly TicketComment $comment
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
            ->subject('New comment: '.$this->ticket->title)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('A new comment has been added to a ticket.')
            ->line('Ticket: '.$this->ticket->title)
            ->line('Comment by: '.$this->comment->user->name)
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
            'comment_id' => $this->comment->id,
            'title' => $this->ticket->title,
        ];
    }
}
