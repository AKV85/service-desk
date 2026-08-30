<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTicketRequest;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

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
}
