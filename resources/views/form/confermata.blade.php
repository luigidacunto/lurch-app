<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preregistrazione confermata</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center px-4">
    <div class="max-w-sm w-full text-center space-y-5">

        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto">
            <svg class="w-8 h-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
            </svg>
        </div>

        <div>
            <h1 class="text-xl font-bold text-gray-900">Preregistrazione confermata</h1>
            <p class="text-sm text-gray-500 mt-1">
                {{ $partecipante->cognome }} {{ $partecipante->nome }}
            </p>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4 text-left space-y-1">
            <p class="text-xs text-gray-400 uppercase tracking-wide font-medium">Corso</p>
            <p class="text-sm font-semibold text-gray-900">{{ $corso->nome }}</p>
            <p class="text-xs text-gray-500">{{ $evento->nome }}</p>
            <p class="text-xs text-gray-400 mt-1">
                {{ $corso->data_inizio->format('d/m/Y') }}
                @if(!$corso->data_inizio->eq($corso->data_fine))
                    — {{ $corso->data_fine->format('d/m/Y') }}
                @endif
            </p>
        </div>

        @if(session('email_verificata'))
            <div class="bg-green-50 border border-green-300 rounded-xl px-4 py-3 text-sm text-green-800 text-center">
                Email verificata con successo!
            </div>
        @elseif($emailDaVerificare)
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 px-6 py-6 text-left">
                <p class="text-sm font-semibold text-gray-900">Conferma il tuo indirizzo email</p>
                <p class="text-xs text-gray-500 mt-1">
                    Questa verifica serve solo ad accertarci che l'indirizzo <strong>{{ $emailDaVerificare }}</strong> riceva
                    correttamente le email, così da poterti raggiungere per le comunicazioni relative al corso/evento.
                </p>
                <p class="text-xs text-gray-500 mt-2">
                    Ti abbiamo inviato un'email con un link e un codice di 6 cifre: clicca il link, oppure inserisci il codice qui sotto.
                </p>
                <p class="text-xs text-gray-500 mt-2">
                    Se non la ricevi entro la giornata, controlla la cartella spam/posta indesiderata.
                </p>

                @if($errors->any())
                    <div class="bg-red-50 border border-red-300 rounded-lg px-3 py-2 text-xs text-red-800 mt-3">
                        @foreach($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('form.verifica-email', $iscrizione->token) }}" class="mt-5 flex items-center gap-2">
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

        @if(session('doc_sostituito'))
            <div class="bg-green-50 border border-green-300 rounded-xl px-4 py-3 text-sm text-green-800 text-center">
                {{ session('doc_sostituito') }}
            </div>
        @elseif(session('doc_errore'))
            <div class="bg-amber-50 border border-amber-300 rounded-xl px-4 py-3 text-sm text-amber-800 text-center">
                {{ session('doc_errore') }}
            </div>
        @endif

        @if(($risposta?->ricevuta_path && $corso->pagamento_previsto) || ($risposta?->nullaosta_path && $richiedeNullaosta) || $corso->richiede_documento_aggiuntivo)
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-4">
                <p class="text-xs text-gray-400 uppercase tracking-wide font-medium mb-1">Documenti</p>
                @if($corso->pagamento_previsto)
                    <x-sostituzione-documento
                        slot="ricevuta" label="Ricevuta di pagamento" accept=".jpg,.jpeg,.png,.webp,.pdf"
                        :action="route('form.documenti.sostituisci', [$iscrizione->token, 'ricevuta'])"
                        :caricato="(bool) $risposta?->ricevuta_path"
                        :verificato="(bool) $risposta?->pagamento_verificato" />
                @endif
                @if($richiedeNullaosta)
                    <x-sostituzione-documento
                        slot="nullaosta" label="Nullaosta del Presidente" accept=".pdf"
                        :action="route('form.documenti.sostituisci', [$iscrizione->token, 'nullaosta'])"
                        :caricato="(bool) $risposta?->nullaosta_path"
                        :verificato="(bool) $risposta?->nullaosta_verificato" />
                @endif
                @if($corso->richiede_documento_aggiuntivo)
                    <x-sostituzione-documento
                        slot="documento_aggiuntivo" label="Documento aggiuntivo" accept=".pdf"
                        :action="route('form.documenti.sostituisci', [$iscrizione->token, 'documento_aggiuntivo'])"
                        :caricato="(bool) $risposta?->documento_aggiuntivo_path"
                        :verificato="(bool) $risposta?->documento_aggiuntivo_verificato"
                        :richiesto="true" :dicitura="$corso->dicitura_documento_aggiuntivo" />
                @endif
            </div>
        @endif

        <p class="text-xs text-gray-400">
            Ti aspettiamo all'evento. Puoi chiudere questa pagina.
        </p>

        <p class="text-xs text-gray-300 pt-2">Croce Rossa Italiana · Comitato Regionale Toscana</p>
    </div>
<x-form-footer />
</body>
</html>
