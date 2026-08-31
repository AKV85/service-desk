<?php

namespace App\Services;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use App\Notifications\TicketAssignedNotification;
use App\Notifications\TicketStatusChangedNotification;
use App\Notifications\TicketPriorityChangedNotification;

class TicketWorkflowService
{
    public function __construct(
        private readonly TicketStatusTransitionService $transitionService
    ) {}

    public function changeStatus(
        Ticket $ticket,
        TicketStatus $newStatus,
        User $user
    ): void {
        $oldStatus = $ticket->status;

        if (! $this->transitionService->canTransition($oldStatus, $newStatus)) {
            throw ValidationException::withMessages([
                'status' => 'Invalid ticket status transition.',
            ]);
        }

        $ticket->status = $newStatus;

        if ($newStatus === TicketStatus::Resolved) {
            $ticket->resolved_at = now();
        }

        if (
            $oldStatus === TicketStatus::Resolved
            && $newStatus === TicketStatus::InProgress
        ) {
            $ticket->resolved_at = null;
        }

        if ($newStatus === TicketStatus::Closed) {
            $ticket->closed_at = now();
        }

        $ticket->save();

        $ticket->history()->create([
            'user_id' => $user->id,
            'action' => 'status_changed',
            'old_values' => [
                'status' => $oldStatus->value,
            ],
            'new_values' => [
                'status' => $newStatus->value,
            ],
        ]);

        if ($ticket->created_by_id !== $user->id) {
            $ticket->creator->notify(
                new TicketStatusChangedNotification(
                    $ticket,
                    $oldStatus,
                    $newStatus
                )
            );
        }
    }

    public function changePriority(
        Ticket $ticket,
        TicketPriority $newPriority,
        User $user
    ): void {
        $oldPriority = $ticket->priority;

        if ($oldPriority === $newPriority) {
            return;
        }

        $ticket->priority = $newPriority;
        $ticket->save();

        $ticket->history()->create([
            'user_id' => $user->id,
            'action' => 'priority_changed',
            'old_values' => [
                'priority' => $oldPriority->value,
            ],
            'new_values' => [
                'priority' => $newPriority->value,
            ],
        ]);

        if ($ticket->created_by_id !== $user->id) {
            $ticket->creator->notify(
                new TicketPriorityChangedNotification(
                    $ticket,
                    $oldPriority,
                    $newPriority
                )
            );
        }
    }

    public function assign(
        Ticket $ticket,
        ?User $assignee,
        User $user
    ): void {
        $oldAssigneeId = $ticket->assigned_to_id;
        $newAssigneeId = $assignee?->id;

        if ($oldAssigneeId === $newAssigneeId) {
            return;
        }

        $ticket->assigned_to_id = $newAssigneeId;
        $ticket->save();

        $ticket->history()->create([
            'user_id' => $user->id,
            'action' => 'assignee_changed',
            'old_values' => [
                'assigned_to_id' => $oldAssigneeId,
            ],
            'new_values' => [
                'assigned_to_id' => $newAssigneeId,
            ],
        ]);

        if ($assignee !== null) {
            $assignee->notify(
                new TicketAssignedNotification($ticket)
            );
        }
    }
}
