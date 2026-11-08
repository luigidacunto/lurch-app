<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accedi senza password — Croce Rossa Italiana</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 min-h-screen">

    <div class="bg-red-700 text-white px-4 py-4 shadow">
        <div class="max-w-lg mx-auto">
            <p class="text-xs font-medium uppercase tracking-wide opacity-80">Croce Rossa Italiana</p>
            <h1 class="text-lg font-bold leading-tight mt-0.5">Accedi via email</h1>
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
                <p class="text-sm font-semibold text-gray-700">Inserisci l'email del tuo account pannello</p>
            </div>
            <form method="POST" action="{{ route('admin-login.invia') }}" class="px-5 py-4 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Email <span class="text-red-500">*</span></label>
                    <input type="email" name="email" required
                           value="{{ old('email') }}"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 @error('email') border-red-400 @enderror">
                    @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>

                <p class="text-xs text-gray-500 leading-relaxed">
                    Riceverai un'email con un codice e un link. Clicca sul link per accedere subito, oppure inserisci il codice qui sotto se stai leggendo l'email da un altro dispositivo o browser.
                </p>

                <button type="submit" class="w-full bg-red-700 text-white rounded-lg py-2.5 text-sm font-semibold hover:bg-red-800">
                    Ricevi link e codice di accesso
                </button>
            </form>
        </div>

        <p class="text-xs text-gray-500 text-center">
            Problemi a ricevere l'email? <a href="{{ url('/admin/login?password=1') }}" class="underline">Accedi con la password</a>
        </p>

        <p class="text-xs text-gray-500 text-center">
            Primo accesso? <a href="{{ route('guide.index') }}" class="underline">Guide all'uso del portale</a>
        </p>
    </div>

<x-form-footer />
</body>
</html>
