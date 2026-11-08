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

    <div class="max-w-lg mx-auto px-4 py-6 space-y-4">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 px-5 py-6 text-center">
            <p class="text-base font-semibold text-gray-900">Candidatura ricevuta!</p>
            <p class="text-sm text-gray-600 mt-1">
                La tua candidatura come staff per questo evento è stata registrata e sarà valutata dagli organizzatori,
                che ti contatteranno se necessario.
            </p>
        </div>

        @if(session('email_verificata'))
            <div class="bg-green-50 border border-green-300 rounded-xl px-4 py-3 text-sm text-green-800 text-center">
                Email verificata con successo!
            </div>
        @elseif($emailDaVerificare)
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 px-6 py-6">
                <p class="text-sm font-semibold text-gray-900">Conferma il tuo indirizzo email</p>
                <p class="text-xs text-gray-500 mt-1">
                    Questa verifica serve solo ad accertarci che l'indirizzo <strong>{{ $emailDaVerificare }}</strong> riceva
                    correttamente le email, così da poterti raggiungere in futuro per le comunicazioni relative al tuo
                    ruolo di staff.
                </p>
                <p class="text-xs text-gray-500 mt-2">
                    Ti abbiamo inviato un'email con un link e un codice di 6 cifre: clicca il link, oppure inserisci il codice qui sotto.
                </p>
                <p class="text-xs text-gray-500 mt-2">
                    Se non la ricevi entro la giornata, controlla la cartella spam/posta indesiderata, oppure contatta gli organizzatori dell'evento.
                </p>

                @if($errors->any())
                    <div class="bg-red-50 border border-red-300 rounded-lg px-3 py-2 text-xs text-red-800 mt-3">
                        @foreach($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('candidatura-staff.verifica-email', $evento->candidature_staff_uuid) }}" class="mt-5 flex items-center gap-2">
                    @csrf
                    <input type="text" name="codice" maxlength="6" inputmode="numeric" pattern="[0-9]{6}"
                           placeholder="000000" autocomplete="one-time-code"
                           class="w-32 text-center tracking-[0.3em] font-mono rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
                    <button type="submit" class="bg-red-700 text-white rounded-lg px-4 py-2 text-sm font-semibold hover:bg-red-800">
                        Verifica
                    </button>
                </form>
            </div>
        @endif
    </div>

<x-form-footer />
</body>
</html>
