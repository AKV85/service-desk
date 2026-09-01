<?php

namespace App\Http\Controllers\Api;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignTicketRequest;
use App\Http\Requests\ChangeTicketPriorityRequest;
use App\Http\Requests\ChangeTicketStatusRequest;
use App\Http\Requests\StoreTicketCommentRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketCreationService;
use App\Services\TicketNotificationService;
use App\Services\TicketWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Ticket::query()
            ->with(['creator', 'assignee'])
            ->latest();

        if ($user->role === UserRole::Requester) {
            $query->where('created_by_id', $user->id);
        }

        $tickets = $query->paginate(15);

        return response()->json($tickets);
    }

    public function show(
        Request $request,
        Ticket $ticket
    ): JsonResponse {
        $this->authorize('view', $ticket);

        $ticket->load([
            'creator',
            'assignee',
            'comments.user',
            'history.user',
            'attachments.user',
        ]);

        return response()->json([
            'data' => $ticket,
        ]);
    }

    public function store(
        StoreTicketRequest $request,
        TicketCreationService $ticketCreationService
    ): JsonResponse {
        $ticket = $ticketCreationService->create(
            creator: $request->user(),
            title: $request->validated('title'),
            description: $request->validated('description'),
        );

        return response()->json([
            'data' => $ticket,
        ], 201);
    }

    public function update(
        UpdateTicketRequest $request,
        Ticket $ticket
    ): JsonResponse {
        $ticket->update([
            'title' => $request->validated('title'),
            'description' => $request->validated('description'),
        ]);

        return response()->json([
            'data' => $ticket->fresh(),
        ]);
    }

    public function updateStatus(
        ChangeTicketStatusRequest $request,
        Ticket $ticket,
        TicketWorkflowService $workflowService
    ): JsonResponse {
        $workflowService->changeStatus(
            $ticket,
            TicketStatus::from($request->validated('status')),
            $request->user()
        );

        return response()->json([
            'data' => $ticket->fresh(),
        ]);
    }

    public function updatePriority(
        ChangeTicketPriorityRequest $request,
        Ticket $ticket,
        TicketWorkflowService $workflowService
    ): JsonResponse {
        $workflowService->changePriority(
            $ticket,
            TicketPriority::from($request->validated('priority')),
            $request->user()
        );

        return response()->json([
            'data' => $ticket->fresh(),
        ]);
    }

    public function assign(
        AssignTicketRequest $request,
        Ticket $ticket,
        TicketWorkflowService $workflowService
    ): JsonResponse {
        $assigneeId = $request->validated('assigned_to_id');

        $assignee = $assigneeId !== null
            ? User::findOrFail($assigneeId)
            : null;

        $workflowService->assign(
            $ticket,
            $assignee,
            $request->user()
        );

        return response()->json([
            'data' => $ticket->fresh(),
        ]);
    }

    public function storeComment(
        StoreTicketCommentRequest $request,
        Ticket $ticket,
        TicketNotificationService $notificationService
    ): JsonResponse {
        $comment = $ticket->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        $notificationService->commentAdded(
            $ticket,
            $comment,
            $request->user()
        );

        return response()->json([
            'data' => $comment->load('user'),
        ], 201);
    }
}
