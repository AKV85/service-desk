<?php

namespace App\Http\Controllers;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Http\Requests\AssignTicketRequest;
use App\Http\Requests\ChangeTicketPriorityRequest;
use App\Http\Requests\ChangeTicketStatusRequest;
use App\Http\Requests\StoreTicketCommentRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\TicketIndexRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketCreationService;
use App\Services\TicketHistoryService;
use App\Services\TicketNotificationService;
use App\Services\TicketWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TicketController extends Controller
{
    public function create(): View
    {
        return view('tickets.create');
    }

    public function store(
        StoreTicketRequest $request,
        TicketCreationService $ticketCreationService
    ): RedirectResponse {
        $ticket = $ticketCreationService->create(
            creator: $request->user(),
            title: $request->validated('title'),
            description: $request->validated('description'),
        );

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('success', 'Ticket created successfully.');
    }

    public function show(Ticket $ticket): View
    {
        $this->authorize('view', $ticket);

        $ticket->load([
            'creator',
            'assignee',

            'comments' => fn ($query) => $query
                ->with('user')
                ->oldest(),

            'history' => fn ($query) => $query
                ->with('user')
                ->latest(),

            'attachments' => fn ($query) => $query
                ->with('user')
                ->latest(),
        ]);

        $legacyAssigneeIds = $ticket->history
            ->where('action', 'assignee_changed')
            ->flatMap(function ($history) {
                $ids = [];

                if (
                    ! array_key_exists('assigned_to_name', $history->old_values ?? [])
                    && isset($history->old_values['assigned_to_id'])
                ) {
                    $ids[] = $history->old_values['assigned_to_id'];
                }

                if (
                    ! array_key_exists('assigned_to_name', $history->new_values ?? [])
                    && isset($history->new_values['assigned_to_id'])
                ) {
                    $ids[] = $history->new_values['assigned_to_id'];
                }

                return $ids;
            })
            ->unique()
            ->values();

        $legacyAssigneeNames = User::query()
            ->whereIn('id', $legacyAssigneeIds)
            ->pluck('name', 'id');

        $ticket->history->each(function ($history) use ($legacyAssigneeNames): void {
            if ($history->action !== 'assignee_changed') {
                return;
            }

            $oldValues = $history->old_values ?? [];
            $newValues = $history->new_values ?? [];

            if (
                ! array_key_exists('assigned_to_name', $oldValues)
                && isset($oldValues['assigned_to_id'])
            ) {
                $oldValues['assigned_to_name'] = $legacyAssigneeNames->get(
                    $oldValues['assigned_to_id'],
                    'Deleted user #'.$oldValues['assigned_to_id']
                );
            }

            if (
                ! array_key_exists('assigned_to_name', $newValues)
                && isset($newValues['assigned_to_id'])
            ) {
                $newValues['assigned_to_name'] = $legacyAssigneeNames->get(
                    $newValues['assigned_to_id'],
                    'Deleted user #'.$newValues['assigned_to_id']
                );
            }

            $history->old_values = $oldValues;
            $history->new_values = $newValues;
        });

        $agents = collect();

        if (request()->user()->can('assign', $ticket)) {
            $agents = User::query()
                ->where('role', UserRole::Agent)
                ->orderBy('name')
                ->get();
        }

        return view('tickets.show', compact('ticket', 'agents'));
    }

    public function index(TicketIndexRequest $request): View
    {
        $user = $request->user();

        $query = Ticket::query()
            ->with(['creator', 'assignee'])
            ->latest();

        if ($user->role === UserRole::Requester) {
            $query->where('created_by_id', $user->id);
        }

        if ($search = $request->validated('search')) {
            $query->where('title', 'like', "%{$search}%");
        }

        if ($status = $request->validated('status')) {
            $query->where('status', $status);
        }

        if ($priority = $request->validated('priority')) {
            $query->where('priority', $priority);
        }

        if (
            $user->role !== UserRole::Requester
            && ($assignee = $request->validated('assignee'))
        ) {
            if ($assignee === 'unassigned') {
                $query->whereNull('assigned_to_id');
            } elseif (ctype_digit($assignee)) {
                $query->where('assigned_to_id', (int) $assignee);
            }
        }

        $tickets = $query
            ->paginate(15)
            ->withQueryString();

        $agents = $user->role === UserRole::Requester
            ? collect()
            : User::query()
                ->where('role', UserRole::Agent)
                ->orderBy('name')
                ->get();

        return view('tickets.index', compact('tickets', 'agents'));
    }

    public function edit(Ticket $ticket): View
    {
        $this->authorize('update', $ticket);

        return view('tickets.edit', compact('ticket'));
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket): RedirectResponse
    {
        $ticket->update([
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
        ]);

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('success', 'Ticket updated successfully.');
    }

    public function updateStatus(
        ChangeTicketStatusRequest $request,
        Ticket $ticket,
        TicketWorkflowService $workflowService
    ): RedirectResponse {
        $workflowService->changeStatus(
            $ticket,
            TicketStatus::from($request->validated('status')),
            $request->user()
        );

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('success', 'Ticket status updated successfully.');
    }

    public function updatePriority(
        ChangeTicketPriorityRequest $request,
        Ticket $ticket,
        TicketWorkflowService $workflowService
    ): RedirectResponse {
        $workflowService->changePriority(
            $ticket,
            TicketPriority::from($request->validated('priority')),
            $request->user()
        );

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('success', 'Ticket priority updated successfully.');
    }

    public function assign(
        AssignTicketRequest $request,
        Ticket $ticket,
        TicketWorkflowService $workflowService
    ): RedirectResponse {
        $assigneeId = $request->validated('assigned_to_id');

        $assignee = $assigneeId !== null
            ? User::findOrFail($assigneeId)
            : null;

        $workflowService->assign(
            $ticket,
            $assignee,
            $request->user()
        );

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('success', 'Ticket assignment updated successfully.');
    }

    public function storeComment(
        StoreTicketCommentRequest $request,
        Ticket $ticket,
        TicketNotificationService $notificationService,
        TicketHistoryService $historyService
    ): RedirectResponse {
        $comment = DB::transaction(function () use (
            $request,
            $ticket,
            $historyService
        ) {
            $comment = $ticket->comments()->create([
                'user_id' => $request->user()->id,
                'body' => $request->validated('body'),
            ]);

            $historyService->commentAdded(
                $ticket,
                $comment,
                $request->user()
            );

            return $comment;
        });

        $notificationService->commentAdded(
            $ticket,
            $comment,
            $request->user()
        );

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('success', 'Comment added successfully.');
    }

    public function destroy(Ticket $ticket): RedirectResponse
    {
        $this->authorize('delete', $ticket);

        $ticket->delete();

        return redirect()
            ->route('tickets.index')
            ->with('success', 'Ticket deleted successfully.');
    }
}
