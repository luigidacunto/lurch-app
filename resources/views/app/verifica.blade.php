@extends('app.layout')

@section('title', 'Inserisci codice')

@push('styles')
<style>
    body { background: #C8102E; }
    .app-main { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100dvh; padding: 24px 16px; }
    .verifica-card {
        background: #fff;
        border-radius: 16px;
        padding: 32px 24px;
        width: 100%;
        max-width: 400px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.18);
        text-align: center;
    }
    .verifica-icon { font-size: 40px; margin-bottom: 12px; }
    .verifica-card h1 { font-size: 20px; font-weight: 800; color: #111827; margin-bottom: 8px; }
    .verifica-card p { font-size: 14px; color: #6b7280; margin-bottom: 24px; line-height: 1.5; }
    .verifica-card p strong { color: #111827; }
    input[type=text] {
        width: 100%;
        border: 1.5px solid #e5e7eb;
        border-radius: 8px;
        padding: 14px;
        font-size: 28px;
        font-weight: 700;
        color: #111827;
        text-align: center;
        letter-spacing: 10px;
        outline: none;
        transition: border-color 0.2s;
        margin-bottom: 16px;
        background: #f9fafb;
        -webkit-appearance: none;
        font-variant-numeric: tabular-nums;
    }
    input[type=text]:focus { border-color: #C8102E; background: #fff; }
    input.is-invalid { border-color: #ef4444; }
    .error-msg { font-size: 13px; color: #ef4444; margin-top: -10px; margin-bottom: 14px; }
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
        -webkit-appearance: none;
    }
    .btn-primary:active { background: #8a0b1f; }
    .timer { font-size: 12px; color: #9ca3af; margin-top: 16px; }
    .timer span { font-weight: 700; color: #374151; }
    .back-link { display: block; font-size: 13px; color: #9ca3af; margin-top: 20px; text-decoration: none; }
    .back-link:hover { color: #6b7280; }
</style>
@endpush

@section('content')
<div class="verifica-card">
    <div class="verifica-icon">✉️</div>
    <h1>Controlla la email</h1>
    <p>Abbiamo inviato un codice di 6 cifre a<br><strong>{{ $email }}</strong></p>

    @if ($errors->any())
        <div style="background:#b91c1c;color:#fff;border-radius:10px;padding:14px 16px;margin-bottom:18px;font-size:14px;font-weight:600;line-height:1.4;">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('app.verifica.post') }}">
        @csrf
        <input
            type="text"
            name="codice"
            id="codice"
            placeholder="000000"
            inputmode="numeric"
            autocomplete="one-time-code"
            maxlength="6"
            autofocus
            class="{{ $errors->has('codice') ? 'is-invalid' : '' }}"
        >
        <button type="submit" class="btn-primary">Accedi</button>
    </form>

    <p class="timer">Il codice scade tra <span id="countdown">15:00</span></p>
    <a href="{{ route('app.login') }}" class="back-link">← Torna al login</a>
</div>
@endsection

@push('scripts')
<script>
    // Countdown basato sui secondi rimasti dell'OTP reale
    let secondi = {{ $secondiRimasti ?? 900 }};
    const el = document.getElementById('countdown');
    const tick = setInterval(() => {
        secondi--;
        if (secondi <= 0) { clearInterval(tick); el.textContent = 'scaduto'; return; }
        const m = Math.floor(secondi / 60).toString().padStart(2, '0');
        const s = (secondi % 60).toString().padStart(2, '0');
        el.textContent = m + ':' + s;
    }, 1000);

    // Auto-submit al 6° carattere
    document.getElementById('codice').addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 6);
        if (this.value.length === 6) this.closest('form').submit();
    });
</script>
@endpush
