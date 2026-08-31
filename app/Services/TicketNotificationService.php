<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Models\User;
use App\Notifications\TicketAttachmentAddedNotification;
use App\Notifications\TicketCommentAddedNotification;
use Illuminate\Support\Collection;

class TicketNotificationService
{
    public function commentAdded(
        Ticket $ticket,
        TicketComment $comment,
        User $actor
    ): void {
        foreach ($this->recipients($ticket, $actor) as $recipient) {
            $recipient->notify(
                new TicketCommentAddedNotification(
                    $ticket,
                    $comment
                )
            );
        }
    }

    public function attachmentAdded(
        Ticket $ticket,
        TicketAttachment $attachment,
        User $actor
    ): void {
        foreach ($this->recipients($ticket, $actor) as $recipient) {
            $recipient->notify(
                new TicketAttachmentAddedNotification(
                    $ticket,
                    $attachment
                )
            );
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function recipients(
        Ticket $ticket,
        User $actor
    ): Collection {
        return collect([
            $ticket->creator,
            $ticket->assignee,
        ])
            ->filter()
            ->reject(fn(User $user) => $user->id === $actor->id)
            ->unique('id')
            ->values();
    }
}
