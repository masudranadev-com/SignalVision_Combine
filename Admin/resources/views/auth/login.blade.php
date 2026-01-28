@extends('layouts.app')

@section('title', 'Login - SignalVision Admin')

@section('content')
<style>
    .login-container {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        background: var(--bg-dark);
    }

    .login-box {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 40px;
        width: 100%;
        max-width: 450px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    }

    .login-header {
        text-align: center;
        margin-bottom: 35px;
    }

    .login-logo {
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, var(--cyan), var(--purple));
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
    }

    .login-title {
        font-size: 24px;
        font-weight: 600;
        color: var(--text-white);
        margin-bottom: 8px;
    }

    .login-subtitle {
        font-size: 14px;
        color: var(--text-gray);
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-label {
        display: block;
        font-size: 13px;
        font-weight: 500;
        color: var(--text-white);
        margin-bottom: 8px;
    }

    .form-control {
        width: 100%;
        background: var(--bg-dark);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 12px 16px;
        color: var(--text-white);
        font-size: 14px;
        font-family: inherit;
        transition: all 0.2s;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--cyan);
        box-shadow: 0 0 0 3px rgba(0, 217, 233, 0.1);
    }

    .form-control::placeholder {
        color: var(--text-muted);
    }

    .form-check {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 20px;
    }

    .form-check input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }

    .form-check label {
        font-size: 13px;
        color: var(--text-gray);
        cursor: pointer;
    }

    .btn-login {
        width: 100%;
        background: var(--cyan);
        color: var(--bg-dark);
        border: none;
        border-radius: 8px;
        padding: 14px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        font-family: inherit;
    }

    .btn-login:hover {
        background: #00c4d4;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 217, 233, 0.3);
    }

    .alert {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 13px;
    }

    .alert-danger {
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid var(--red);
        color: var(--red);
    }
</style>

<div class="login-container">
    <div class="login-box">
        <div class="login-header">
            <div class="login-logo">
                <i data-lucide="activity" style="width: 32px; height: 32px; color: white;"></i>
            </div>
            <h1 class="login-title">Welcome Back</h1>
            <p class="login-subtitle">Sign in to SignalVision Admin Zone</p>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">
                @foreach ($errors->all() as $error)
                    {{ $error }}
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('login.post') }}">
            @csrf

            <div class="form-group">
                <label for="username" class="form-label">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    class="form-control"
                    placeholder="Enter your username"
                    value="{{ old('username') }}"
                    required
                    autofocus
                >
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    class="form-control"
                    placeholder="Enter your password"
                    required
                >
            </div>

            <div class="form-check">
                <input type="checkbox" id="remember" name="remember">
                <label for="remember">Remember me</label>
            </div>

            <button type="submit" class="btn-login">
                Sign In
            </button>
        </form>
    </div>
</div>

<script>
    lucide.createIcons();
</script>
@endsection
