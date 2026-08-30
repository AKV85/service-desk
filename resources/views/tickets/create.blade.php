@extends('layouts.app')

@section('title', 'Create Ticket | Service Desk')

@section('content')
<section class="panel form-panel">
    <h2>Create Ticket</h2>

    <form method="POST" action="{{ route('tickets.store') }}">
        @csrf

        <div>
            <label for="title">Title</label>
            <input
                id="title"
                type="text"
                name="title"
                value="{{ old('title') }}"
                required>
        </div>

        <div>
            <label for="description">Description</label>
            <textarea
                id="description"
                name="description"
                required>{{ old('description') }}</textarea>
        </div>

        <div class="form-actions">
            <button type="submit">Create ticket</button>

            <a href="{{ route('tickets.index') }}">Cancel</a>
        </div>
    </form>
</section>
@endsection