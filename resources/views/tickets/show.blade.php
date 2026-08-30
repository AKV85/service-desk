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
    @can('assign', $ticket)
    <h2>Assign ticket</h2>

    <form method="POST" action="{{ route('tickets.assignee.update', $ticket) }}">
        @csrf
        @method('PATCH')

        <select name="assigned_to_id">
            <option value="">Unassigned</option>

            @foreach ($agents as $agent)
            <option
                value="{{ $agent->id }}"
                @selected($ticket->assigned_to_id === $agent->id)
                >
                {{ $agent->name }}
            </option>
            @endforeach
        </select>

        <button type="submit">Assign</button>
    </form>

    @error('assigned_to_id')
    <p>{{ $message }}</p>
    @enderror
    @endcan
    <h2>Comments</h2>

    @if ($ticket->comments->isEmpty())
    <p>No comments yet.</p>
    @else
    @foreach ($ticket->comments as $comment)
    <div>
        <strong>{{ $comment->user->name }}</strong>
        <small>{{ $comment->created_at?->format('Y-m-d H:i') }}</small>

        <p>{{ $comment->body }}</p>
    </div>
    @endforeach
    @endif

    @can('comment', $ticket)
    <h3>Add comment</h3>

    <form method="POST" action="{{ route('tickets.comments.store', $ticket) }}">
        @csrf

        <div>
            <label for="body">Comment</label>

            <textarea
                id="body"
                name="body"
                required>{{ old('body') }}</textarea>
        </div>

        @error('body')
        <p>{{ $message }}</p>
        @enderror

        <button type="submit">Add comment</button>
    </form>
    @endcan
    <p>
        <a href="{{ route('tickets.edit', $ticket) }}">Edit ticket</a>
    </p>
</body>

</html>