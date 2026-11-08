<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link non valido — Croce Rossa Italiana</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 min-h-screen">

    <div class="bg-red-700 text-white px-4 py-4 shadow">
        <div class="max-w-lg mx-auto">
            <p class="text-xs font-medium uppercase tracking-wide opacity-80">Croce Rossa Italiana</p>
            <h1 class="text-lg font-bold leading-tight mt-0.5">Accesso pannello</h1>
        </div>
    </div>

    <div class="max-w-lg mx-auto px-4 py-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 px-5 py-6 text-center">
            <p class="text-base font-semibold text-gray-900">Link non valido o scaduto</p>
            <p class="text-sm text-gray-600 mt-1">
                Il link utilizzato non è valido oppure è scaduto (validità 15 minuti). Puoi comunque inserire il codice ricevuto via email nella pagina da cui hai effettuato la richiesta, oppure richiederne uno nuovo.
            </p>
            <a href="{{ route('admin-login.form') }}" class="inline-block mt-4 text-sm font-semibold text-red-700 underline">Richiedi un nuovo accesso</a>
        </div>
    </div>

<x-form-footer />
</body>
</html>
