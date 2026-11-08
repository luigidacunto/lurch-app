<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form già compilato</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center px-4">
    <div class="max-w-sm w-full text-center space-y-5">

        <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto">
            <svg class="w-8 h-8 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
            </svg>
        </div>

        <div>
            <h1 class="text-xl font-bold text-gray-900">Form già compilato</h1>
            <p class="text-sm text-gray-500 mt-1">
                {{ $partecipante->cognome }} {{ $partecipante->nome }}
            </p>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4 text-left space-y-1">
            <p class="text-xs text-gray-400 uppercase tracking-wide font-medium">Corso</p>
            <p class="text-sm font-semibold text-gray-900">{{ $corso->nome }}</p>
            <p class="text-xs text-gray-500">{{ $evento->nome }}</p>
            <p class="text-xs text-gray-400 mt-1">
                {{ $corso->data_inizio->format('d/m/Y') }}
                @if(!$corso->data_inizio->eq($corso->data_fine))
                    — {{ $corso->data_fine->format('d/m/Y') }}
                @endif
            </p>
        </div>

        <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 text-left">
            <p class="text-sm text-amber-800 font-medium">Le tue risposte sono state registrate</p>
            <p class="text-xs text-amber-700 mt-1">
                Il form non può essere modificato dopo la compilazione. Per eventuali necessità contatta la segreteria di formazione di riferimento.
            </p>
        </div>

        <p class="text-xs text-gray-300 pt-2">Croce Rossa Italiana · Comitato Regionale Toscana</p>
    </div>
<x-form-footer />
</body>
</html>
