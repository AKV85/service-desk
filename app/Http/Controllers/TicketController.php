<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketRequest;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use App\Enums\UserRole;
use App\Http\Requests\UpdateTicketRequest;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Requests\ChangeTicketPriorityRequest;
use App\Http\Requests\ChangeTicketStatusRequest;
use App\Services\TicketWorkflowService;
use App\Http\Requests\AssignTicketRequest;
use App\Models\User;
use App\Http\Requests\StoreTicketCommentRequest;

class TicketController extends Controller
{
    public function create(): View
    {
        return view('tickets.create');
    }

    public function store(StoreTicketRequest $request): RedirectResponse
    {

        $ticket = Ticket::create([
            'created_by_id' => $request->user()->id,
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
        ]);

        return redirect()->route('tickets.show', $ticket);
    }

    public function show(Ticket $ticket): View
    {
        $this->authorize('view', $ticket);

        $ticket->load([
            'comments' => fn($query) => $query
                ->with('user')
                ->oldest(),
        ]);

        $agents = collect();

        if (request()->user()->can('assign', $ticket)) {
            $agents = User::query()
                ->where('role', UserRole::Agent)
                ->orderBy('name')
                ->get();
        }

        return view('tickets.show', compact('ticket', 'agents'));
    }

    public function index(): View
    {
        $user = request()->user();

        $query = Ticket::query()
            ->with(['creator', 'assignee'])
            ->latest();

        if ($user->role === UserRole::Requester) {
            $query->where('created_by_id', $user->id);
        }

        $tickets = $query->paginate(15);

        return view('tickets.index', compact('tickets'));
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

        return redirect()->route('tickets.show', $ticket);
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

        return redirect()->route('tickets.show', $ticket);
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

        return redirect()->route('tickets.show', $ticket);
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

        return redirect()->route('tickets.show', $ticket);
    }

    public function storeComment(
        StoreTicketCommentRequest $request,
        Ticket $ticket
    ): RedirectResponse {
        $ticket->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        return redirect()->route('tickets.show', $ticket);
    }
}
