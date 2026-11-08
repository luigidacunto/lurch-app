<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preregistrazione confermata — {{ $evento->nome }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 min-h-screen flex flex-col items-center justify-center px-4">

    <div class="max-w-md w-full bg-white rounded-2xl shadow-sm border border-gray-100 px-6 py-8 text-center">
        <div style="width:3.5rem;height:3.5rem;background:#dcfce7;border-radius:9999px;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
        </div>
        <h1 class="text-xl font-bold text-gray-900 mb-1">Preregistrazione inviata!</h1>
        <p class="text-sm text-gray-600 mb-4">
            Grazie <strong>{{ $partecipante->cognome }} {{ $partecipante->nome }}</strong>,<br>
            la tua preregistrazione come staff per<br>
            <strong>{{ $evento->nome }}</strong> è stata ricevuta.
        </p>
        <p class="text-xs text-gray-400">
            Riceverai ulteriori istruzioni dalla segreteria dell'evento.<br>
            Ti aspettiamo! Puoi chiudere questa pagina.
        </p>
    </div>

    @if(session('email_verificata'))
        <div class="max-w-md w-full bg-green-50 border border-green-300 rounded-xl px-4 py-3 text-sm text-green-800 text-center mt-4">
            Email verificata con successo!
        </div>
    @elseif($emailDaVerificare)
        <div class="max-w-md w-full bg-white rounded-2xl shadow-sm border border-gray-100 px-6 py-6 text-left mt-4">
            <p class="text-sm font-semibold text-gray-900">Conferma il tuo indirizzo email</p>
            <p class="text-xs text-gray-500 mt-1">
                Questa verifica serve solo ad accertarci che l'indirizzo <strong>{{ $emailDaVerificare }}</strong> riceva
                correttamente le email, così da poterti raggiungere per le comunicazioni relative all'evento.
            </p>
            <p class="text-xs text-gray-500 mt-2">
                Ti abbiamo inviato un'email con un link e un codice di 6 cifre: clicca il link, oppure inserisci il codice qui sotto.
            </p>
            <p class="text-xs text-gray-500 mt-2">
                Se non la ricevi entro la giornata, controlla la cartella spam/posta indesiderata.
            </p>

            @if($errors->any())
                <div class="bg-red-50 border border-red-300 rounded-lg px-3 py-2 text-xs text-red-800 mt-3">
                    @foreach($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('form-staff.verifica-email', $iscrizione->token) }}" class="mt-5 flex items-center gap-2">
                @csrf
                <input type="text" name="codice" maxlength="6" inputmode="numeric" pattern="[0-9]{6}"
                       placeholder="000000" autocomplete="one-time-code"
                       class="w-32 text-center tracking-[0.3em] font-mono rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
                <button type="submit" class="bg-red-700 text-white rounded-lg px-4 py-2 text-sm font-semibold hover:bg-red-800">
                    Verifica
                </button>
            </form>
        </div>
    @endif

    @if(session('doc_sostituito'))
        <div class="max-w-md w-full bg-green-50 border border-green-300 rounded-xl px-4 py-3 text-sm text-green-800 text-center mt-4">
            {{ session('doc_sostituito') }}
        </div>
    @elseif(session('doc_errore'))
        <div class="max-w-md w-full bg-amber-50 border border-amber-300 rounded-xl px-4 py-3 text-sm text-amber-800 text-center mt-4">
            {{ session('doc_errore') }}
        </div>
    @endif

    @php
        $hasLogistica = collect($risposta?->risposte['disponibilita'] ?? [])->pluck('tipo')->contains('logistica');
    @endphp
    @if($risposta?->nullaosta_path && $hasLogistica)
        <div class="max-w-md w-full bg-white rounded-2xl shadow-sm border border-gray-100 px-5 py-4 mt-4">
            <p class="text-xs text-gray-400 uppercase tracking-wide font-medium mb-1">Documenti</p>
            <x-sostituzione-documento
                slot="nullaosta" label="Nullaosta del Presidente" accept=".pdf,.jpg,.jpeg"
                :action="route('form-staff.documenti.sostituisci', [$iscrizione->token, 'nullaosta'])"
                :caricato="(bool) $risposta?->nullaosta_path"
                :verificato="(bool) $risposta?->nullaosta_verificato" />
        </div>
    @endif

    <p class="text-center text-xs text-gray-300 mt-8">Croce Rossa Italiana · Gestione Staff</p>

<x-form-footer />
</body>
</html>
