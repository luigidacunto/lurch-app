<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email verificata — Croce Rossa Italiana</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 min-h-screen">

    <div class="bg-red-700 text-white px-4 py-4 shadow">
        <div class="max-w-lg mx-auto">
            <p class="text-xs font-medium uppercase tracking-wide opacity-80">Croce Rossa Italiana</p>
            <h1 class="text-lg font-bold leading-tight mt-0.5">Verifica email</h1>
        </div>
    </div>

    <div class="max-w-lg mx-auto px-4 py-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 px-5 py-6 text-center">
            @if($giaFatto)
                <p class="text-base font-semibold text-gray-900">Email già verificata</p>
                <p class="text-sm text-gray-600 mt-1">Questo indirizzo era già stato confermato in precedenza.</p>
            @else
                <p class="text-base font-semibold text-gray-900">Email verificata con successo!</p>
                <p class="text-sm text-gray-600 mt-1">
                    Grazie {{ $nome }}, il tuo indirizzo email è confermato e potrà essere usato per le comunicazioni relative alla tua candidatura.
                </p>
            @endif
        </div>
    </div>

<x-form-footer />
</body>
</html>
