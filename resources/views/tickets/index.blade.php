<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tickets | Service Desk</title>
</head>

<body>
    <h1>Tickets</h1>

    <p>
        <a href="{{ route('tickets.create') }}">Create ticket</a>
    </p>
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
                    {{ $status->value }}
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
                    {{ $priority->value }}
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
    <table border="1" cellpadding="8" cellspacing="0">
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
                <td>{{ $ticket->status->value }}</td>
                <td>{{ $ticket->priority->value }}</td>
                <td>{{ $ticket->creator->name }}</td>
                <td>{{ $ticket->assignee?->name ?? 'Unassigned' }}</td>
                <td>{{ $ticket->created_at?->format('Y-m-d H:i') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{ $tickets->links() }}
    @endif
</body>

</html>