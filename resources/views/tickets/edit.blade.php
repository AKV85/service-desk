@extends('layouts.app')

@section('title', 'Edit Ticket | Service Desk')

@section('content')
<section class="panel form-panel">
    <h2>Edit Ticket</h2>

    <form method="POST" action="{{ route('tickets.update', $ticket) }}">
        @csrf
        @method('PUT')

        <div>
            <label for="title">Title</label>
            <input
                id="title"
                type="text"
                name="title"
                value="{{ old('title', $ticket->title) }}"
                required>
        </div>

        <div>
            <label for="description">Description</label>
            <textarea
                id="description"
                name="description"
                required>{{ old('description', $ticket->description) }}</textarea>
        </div>

        <div class="form-actions">
            <button type="submit">Save changes</button>

            <a href="{{ route('tickets.show', $ticket) }}">Cancel</a>
        </div>
    </form>
</section>
@endsection