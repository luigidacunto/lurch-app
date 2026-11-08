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
            <form method="POST" action="{{ route('candidatura-staff.verifica', $evento->candidature_staff_uuid) }}" class="px-5 py-4 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Codice fiscale <span class="text-red-500">*</span></label>
                    <input type="text" name="codice_fiscale" maxlength="16" required
                           value="{{ old('codice_fiscale') }}"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm uppercase focus:outline-none focus:ring-2 focus:ring-red-500 @error('codice_fiscale') border-red-400 @enderror">
                    @error('codice_fiscale')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <label class="flex items-start gap-2.5">
                    <input type="checkbox" name="dichiarazione_esterno" value="1"
                           {{ old('dichiarazione_esterno') ? 'checked' : '' }}
                           class="mt-0.5 rounded border-gray-300 text-red-600 focus:ring-red-500">
                    <span class="text-sm text-gray-700">
                        Non faccio parte dell'associazione C.R.I. (docente esterno)
                    </span>
                </label>
                <button type="submit" class="w-full bg-red-700 text-white rounded-lg py-2.5 text-sm font-semibold hover:bg-red-800">
                    Continua
                </button>
            </form>
        </div>

        <p class="text-xs text-gray-500 text-center">
            Le candidature sono aperte dal {{ $evento->candidature_staff_apertura_at->format('d/m/Y H:i') }}
            al {{ $evento->candidature_staff_chiusura_at->format('d/m/Y H:i') }}.
        </p>
    </div>

<x-form-footer />
</body>
</html>
