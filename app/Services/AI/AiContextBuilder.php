<?php

namespace App\Services\AI;

use App\Data\AI\AiAttachmentContextData;
use App\Data\AI\AiCommentContextData;
use App\Data\AI\AiContextData;
use App\Data\AI\AiGitHubContextData;
use App\Data\AI\AiHistoryContextData;
use App\Data\AI\AiJiraContextData;
use App\Data\AI\AiTicketContextData;
use App\Data\AI\AiUserContextData;
use App\Models\Ticket;
use App\Models\User;

class AiContextBuilder
{
    public function build(Ticket $ticket): AiContextData
    {
        $ticket->loadMissing([
            'creator',
            'assignee',
            'comments' => fn ($query) => $query
                ->orderBy('created_at')
                ->orderBy('id')
                ->with('user'),
            'history' => fn ($query) => $query
                ->orderBy('created_at')
                ->orderBy('id')
                ->with('user'),
            'attachments' => fn ($query) => $query
                ->orderBy('created_at')
                ->orderBy('id')
                ->with('user'),
            'jiraIssue',
            'githubResources' => fn ($query) => $query
                ->orderBy('created_at')
                ->orderBy('id'),
        ]);

        return new AiContextData(
            ticket: new AiTicketContextData(
                id: $ticket->id,
                title: $ticket->title,
                description: $ticket->description,
                status: $ticket->status->value,
                priority: $ticket->priority->value,
                creator: $this->user($ticket->creator),
                assignee: $ticket->assignee !== null
                    ? $this->user($ticket->assignee)
                    : null,
                createdAt: $ticket->created_at?->toISOString(),
                updatedAt: $ticket->updated_at?->toISOString(),
                resolvedAt: $ticket->resolved_at?->toISOString(),
                closedAt: $ticket->closed_at?->toISOString(),
            ),
            comments: $ticket->comments
                ->map(fn ($comment) => new AiCommentContextData(
                    id: $comment->id,
                    body: $comment->body,
                    author: $this->user($comment->user),
                    createdAt: $comment->created_at?->toISOString(),
                ))
                ->values()
                ->all(),
            history: $ticket->history
                ->map(fn ($history) => new AiHistoryContextData(
                    id: $history->id,
                    action: $history->action,
                    user: $history->user !== null
                        ? $this->user($history->user)
                        : null,
                    oldValues: $history->old_values,
                    newValues: $history->new_values,
                    createdAt: $history->created_at?->toISOString(),
                ))
                ->values()
                ->all(),
            attachments: $ticket->attachments
                ->map(fn ($attachment) => new AiAttachmentContextData(
                    id: $attachment->id,
                    originalName: $attachment->original_name,
                    mimeType: $attachment->mime_type,
                    size: $attachment->size,
                    uploadedBy: $this->user($attachment->user),
                    createdAt: $attachment->created_at?->toISOString(),
                ))
                ->values()
                ->all(),
            jiraIssue: $ticket->jiraIssue !== null
                ? new AiJiraContextData(
                    externalId: $ticket->jiraIssue->external_id,
                    issueKey: $ticket->jiraIssue->issue_key,
                    url: $ticket->jiraIssue->url,
                    status: $ticket->jiraIssue->external_status,
                    externalUpdatedAt: $ticket->jiraIssue
                        ->external_updated_at?->toISOString(),
                    lastSyncedAt: $ticket->jiraIssue
                        ->last_synced_at?->toISOString(),
                )
                : null,
            githubResources: $ticket->githubResources
                ->map(fn ($resource) => new AiGitHubContextData(
                    type: $resource->resource_type->value,
                    externalId: $resource->external_id,
                    repository: $resource->repository,
                    resourceNumber: $resource->resource_number,
                    reference: $resource->reference,
                    url: $resource->url,
                    state: $resource->external_state,
                    externalUpdatedAt: $resource
                        ->external_updated_at?->toISOString(),
                    lastSyncedAt: $resource
                        ->last_synced_at?->toISOString(),
                ))
                ->values()
                ->all(),
        );
    }

    private function user(User $user): AiUserContextData
    {
        return new AiUserContextData(
            id: $user->id,
            name: $user->name,
            role: $user->role?->value,
        );
    }
}
