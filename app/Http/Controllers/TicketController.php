<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketRequest;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use App\Enums\UserRole;

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

        return view('tickets.show', compact('ticket'));
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
}
