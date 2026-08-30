<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $ticket->title }} | Service Desk</title>
</head>

<body>
    <h1>{{ $ticket->title }}</h1>

    <p>{{ $ticket->description }}</p>

    <p>Status: {{ $ticket->status->value }}</p>
    <p>Priority: {{ $ticket->priority->value }}</p>
    @can('changeStatus', $ticket)
    <h2>Change status</h2>

    <form method="POST" action="{{ route('tickets.status.update', $ticket) }}">
        @csrf
        @method('PATCH')

        <select name="status">
            @foreach (\App\Enums\TicketStatus::cases() as $status)
            <option
                value="{{ $status->value }}"
                @selected($ticket->status === $status)
                >
                {{ $status->value }}
            </option>
            @endforeach
        </select>

        <button type="submit">Change status</button>
    </form>

    @error('status')
    <p>{{ $message }}</p>
    @enderror
    @endcan

    @can('changePriority', $ticket)
    <h2>Change priority</h2>

    <form method="POST" action="{{ route('tickets.priority.update', $ticket) }}">
        @csrf
        @method('PATCH')

        <select name="priority">
            @foreach (\App\Enums\TicketPriority::cases() as $priority)
            <option
                value="{{ $priority->value }}"
                @selected($ticket->priority === $priority)
                >
                {{ $priority->value }}
            </option>
            @endforeach
        </select>

        <button type="submit">Change priority</button>
    </form>

    @error('priority')
    <p>{{ $message }}</p>
    @enderror
    @endcan
    <p>
        <a href="{{ route('tickets.edit', $ticket) }}">Edit ticket</a>
    </p>
</body>

</html>