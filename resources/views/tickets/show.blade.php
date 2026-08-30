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
</body>
</html>