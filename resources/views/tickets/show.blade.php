@extends('layouts.app')

@section('title', $ticket->title . ' | Service Desk')

@section('content')
<div class="ticket-header">
    <div>
        <h2>{{ $ticket->title }}</h2>
        <p class="ticket-description">{{ $ticket->description }}</p>
    </div>

    <div class="ticket-meta">
        <span class="badge badge-status-{{ $ticket->status->value }}">
            {{ str_replace('_', ' ', ucfirst($ticket->status->value)) }}
        </span>

        <span class="badge badge-priority-{{ $ticket->priority->value }}">
            {{ ucfirst($ticket->priority->value) }}
        </span>
    </div>
</div>

<div class="ticket-grid">
    <section class="panel">
        <h3>Ticket details</h3>

        <p>
            <strong>Creator:</strong>
            {{ $ticket->creator->name }}
        </p>

        <p>
            <strong>Assignee:</strong>
            {{ $ticket->assignee?->name ?? 'Unassigned' }}
        </p>

        <p>
            <strong>Created at:</strong>
            {{ $ticket->created_at?->format('Y-m-d H:i') }}
        </p>

        @if ($ticket->resolved_at)
        <p>
            <strong>Resolved at:</strong>
            {{ $ticket->resolved_at->format('Y-m-d H:i') }}
        </p>
        @endif

        @if ($ticket->closed_at)
        <p>
            <strong>Closed at:</strong>
            {{ $ticket->closed_at->format('Y-m-d H:i') }}
        </p>
        @endif

        <div class="ticket-actions">
            <a href="{{ route('tickets.edit', $ticket) }}">
                Edit ticket
            </a>

            @can('delete', $ticket)
            <form
                method="POST"
                action="{{ route('tickets.destroy', $ticket) }}"
                class="delete-ticket-form"
                onsubmit="return confirm('Are you sure you want to delete this ticket?');">
                @csrf
                @method('DELETE')

                <button type="submit" class="button-danger">
                    Delete ticket
                </button>
            </form>
            @endcan
        </div>
    </section>

    @if (
    auth()->user()->can('changeStatus', $ticket)
    || auth()->user()->can('changePriority', $ticket)
    || auth()->user()->can('assign', $ticket)
    )
    <section class="panel">
        <h3>Manage ticket</h3>

        @can('changeStatus', $ticket)
        <form method="POST" action="{{ route('tickets.status.update', $ticket) }}">
            @csrf
            @method('PATCH')

            <div>
                <label for="status">Status</label>

                <select id="status" name="status">
                    @foreach (\App\Enums\TicketStatus::cases() as $status)
                    <option
                        value="{{ $status->value }}"
                        @selected($ticket->status === $status)
                        >
                        {{ str_replace('_', ' ', ucfirst($status->value)) }}
                    </option>
                    @endforeach
                </select>
            </div>

            @error('status')
            <p class="field-error">{{ $message }}</p>
            @enderror

            <button type="submit">Change status</button>
        </form>
        @endcan

        @can('changePriority', $ticket)
        <form method="POST" action="{{ route('tickets.priority.update', $ticket) }}">
            @csrf
            @method('PATCH')

            <div>
                <label for="priority">Priority</label>

                <select id="priority" name="priority">
                    @foreach (\App\Enums\TicketPriority::cases() as $priority)
                    <option
                        value="{{ $priority->value }}"
                        @selected($ticket->priority === $priority)
                        >
                        {{ ucfirst($priority->value) }}
                    </option>
                    @endforeach
                </select>
            </div>

            @error('priority')
            <p class="field-error">{{ $message }}</p>
            @enderror

            <button type="submit">Change priority</button>
        </form>
        @endcan

        @can('assign', $ticket)
        <form method="POST" action="{{ route('tickets.assignee.update', $ticket) }}">
            @csrf
            @method('PATCH')

            <div>
                <label for="assigned_to_id">Assignee</label>

                <select id="assigned_to_id" name="assigned_to_id">
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
            </div>

            @error('assigned_to_id')
            <p class="field-error">{{ $message }}</p>
            @enderror

            <button type="submit">Assign</button>
        </form>
        @endcan
    </section>
    @endif
</div>
<section class="panel attachments-panel">
    <h3>Attachments</h3>

    @if ($ticket->attachments->isEmpty())
    <p class="empty-state">No attachments yet.</p>
    @else
    <div class="attachment-list">
        @foreach ($ticket->attachments as $attachment)
        <div class="attachment-item">
            <div>
                <strong>
                    <a href="{{ route('tickets.attachments.download', [$ticket, $attachment]) }}">
                        {{ $attachment->original_name }}
                    </a>
                </strong>

                <small>
                    Uploaded by {{ $attachment->user->name }}
                    · {{ $attachment->created_at?->format('Y-m-d H:i') }}
                </small>
            </div>

            <span>
                {{ number_format($attachment->size / 1024, 1) }} KB
            </span>
        </div>
        @endforeach
    </div>
    @endif

    <form
        method="POST"
        action="{{ route('tickets.attachments.store', $ticket) }}"
        enctype="multipart/form-data">
        @csrf

        <div>
            <label for="attachment">Add attachment</label>

            <input
                id="attachment"
                type="file"
                name="attachment"
                accept=".jpg,.jpeg,.png,.pdf,.txt,.log"
                required>
        </div>

        @error('attachment')
        <p class="field-error">{{ $message }}</p>
        @enderror

        <button type="submit">Upload attachment</button>
    </form>
</section>
<div class="ticket-content-grid">
    <section class="panel">
        <h3>Comments</h3>

        @if ($ticket->comments->isEmpty())
        <p class="empty-state">No comments yet.</p>
        @else
        <div class="timeline">
            @foreach ($ticket->comments as $comment)
            <article class="timeline-item">
                <div class="timeline-header">
                    <strong>{{ $comment->user->name }}</strong>
                    <small>{{ $comment->created_at?->format('Y-m-d H:i') }}</small>
                </div>

                <p>{{ $comment->body }}</p>
            </article>
            @endforeach
        </div>
        @endif

        @can('comment', $ticket)
        <form method="POST" action="{{ route('tickets.comments.store', $ticket) }}">
            @csrf

            <div>
                <label for="body">Add comment</label>

                <textarea
                    id="body"
                    name="body"
                    required>{{ old('body') }}</textarea>
            </div>

            @error('body')
            <p class="field-error">{{ $message }}</p>
            @enderror

            <button type="submit">Add comment</button>
        </form>
        @endcan
    </section>

    <section class="panel">
        <h3>History</h3>

        @if ($ticket->history->isEmpty())
        <p class="empty-state">No history yet.</p>
        @else
        <div class="timeline">
            @foreach ($ticket->history as $history)
            <article class="timeline-item">
                <div class="timeline-header">
                    <strong>
                        {{ $history->user?->name ?? 'Unknown user' }}
                    </strong>

                    <small>
                        {{ $history->created_at?->format('Y-m-d H:i') }}
                    </small>
                </div>

                @if ($history->action === 'status_changed')
                <p>
                    Changed status from
                    <strong>{{ $history->old_values['status'] ?? 'unknown' }}</strong>
                    to
                    <strong>{{ $history->new_values['status'] ?? 'unknown' }}</strong>
                </p>
                @elseif ($history->action === 'priority_changed')
                <p>
                    Changed priority from
                    <strong>{{ $history->old_values['priority'] ?? 'unknown' }}</strong>
                    to
                    <strong>{{ $history->new_values['priority'] ?? 'unknown' }}</strong>
                </p>
                @elseif ($history->action === 'assignee_changed')
                <p>
                    Changed assignee from
                    <strong>{{ $history->old_values['assigned_to_id'] ?? 'Unassigned' }}</strong>
                    to
                    <strong>{{ $history->new_values['assigned_to_id'] ?? 'Unassigned' }}</strong>
                </p>
                @else
                <p>{{ $history->action }}</p>
                @endif
            </article>
            @endforeach
        </div>
        @endif
    </section>
</div>
@endsection