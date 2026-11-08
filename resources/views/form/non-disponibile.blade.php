<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Form non disponibile</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center px-4">
    <div class="max-w-sm w-full text-center space-y-4">
        <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto">
            <svg class="w-8 h-8 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
        </div>
        <h1 class="text-lg font-bold text-gray-900">Form non ancora disponibile</h1>
        <p class="text-sm text-gray-500">
            Il form di preregistrazione per <strong>{{ $corso->nome }}</strong> non è ancora stato configurato.<br>
            Riprova più tardi o contatta l'organizzazione.
        </p>
        <p class="text-xs text-gray-300 pt-4">Croce Rossa Italiana · Comitato Regionale Toscana</p>
    </div>
<x-form-footer />
</body>
</html>
