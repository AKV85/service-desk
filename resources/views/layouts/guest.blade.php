<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <title>@yield('title', 'Service Desk')</title>
</head>

<body class="guest-body">
    <main class="guest-main">
        @yield('content')
    </main>
</body>

</html>