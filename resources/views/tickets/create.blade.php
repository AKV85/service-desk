<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Ticket | Service Desk</title>
</head>
<body>
    <h1>Create Ticket</h1>

    @if ($errors->any())
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('tickets.store') }}">
        @csrf

        <div>
            <label for="title">Title</label>
            <input
                id="title"
                type="text"
                name="title"
                value="{{ old('title') }}"
                required
            >
        </div>

        <div>
            <label for="description">Description</label>
            <textarea
                id="description"
                name="description"
                required
            >{{ old('description') }}</textarea>
        </div>

        <button type="submit">Create ticket</button>
    </form>
</body>
</html>