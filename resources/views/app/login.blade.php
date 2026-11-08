@extends('app.layout')

@section('title', 'Accedi')

@push('styles')
<style>
    body { background: #C8102E; }
    .app-main { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100dvh; padding: 24px 16px; }
    .login-card {
        background: #fff;
        border-radius: 16px;
        padding: 32px 24px;
        width: 100%;
        max-width: 400px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.18);
    }
    .login-logo {
        text-align: center;
        margin-bottom: 24px;
    }
    .login-logo svg { width: 48px; height: 48px; color: #C8102E; }
    .login-logo h1 { font-size: 22px; font-weight: 800; color: #111827; margin-top: 8px; }
    .login-logo p { font-size: 13px; color: #6b7280; margin-top: 4px; }
    label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
    input[type=email], input[type=text] {
        width: 100%;
        border: 1.5px solid #e5e7eb;
        border-radius: 8px;
        padding: 12px 14px;
        font-size: 16px;
        color: #111827;
        outline: none;
        transition: border-color 0.2s;
        margin-bottom: 16px;
        background: #f9fafb;
        -webkit-appearance: none;
    }
    input[type=email]:focus, input[type=text]:focus { border-color: #C8102E; background: #fff; }
    input.is-invalid { border-color: #ef4444; }
    .error-msg { font-size: 12px; color: #ef4444; margin-top: -10px; margin-bottom: 14px; }
    .btn-primary {
        width: 100%;
        background: #C8102E;
        color: #fff;
        border: none;
        border-radius: 8px;
        padding: 14px;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        transition: background 0.2s;
        -webkit-appearance: none;
    }
    .btn-primary:hover { background: #a50e26; }
    .btn-primary:active { background: #8a0b1f; }
    .helper-text { font-size: 12px; color: #9ca3af; text-align: center; margin-top: 16px; line-height: 1.5; }
</style>
@endpush

@section('content')
<div class="login-card">
    <div class="login-logo">
        <svg viewBox="0 0 24 24" fill="currentColor" style="color:#C8102E">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>
        </svg>
        <h1>Lurch</h1>
        <p>Portale eventi e corsi CRI</p>
    </div>

    @if ($errors->any())
        <div class="alert alert-error" style="margin-bottom:16px;">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('app.login.post') }}">
        @csrf

        <label for="email">Indirizzo email</label>
        <input
            type="email"
            id="email"
            name="email"
            value="{{ old('email') }}"
            placeholder="la.tua@email.it"
            autocomplete="email"
            inputmode="email"
            required
            class="{{ $errors->has('email') ? 'is-invalid' : '' }}"
        >

        <button type="submit" class="btn-primary">Ricevi codice di accesso</button>
    </form>

    <p class="helper-text">Riceverai un codice via email all'indirizzo con cui sei registrato nei sistemi CRI.</p>
    <p class="helper-text"><a href="{{ route('guide.index') }}" style="color:#C8102E;">Guide all'uso del portale</a></p>
</div>
@endsection
