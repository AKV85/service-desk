@extends('layouts.guest')

@section('title', 'Login | Service Desk')

@section('content')
<div class="login-wrapper">
    <section class="login-card">
        <div class="login-header">
            <h1>Service Desk</h1>
            <p>Sign in to continue</p>
        </div>

        @if ($errors->any())
        <div class="login-alert" role="alert">
            <ul>
                @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="login-form">
            @csrf

            <div>
                <label for="email">Email</label>

                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    autocomplete="email"
                    required
                    autofocus>
            </div>

            <div>
                <label for="password">Password</label>

                <input
                    id="password"
                    type="password"
                    name="password"
                    autocomplete="current-password"
                    required>
            </div>

            <label class="remember-option">
                <input
                    type="checkbox"
                    name="remember"
                    @checked(old('remember'))>

                <span>Remember me</span>
            </label>

            <button type="submit" class="login-button">
                Login
            </button>
        </form>
    </section>
</div>
@endsection