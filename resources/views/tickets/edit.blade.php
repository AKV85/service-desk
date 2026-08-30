<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Ticket | Service Desk</title>
</head>
<body>
    <h1>Edit Ticket</h1>

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
                required
            >
        </div>

        <div>
            <label for="description">Description</label>
            <textarea
                id="description"
                name="description"
                required
            >{{ old('description', $ticket->description) }}</textarea>
        </div>

        <button type="submit">Save changes</button>
    </form>
</body>
</html>