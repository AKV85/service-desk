@extends('layouts.app')

@section('title', 'Edit Ticket | Service Desk')

@section('content')
<h2>Edit Ticket</h2>

@if ($errors->any())
<ul>
    @foreach ($errors->all() as $error)
    <li>{{ $error }}</li>
    @endforeach
</ul>
@endif

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

    <button type="submit">Save changes</button>
</form>

<p>
    <a href="{{ route('tickets.show', $ticket) }}">Back to ticket</a>
</p>
@endsection