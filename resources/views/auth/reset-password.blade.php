@extends('layouts.guest')

@section('title', 'Reset Password | Service Desk')

@section('content')
<div class="login-wrapper">
    <section class="login-card">
        <div class="login-header">
            <h1>Service Desk</h1>
            <p>Create a new password</p>
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

        <form method="POST" action="{{ route('password.update') }}" class="login-form">
            @csrf

            <input type="hidden" name="token" value="{{ request()->route('token') }}">

            <div>
                <label for="email">Email</label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email', request('email')) }}"
                    autocomplete="email"
                    required
                    autofocus>
            </div>

            <div>
                <label for="password">New password</label>
                <input
                    id="password"
                    type="password"
                    name="password"
                    autocomplete="new-password"
                    required>
            </div>

            <div>
                <label for="password_confirmation">Confirm password</label>
                <input
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    autocomplete="new-password"
                    required>
            </div>

            <button type="submit" class="login-button">
                Reset password
            </button>
        </form>
    </section>
</div>
@endsection