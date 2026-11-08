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

        <p class="text-xs text-gray-400 text-center">Versione {{ $versione }}</p>
    </div>

<x-form-footer />
</body>
</html>
