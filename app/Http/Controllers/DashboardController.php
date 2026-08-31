<?php

namespace App\Http\Controllers;

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Ticket;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = request()->user();

        $query = Ticket::query();

        if ($user->role === UserRole::Requester) {
            $query->where('created_by_id', $user->id);
        }

        $statusCounts = (clone $query)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $counts = [
            'new' => (int) ($statusCounts[TicketStatus::New->value] ?? 0),
            'in_progress' => (int) ($statusCounts[TicketStatus::InProgress->value] ?? 0),
            'resolved' => (int) ($statusCounts[TicketStatus::Resolved->value] ?? 0),
            'closed' => (int) ($statusCounts[TicketStatus::Closed->value] ?? 0),
        ];

        $recentTickets = (clone $query)
            ->with(['creator', 'assignee'])
            ->latest()
            ->limit(5)
            ->get();

        $assignedToMe = collect();
        $unassignedCount = null;

        if (in_array($user->role, [
            UserRole::Agent,
            UserRole::Admin,
        ], true)) {
            $assignedToMe = Ticket::query()
                ->where('assigned_to_id', $user->id)
                ->latest()
                ->limit(5)
                ->get();

            $unassignedCount = Ticket::query()
                ->whereNull('assigned_to_id')
                ->count();
        }

        return view('dashboard', compact(
            'counts',
            'recentTickets',
            'assignedToMe',
            'unassignedCount'
        ));
    }
}
