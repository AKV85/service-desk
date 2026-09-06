@extends('layouts.guest')

@section('title', 'Verify Email | Service Desk')

@section('content')
<div class="login-wrapper">
    <section class="login-card">
        <div class="login-header">
            <h1>Service Desk</h1>
            <p>Verify your email address</p>
        </div>

        @if (session('status') === 'verification-link-sent')
        <div class="login-alert" role="status">
            A new verification link has been sent to your email address.
        </div>
        @endif

        <p>
            Before continuing, please check your email and click the verification link.
        </p>

        <form method="POST" action="{{ route('verification.send') }}" class="login-form">
            @csrf

            <button type="submit" class="login-button">
                Resend verification email
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="login-form">
            @csrf

            <button type="submit">
                Logout
            </button>
        </form>
    </section>
</div>
@endsection