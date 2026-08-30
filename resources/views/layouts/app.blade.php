<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <title>@yield('title', 'Service Desk')</title>
</head>

<body>
    <header class="app-header">
        <div class="header-left">
            <h1>Service Desk</h1>

            <nav class="main-nav">
                <a href="{{ route('dashboard') }}">Dashboard</a>
                <a href="{{ route('tickets.index') }}">Tickets</a>
                <a href="{{ route('tickets.create') }}">Create ticket</a>
            </nav>
        </div>

        <div class="header-right">
            <div class="user-info">
                <strong>{{ auth()->user()->name }}</strong>
                <span>{{ auth()->user()->role?->value ?? 'unknown' }}</span>
            </div>

            <form method="POST" action="{{ route('logout') }}" class="logout-form">
                @csrf

                <button type="submit">Logout</button>
            </form>
        </div>
    </header>
    @if (session('success'))
    <div role="alert">
        {{ session('success') }}
    </div>
    @endif

    @if ($errors->any())
    <div role="alert">
        <ul>
            @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif
    <main>
        @yield('content')
    </main>
</body>

</html>