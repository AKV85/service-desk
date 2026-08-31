<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketAttachmentAddedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Ticket $ticket,
        private readonly TicketAttachment $attachment
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
            ->subject('New attachment: ' . $this->ticket->title)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('A new attachment has been added to a ticket.')
            ->line('Ticket: ' . $this->ticket->title)
            ->line('File: ' . $this->attachment->original_name)
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
            'attachment_id' => $this->attachment->id,
            'title' => $this->ticket->title,
            'original_name' => $this->attachment->original_name,
        ];
    }
}
