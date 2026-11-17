<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informativa privacy — LURCH</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 min-h-screen">

    <div class="bg-red-700 text-white px-4 py-4 shadow">
        <div class="max-w-2xl mx-auto">
            <p class="text-xs font-medium uppercase tracking-wide opacity-80">LURCH</p>
            <h1 class="text-lg font-bold leading-tight mt-0.5">Informativa sul trattamento dei dati personali</h1>
        </div>
    </div>

    <div class="max-w-2xl mx-auto px-4 py-6 space-y-4">

        @if($pdf)
            <a href="{{ Storage::url($pdf) }}" target="_blank"
               class="inline-flex items-center gap-1 text-sm text-red-600 underline">
                📄 Scarica il PDF
            </a>
        @endif

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 px-5 py-5 space-y-3">
            @forelse($blocchi as $blocco)
                @if($blocco['tipo'] === 'titolo')
                    <h2 id="{{ $blocco['id'] }}" class="text-sm font-bold text-gray-900 pt-3 first:pt-0">{{ $blocco['testo'] }}</h2>
                @else
                    <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-line">{{ $blocco['testo'] }}</p>
                @endif
            @empty
                <p class="text-sm text-gray-500">Informativa non ancora disponibile.</p>
            @endforelse
        </div>

        {{-- Parte fissa, legata al funzionamento del software: va aggiornata insieme al codice
             quando cambiano i cookie, la memoria locale del browser o i dati di navigazione registrati. --}}
        <div id="cookie" class="bg-white rounded-2xl shadow-sm border border-gray-100 px-5 py-5 space-y-3">
            <h2 class="text-sm font-bold text-gray-900">Cookie e dati di navigazione</h2>
            <p class="text-sm text-gray-700 leading-relaxed">
                Questo sito usa esclusivamente cookie tecnici, necessari al suo funzionamento: non usa cookie
                di profilazione, statistiche di terze parti, pubblicità o contenuti incorporati da altri siti.
                Per questo non è richiesto il consenso (art. 122 del Codice privacy e Linee guida del Garante
                del 10 giugno 2021); questa sezione ne dà informazione.
            </p>
            <ul class="text-sm text-gray-700 leading-relaxed list-disc pl-4 space-y-1">
                <li><strong>Cookie di sessione</strong> (nome derivato da quello dell'applicazione): mantiene l'accesso e lo stato della navigazione. Scade dopo il periodo di inattività previsto (una settimana nella configurazione standard) o con l'uscita.</li>
                <li><strong>XSRF-TOKEN</strong>: protegge i moduli da invii fraudolenti (CSRF). Stessa durata del cookie di sessione.</li>
                <li><strong>app_remember</strong>: solo nell'app dei soci (<code>/app</code>), dopo l'accesso con codice via email; evita di ripetere l'accesso sullo stesso dispositivo. Durata 90 giorni, eliminato con l'uscita.</li>
                <li><strong>Memoria locale del browser</strong>: durante le prove d'esame conserva lo stato del timer sul dispositivo; non viene inviata al server.</li>
            </ul>
            <p class="text-sm text-gray-700 leading-relaxed">
                Per la sicurezza degli accessi il sistema registra la data e l'ora dell'ultima attività di
                ciascun utente del pannello e di ciascun socio nell'app. Il dato è visibile solo agli
                amministratori competenti e serve a individuare accessi anomali e a chiudere le sessioni in
                caso di segnalazione.
            </p>
            <p class="text-sm text-gray-700 leading-relaxed">
                Tutte le risorse delle pagine (script, fogli di stile, caratteri) sono servite da questo
                server: la navigazione non trasmette dati a terzi. I collegamenti a servizi esterni (ad
                esempio le mappe) si aprono solo su richiesta.
            </p>
        </div>

        <p class="text-xs text-gray-400 text-center">Versione {{ $versione }}</p>
    </div>

<x-form-footer />
</body>
</html>
