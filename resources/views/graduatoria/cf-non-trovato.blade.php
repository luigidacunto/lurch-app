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

    <div class="max-w-lg mx-auto px-4 py-6 space-y-6">

        <div class="bg-amber-50 border border-amber-200 rounded-2xl px-5 py-4 space-y-3">
            <p class="text-sm font-semibold text-amber-900">Codice fiscale non trovato</p>
            <p class="text-sm text-amber-800 leading-relaxed">
                Verifica il codice fiscale inserito, nessuna corrispondenza trovata. Se sei un nuovo volontario registrato da poco su GAIA, procedi confermando il codice fiscale inserito e inserendo tutti i dati manualmente: un amministratore dovrà confermare i dati presenti su GAIA. Altrimenti controlla il codice fiscale inserito per accertarti che non ci siano errori di battitura.
            </p>
        </div>

        <div class="flex flex-col gap-3">
            <a href="{{ route('graduatoria.mostra', $graduatoria->codice_pubblico) }}"
               class="w-full text-center bg-white border border-gray-300 text-gray-700 rounded-lg py-2.5 text-sm font-semibold hover:bg-gray-50">
                Correggi il codice fiscale
            </a>
            <form method="POST" action="{{ route('graduatoria.forza-inserimento', $slug) }}">
                @csrf
                <button type="submit" class="w-full bg-red-700 text-white rounded-lg py-2.5 text-sm font-semibold hover:bg-red-800">
                    Forza inserimento manuale
                </button>
            </form>
        </div>
    </div>

<x-form-footer />
</body>
</html>
