<?php

namespace App\Services;

use App\Jobs\CreateJiraIssueJob;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketCreatedNotification;
use Illuminate\Support\Facades\DB;

class TicketCreationService
{
    public function create(
        User $creator,
        string $title,
        string $description,
    ): Ticket {
        $ticket = DB::transaction(function () use ($creator, $title, $description): Ticket {
            return Ticket::create([
                'created_by_id' => $creator->id,
                'title' => $title,
                'description' => $description,
            ]);
        });

        $creator->notify(
            new TicketCreatedNotification($ticket)
        );

        if (config('integrations.jira.enabled')) {
            CreateJiraIssueJob::dispatch($ticket->id)->afterCommit();
        }

        return $ticket;
    }
}
