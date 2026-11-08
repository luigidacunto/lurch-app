<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inserisci codice — Croce Rossa Italiana</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 min-h-screen">

    <div class="bg-red-700 text-white px-4 py-4 shadow">
        <div class="max-w-lg mx-auto">
            <p class="text-xs font-medium uppercase tracking-wide opacity-80">Croce Rossa Italiana</p>
            <h1 class="text-lg font-bold leading-tight mt-0.5">Controlla la email</h1>
        </div>
    </div>

    <div class="max-w-lg mx-auto px-4 py-6 space-y-6">

        @if($errors->any())
            <div class="bg-red-50 border border-red-300 rounded-xl px-4 py-3 text-sm text-red-800">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100">
                <p class="text-sm font-semibold text-gray-700">
                    Abbiamo inviato un codice a 6 cifre e un link a <strong>{{ $email }}</strong>
                </p>
            </div>
            <form method="POST" action="{{ route('admin-login.verifica.post') }}" class="px-5 py-4 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Codice</label>
                    <input type="text" name="codice" id="codice" maxlength="6" inputmode="numeric" autocomplete="one-time-code" autofocus
                           placeholder="000000"
                           class="w-full rounded-lg border border-gray-300 px-3 py-3 text-center text-2xl font-bold tracking-widest focus:outline-none focus:ring-2 focus:ring-red-500 @error('codice') border-red-400 @enderror">
                    @error('codice')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="w-full bg-red-700 text-white rounded-lg py-2.5 text-sm font-semibold hover:bg-red-800">
                    Accedi
                </button>
            </form>
        </div>

        <p class="text-xs text-gray-500 text-center">Il codice scade tra <span id="countdown" class="font-semibold">15:00</span></p>
        <p class="text-xs text-gray-500 text-center">
            <a href="{{ route('admin-login.form') }}" class="underline">← Torna indietro</a>
            &nbsp;·&nbsp;
            <a href="{{ url('/admin/login?password=1') }}" class="underline">Accedi con la password</a>
        </p>
    </div>

<x-form-footer />

<script>
    let secondi = {{ $secondiRimasti ?? 900 }};
    const el = document.getElementById('countdown');
    const tick = setInterval(() => {
        secondi--;
        if (secondi <= 0) { clearInterval(tick); el.textContent = 'scaduto'; return; }
        const m = Math.floor(secondi / 60).toString().padStart(2, '0');
        const s = (secondi % 60).toString().padStart(2, '0');
        el.textContent = m + ':' + s;
    }, 1000);

    const form = document.getElementById('codice').closest('form');
    let inviato = false;
    form.addEventListener('submit', function (e) {
        if (inviato) { e.preventDefault(); return; }
        inviato = true;
        form.querySelector('button[type="submit"]').disabled = true;
    });

    document.getElementById('codice').addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 6);
        if (this.value.length === 6 && !inviato) form.requestSubmit();
    });
</script>
</body>
</html>
