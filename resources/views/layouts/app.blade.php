<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Service Desk')</title>
</head>

<body>
    <header>
        <h1>Service Desk</h1>

        <nav>
            <a href="{{ route('dashboard') }}">Dashboard</a>
            |
            <a href="{{ route('tickets.index') }}">Tickets</a>
            |
            <a href="{{ route('tickets.create') }}">Create ticket</a>
        </nav>

        <p>
            {{ auth()->user()->name }}
            ({{ auth()->user()->role?->value ?? 'unknown' }})
        </p>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit">Logout</button>
        </form>

        <hr>
    </header>

    <main>
        @yield('content')
    </main>
</body>

</html>