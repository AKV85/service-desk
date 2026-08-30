@extends('layouts.app')

@section('title', 'Tickets | Service Desk')

@section('content')
<h2>Tickets</h2>

<form method="GET" action="{{ route('tickets.index') }}">
    <div>
        <label for="search">Search</label>
        <input
            id="search"
            type="text"
            name="search"
            value="{{ request('search') }}"
            placeholder="Search by title">
    </div>

    <div>
        <label for="status">Status</label>
        <select id="status" name="status">
            <option value="">All statuses</option>

            @foreach (\App\Enums\TicketStatus::cases() as $status)
            <option
                value="{{ $status->value }}"
                @selected(request('status')===$status->value)
                >
                {{ str_replace('_', ' ', ucfirst($status->value)) }}
            </option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="priority">Priority</label>
        <select id="priority" name="priority">
            <option value="">All priorities</option>

            @foreach (\App\Enums\TicketPriority::cases() as $priority)
            <option
                value="{{ $priority->value }}"
                @selected(request('priority')===$priority->value)
                >
                {{ ucfirst($priority->value) }}
            </option>
            @endforeach
        </select>
    </div>

    @if (request()->user()->role !== \App\Enums\UserRole::Requester)
    <div>
        <label for="assignee">Assignee</label>
        <select id="assignee" name="assignee">
            <option value="">All assignees</option>

            <option
                value="unassigned"
                @selected(request('assignee')==='unassigned' )>
                Unassigned
            </option>

            @foreach ($agents as $agent)
            <option
                value="{{ $agent->id }}"
                @selected(request('assignee')===(string) $agent->id)
                >
                {{ $agent->name }}
            </option>
            @endforeach
        </select>
    </div>
    @endif

    <button type="submit">Apply filters</button>

    <a href="{{ route('tickets.index') }}">Reset</a>
</form>

@if ($tickets->isEmpty())
<p>No tickets found.</p>
@else
<table>
    <thead>
        <tr>
            <th>Title</th>
            <th>Status</th>
            <th>Priority</th>
            <th>Creator</th>
            <th>Assignee</th>
            <th>Created at</th>
        </tr>
    </thead>

    <tbody>
        @foreach ($tickets as $ticket)
        <tr>
            <td>
                <a href="{{ route('tickets.show', $ticket) }}">
                    {{ $ticket->title }}
                </a>
            </td>

            <td>
                <span class="badge badge-status-{{ $ticket->status->value }}">
                    {{ str_replace('_', ' ', ucfirst($ticket->status->value)) }}
                </span>
            </td>

            <td>
                <span class="badge badge-priority-{{ $ticket->priority->value }}">
                    {{ ucfirst($ticket->priority->value) }}
                </span>
            </td>

            <td>
                {{ $ticket->creator->name }}
            </td>

            <td>
                {{ $ticket->assignee?->name ?? 'Unassigned' }}
            </td>

            <td>
                {{ $ticket->created_at?->format('Y-m-d H:i') }}
            </td>
        </tr>
        @endforeach
    </tbody>
</table>

{{ $tickets->links() }}
@endif
@endsection