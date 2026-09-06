@extends('layouts.guest')

@section('title', 'Forgot Password | Service Desk')

@section('content')
<div class="login-wrapper">
    <section class="login-card">
        <div class="login-header">
            <h1>Service Desk</h1>
            <p>Reset your password</p>
        </div>

        @if (session('status'))
        <div class="login-alert" role="status">
            {{ session('status') }}
        </div>
        @endif

        @if ($errors->any())
        <div class="login-alert" role="alert">
            <ul>
                @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="login-form">
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

            <button type="submit" class="login-button">
                Send reset link
            </button>
        </form>

        <p>
            <a href="{{ route('login') }}">Back to login</a>
        </p>
    </section>
</div>
@endsection