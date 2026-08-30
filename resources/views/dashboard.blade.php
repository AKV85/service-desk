<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Service Desk</title>
</head>

<body>
    <h1>Service Desk Dashboard</h1>

    <p>
        <a href="{{ route('tickets.index') }}">View tickets</a>
        |
        <a href="{{ route('tickets.create') }}">Create ticket</a>
    </p>

    <h2>Ticket overview</h2>

    <ul>
        <li>New: {{ $counts['new'] }}</li>
        <li>In progress: {{ $counts['in_progress'] }}</li>
        <li>Resolved: {{ $counts['resolved'] }}</li>
        <li>Closed: {{ $counts['closed'] }}</li>
    </ul>

    @if ($unassignedCount !== null)
        <p>Unassigned tickets: {{ $unassignedCount }}</p>
    @endif

    <h2>Recent tickets</h2>

    @if ($recentTickets->isEmpty())
        <p>No recent tickets.</p>
    @else
        <ul>
            @foreach ($recentTickets as $ticket)
                <li>
                    <a href="{{ route('tickets.show', $ticket) }}">
                        {{ $ticket->title }}
                    </a>

                    — {{ $ticket->status->value }}
                    — {{ $ticket->priority->value }}
                </li>
            @endforeach
        </ul>
    @endif

    @if ($assignedToMe->isNotEmpty())
        <h2>Assigned to me</h2>

        <ul>
            @foreach ($assignedToMe as $ticket)
                <li>
                    <a href="{{ route('tickets.show', $ticket) }}">
                        {{ $ticket->title }}
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</body>

</html>