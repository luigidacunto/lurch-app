<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Graduatoria — {{ $graduatoria->titolo }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 min-h-screen">

    <div class="bg-red-700 text-white px-4 py-4 shadow">
        <div class="max-w-lg mx-auto">
            <p class="text-xs font-medium uppercase tracking-wide opacity-80">Iscrizione graduatoria</p>
            <h1 class="text-lg font-bold leading-tight mt-0.5">{{ $graduatoria->titolo }}</h1>
        </div>
    </div>

    <div class="max-w-lg mx-auto px-4 py-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 px-5 py-6 text-center">
            @if(now()->lt($graduatoria->data_apertura))
                <p class="text-base font-semibold text-gray-900">Le iscrizioni non sono ancora aperte.</p>
                <p class="text-sm text-gray-600 mt-1">Apertura prevista il {{ $graduatoria->data_apertura->format('d/m/Y H:i') }}.</p>
            @else
                <p class="text-base font-semibold text-gray-900">Le iscrizioni a questa graduatoria sono chiuse.</p>
                <p class="text-sm text-gray-600 mt-1">Termine scaduto il {{ $graduatoria->data_chiusura->format('d/m/Y H:i') }}.</p>
            @endif
        </div>
    </div>

<x-form-footer />
</body>
</html>
