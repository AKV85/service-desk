<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Models\User;

class TicketHistoryService
{
    public function commentAdded(
        Ticket $ticket,
        TicketComment $comment,
        User $actor
    ): void {
        $ticket->history()->create([
            'user_id' => $actor->id,
            'action' => 'comment_added',
            'old_values' => null,
            'new_values' => [
                'comment_id' => $comment->id,
            ],
        ]);
    }

    public function attachmentAdded(
        Ticket $ticket,
        TicketAttachment $attachment,
        User $actor
    ): void {
        $ticket->history()->create([
            'user_id' => $actor->id,
            'action' => 'attachment_added',
            'old_values' => null,
            'new_values' => [
                'attachment_id' => $attachment->id,
                'original_name' => $attachment->original_name,
            ],
        ]);
    }
}
