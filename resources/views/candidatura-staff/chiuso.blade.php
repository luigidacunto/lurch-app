<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidatura staff — {{ $evento->nome }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 min-h-screen">

    <div class="bg-red-700 text-white px-4 py-4 shadow">
        <div class="max-w-lg mx-auto">
            <p class="text-xs font-medium uppercase tracking-wide opacity-80">Candidatura staff</p>
            <h1 class="text-lg font-bold leading-tight mt-0.5">{{ $evento->nome }}</h1>
        </div>
    </div>

    <div class="max-w-lg mx-auto px-4 py-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 px-5 py-6 text-center">
            @if(! $evento->candidature_staff_apertura_at || ! $evento->candidature_staff_chiusura_at)
                <p class="text-base font-semibold text-gray-900">Le candidature staff non sono attive per questo evento.</p>
            @elseif(now()->lt($evento->candidature_staff_apertura_at))
                <p class="text-base font-semibold text-gray-900">Le candidature non sono ancora aperte.</p>
                <p class="text-sm text-gray-600 mt-1">Apertura prevista il {{ $evento->candidature_staff_apertura_at->format('d/m/Y H:i') }}.</p>
            @else
                <p class="text-base font-semibold text-gray-900">Le candidature per questo evento sono chiuse.</p>
                <p class="text-sm text-gray-600 mt-1">Termine scaduto il {{ $evento->candidature_staff_chiusura_at->format('d/m/Y H:i') }}.</p>
            @endif
        </div>
    </div>

<x-form-footer />
</body>
</html>
