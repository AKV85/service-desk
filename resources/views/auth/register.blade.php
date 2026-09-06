@extends('layouts.guest')

@section('title', 'Register | Service Desk')

@section('content')
<div class="login-wrapper">
    <section class="login-card">
        <div class="login-header">
            <h1>Service Desk</h1>
            <p>Create your requester account</p>
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

        <form method="POST" action="{{ route('register.store') }}" class="login-form">
            @csrf

            <div>
                <label for="name">Name</label>

                <input
                    id="name"
                    type="text"
                    name="name"
                    value="{{ old('name') }}"
                    autocomplete="name"
                    required
                    autofocus>
            </div>

            <div>
                <label for="email">Email</label>

                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    autocomplete="email"
                    required>
            </div>

            <div>
                <label for="password">Password</label>

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
                Register
            </button>
        </form>

        <p>
            Already have an account?
            <a href="{{ route('login') }}">Login</a>
        </p>
    </section>
</div>
@endsection