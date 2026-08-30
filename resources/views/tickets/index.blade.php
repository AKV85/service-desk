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