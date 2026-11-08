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
            <p class="text-xs opacity-60 mt-0.5 font-mono">{{ $graduatoria->codice_protocollo }}</p>
        </div>
    </div>

    <div class="max-w-lg mx-auto px-4 py-6 space-y-6">

        @if($errors->any())
            <div class="bg-red-50 border border-red-300 rounded-xl px-4 py-3 text-sm text-red-800">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100">
                <p class="text-sm font-semibold text-gray-700">Inserisci il tuo codice fiscale</p>
            </div>
            <form method="POST" action="{{ route('graduatoria.verifica', $graduatoria->codice_pubblico) }}" class="px-5 py-4 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Codice fiscale <span class="text-red-500">*</span></label>
                    <input type="text" name="codice_fiscale" maxlength="16" required
                           value="{{ old('codice_fiscale') }}"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm uppercase focus:outline-none focus:ring-2 focus:ring-red-500 @error('codice_fiscale') border-red-400 @enderror">
                    @error('codice_fiscale')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="w-full bg-red-700 text-white rounded-lg py-2.5 text-sm font-semibold hover:bg-red-800">
                    Continua
                </button>
            </form>
        </div>

        <p class="text-xs text-gray-500 text-center">
            Le iscrizioni sono aperte dal {{ $graduatoria->data_apertura->format('d/m/Y H:i') }}
            al {{ $graduatoria->data_chiusura->format('d/m/Y H:i') }}.
        </p>
    </div>

<x-form-footer />
</body>
</html>
