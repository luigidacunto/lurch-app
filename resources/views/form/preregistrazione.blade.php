<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preregistrazione — {{ $corso->nome }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 min-h-screen">

    {{-- Header CRI --}}
    <div class="bg-red-700 text-white px-4 py-4 shadow">
        <div class="max-w-lg mx-auto">
            <p class="text-xs font-medium uppercase tracking-wide opacity-80">{{ $evento->nome }}</p>
            <h1 class="text-lg font-bold leading-tight mt-0.5">{{ $corso->nome }}</h1>
            @if($corso->numero_protocollo)
                <p class="text-xs opacity-60 mt-0.5 font-mono">{{ $corso->numero_protocollo }}</p>
            @endif
            <p class="text-xs opacity-75 mt-1">
                {{ $corso->data_inizio->format('d/m/Y') }}
                @if(!$corso->data_inizio->eq($corso->data_fine))
                    — {{ $corso->data_fine->format('d/m/Y') }}
                @endif
                @if($corso->sede ?? $evento->sede)
                    · {{ $corso->sede ?? $evento->sede }}
                @endif
            </p>
        </div>
    </div>

    <div class="max-w-lg mx-auto px-4 py-6 space-y-6">

        {{-- Banner anteprima --}}
        @if($preview)
            <div class="bg-amber-50 border border-amber-300 rounded-xl px-4 py-3 text-sm text-amber-800 font-medium">
                Modalità anteprima — le risposte non verranno salvate.
            </div>
        @endif

        {{-- Conferma dopo un tentativo di invio in anteprima: bene visibile, per non lasciare
             l'operatore col dubbio se sia stato inviato per davvero o no. --}}
        @if(session('preview_info'))
            <div class="bg-emerald-50 border border-emerald-300 rounded-xl px-4 py-3 text-sm text-emerald-800 font-semibold">
                ✓ {{ session('preview_info') }}
            </div>
        @endif

        {{-- Banner sola lettura --}}
        @if($readonly)
            <div class="bg-blue-50 border border-blue-200 rounded-xl px-4 py-3 text-sm text-blue-900">
                <p class="font-semibold flex items-center gap-1.5">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="flex-shrink:0">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                    </svg>
                    Form già compilato — sola lettura
                </p>
                <p class="mt-1 text-xs text-blue-700">Per richiedere modifiche contatta la segreteria di formazione di riferimento.</p>
            </div>
        @endif

        <form method="POST" action="{{ route('form.store', $iscrizione->token) }}" class="space-y-5" id="form-prereg" enctype="multipart/form-data">
            @csrf
            @if($preview)
                <input type="hidden" name="preview" value="1">
            @endif

            {{-- 0. Dati personali --}}
            @php
                $selRegionale = old('comitato_regionale_id', $partecipante->comitato_regionale_id);
                $selLocale    = old('comitato_locale_id',    $partecipante->comitato_locale_id);
                // Se il comitato è già noto, il campo parte bloccato (valore in chiaro, link
                // personale con UUID: nessun rischio a mostrarlo) per evitare click/typing per
                // sbaglio — si sblocca solo su richiesta esplicita. Un redisplay dopo errore di
                // validazione riparte già sbloccato.
                $comitatoSbloccato = ! $selRegionale || old('comitato_regionale_id') !== null || $errors->has('comitato_regionale_id');
            @endphp
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100">
                    <p class="text-sm font-semibold text-gray-700">Dati personali</p>
                </div>
                <div class="px-5 py-4 space-y-4">
                    <div>
                        <p class="text-xs font-medium text-gray-600 mb-0.5">Cognome e nome</p>
                        <p class="text-base font-semibold text-gray-900">{{ $partecipante->cognome }} {{ $partecipante->nome }}</p>
                    </div>
                    @if($readonly)
                        <div>
                            <p class="text-xs font-medium text-gray-600 mb-0.5">Comitato regionale</p>
                            <p class="text-sm text-gray-900">{{ $partecipante->comitatoRegionale?->nome ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-600 mb-0.5">Comitato locale</p>
                            <p class="text-sm text-gray-900">{{ $partecipante->comitatoLocale?->nome ?? '—' }}</p>
                        </div>
                    @else
                        @if($selRegionale)
                            <div id="comitato-bloccato-wrap" class="space-y-3" style="{{ $comitatoSbloccato ? 'display:none' : '' }}">
                                <div>
                                    <p class="text-xs font-medium text-gray-600 mb-0.5">Comitato regionale</p>
                                    <p class="text-sm text-gray-900">{{ $partecipante->comitatoRegionale?->nome ?? '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium text-gray-600 mb-0.5">Comitato locale</p>
                                    <p class="text-sm text-gray-900">{{ $partecipante->comitatoLocale?->nome ?? '—' }}</p>
                                </div>
                                <button type="button" onclick="sbloccaComitato()"
                                        class="text-xs font-medium text-red-700 hover:text-red-800">Modifica comitato</button>
                                <p class="text-xs text-gray-400">Se il dato mostrato è corretto non serve modificarlo.</p>
                            </div>
                        @endif
                        <div id="comitato-sblocco-wrap" class="space-y-4" style="{{ $comitatoSbloccato ? '' : 'display:none' }}">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1.5">Comitato regionale <span class="text-red-500">*</span></label>
                                <select name="comitato_regionale_id" id="sel-regionale"
                                        {{ $comitatoSbloccato ? 'required' : 'disabled' }}
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 @error('comitato_regionale_id') border-red-400 @enderror">
                                    <option value="">— Seleziona —</option>
                                    @foreach($comitatiRegionali as $reg)
                                        <option value="{{ $reg->id }}" {{ $selRegionale == $reg->id ? 'selected' : '' }}>{{ $reg->nome }}</option>
                                    @endforeach
                                </select>
                                @error('comitato_regionale_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1.5">Comitato locale <span class="text-red-500">*</span></label>
                                <select name="comitato_locale_id" id="sel-locale"
                                        {{ $comitatoSbloccato ? 'required' : 'disabled' }}
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 @error('comitato_locale_id') border-red-400 @enderror">
                                    <option value="">— prima seleziona il regionale —</option>
                                </select>
                                @error('comitato_locale_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                            @if($selRegionale)
                                <button type="button" id="comitato-annulla" onclick="annullaComitato()"
                                        class="text-xs font-medium text-gray-500 hover:text-gray-700" style="display:none">Annulla modifica</button>
                            @endif
                        </div>
                        @if(! $comitatoSbloccato)
                            <input type="hidden" name="comitato_regionale_id" id="comitato-regionale-hidden" value="{{ $selRegionale }}">
                            <input type="hidden" name="comitato_locale_id" id="comitato-locale-hidden" value="{{ $selLocale }}">
                        @endif
                        <script>
                        var selReg     = document.getElementById('sel-regionale');
                        var selLoc     = document.getElementById('sel-locale');
                        var localiComitati = {!! $comitatiLocaliJson !!};
                        var initLocale = {{ $selLocale ?? 'null' }};

                        function aggiornaLocali(regionaleId, seleziona) {
                            selLoc.innerHTML = '';
                            var opt0 = document.createElement('option');
                            opt0.value = '';
                            opt0.textContent = regionaleId ? '— Seleziona comitato locale —' : '— prima seleziona il regionale —';
                            selLoc.appendChild(opt0);
                            if (!regionaleId || !localiComitati[regionaleId]) return;
                            localiComitati[regionaleId].forEach(function (l) {
                                var opt = document.createElement('option');
                                opt.value = l.id;
                                opt.textContent = l.nome;
                                if (l.id == seleziona) opt.selected = true;
                                selLoc.appendChild(opt);
                            });
                        }

                        aggiornaLocali(selReg.value, initLocale);
                        selReg.addEventListener('change', function () { aggiornaLocali(this.value, null); });

                        function sbloccaComitato() {
                            document.getElementById('comitato-bloccato-wrap').style.display = 'none';
                            document.getElementById('comitato-sblocco-wrap').style.display = 'block';
                            selReg.disabled = false;
                            selReg.required = true;
                            selLoc.disabled = false;
                            selLoc.required = true;
                            var hidReg = document.getElementById('comitato-regionale-hidden');
                            var hidLoc = document.getElementById('comitato-locale-hidden');
                            if (hidReg) hidReg.disabled = true;
                            if (hidLoc) hidLoc.disabled = true;
                            var btnAnnulla = document.getElementById('comitato-annulla');
                            if (btnAnnulla) btnAnnulla.style.display = 'inline-block';
                        }

                        function annullaComitato() {
                            var hidReg = document.getElementById('comitato-regionale-hidden');
                            var hidLoc = document.getElementById('comitato-locale-hidden');
                            if (hidReg) { hidReg.disabled = false; selReg.value = hidReg.value; }
                            aggiornaLocali(selReg.value, hidLoc ? hidLoc.value : null);
                            if (hidLoc) hidLoc.disabled = false;
                            selReg.disabled = true;
                            selReg.required = false;
                            selLoc.disabled = true;
                            selLoc.required = false;
                            document.getElementById('comitato-sblocco-wrap').style.display = 'none';
                            document.getElementById('comitato-bloccato-wrap').style.display = '';
                            var btnAnnulla = document.getElementById('comitato-annulla');
                            if (btnAnnulla) btnAnnulla.style.display = 'none';
                        }
                        </script>
                    @endif
                </div>
            </div>

            {{-- 1. Contatti --}}
            @php
                $telefonoSalvato  = old('telefono',          $risposta?->risposte['telefono']          ?? $partecipante->telefono          ?? '');
                $prefissoSalvato  = old('prefisso_telefono', $risposta?->risposte['prefisso_telefono'] ?? $partecipante->prefisso_telefono ?? '+39');
                $emailSalvata     = old('email',             $risposta?->risposte['email']             ?? $partecipante->email             ?? '');
                $prefissi = [
                    '+39'  => '🇮🇹 +39 — Italia',
                    '+41'  => '🇨🇭 +41 — Svizzera',
                    '+33'  => '🇫🇷 +33 — Francia',
                    '+34'  => '🇪🇸 +34 — Spagna',
                    '+49'  => '🇩🇪 +49 — Germania',
                    '+43'  => '🇦🇹 +43 — Austria',
                    '+32'  => '🇧🇪 +32 — Belgio',
                    '+31'  => '🇳🇱 +31 — Paesi Bassi',
                    '+44'  => '🇬🇧 +44 — Regno Unito',
                    '+351' => '🇵🇹 +351 — Portogallo',
                    '+352' => '🇱🇺 +352 — Lussemburgo',
                    '+353' => '🇮🇪 +353 — Irlanda',
                    '+355' => '🇦🇱 +355 — Albania',
                    '+385' => '🇭🇷 +385 — Croazia',
                    '+386' => '🇸🇮 +386 — Slovenia',
                    '+380' => '🇺🇦 +380 — Ucraina',
                    '+1'   => '🇺🇸 +1 — USA / Canada',
                ];
                // Stesso schema di blocco del comitato sopra: parte bloccato se il dato c'è già,
                // sbloccabile su richiesta. Qui il testo resta in chiaro (link personale) — a
                // differenza di graduatorie/candidature non serve mascherarlo.
                $emailSbloccata    = ! $emailSalvata    || old('email')    !== null || $errors->has('email');
                $telefonoSbloccato = ! $telefonoSalvato || old('telefono') !== null || $errors->has('telefono');
            @endphp
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100">
                    <p class="text-sm font-semibold text-gray-700">Contatti</p>
                    <p class="text-xs text-gray-400 mt-0.5">Verifica o aggiorna i tuoi dati. Eventuali modifiche aggiorneranno l'anagrafica.</p>
                </div>
                <div class="px-5 py-4 space-y-4">
                    {{-- Email --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1.5">Indirizzo email <span class="text-red-500">*</span></label>
                        <div class="flex items-center gap-2">
                            <input type="email"
                                   name="email"
                                   id="email-input"
                                   value="{{ $emailSalvata }}"
                                   placeholder="tuaemail@esempio.it"
                                   required
                                   {{ $readonly ? 'disabled' : '' }}
                                   {{ (! $readonly && ! $emailSbloccata) ? 'readonly' : '' }}
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 disabled:bg-gray-50 disabled:text-gray-500 {{ (! $readonly && ! $emailSbloccata) ? 'bg-gray-50 text-gray-500' : '' }}">
                            @if(! $readonly)
                                <button type="button" id="email-input-modifica" onclick="sbloccaCampoTesto('email-input')"
                                        class="shrink-0 text-xs font-medium text-red-700 hover:text-red-800 whitespace-nowrap px-2"
                                        style="{{ $emailSbloccata ? 'display:none' : '' }}">Modifica</button>
                                @if($emailSalvata)
                                    <button type="button" id="email-input-annulla" onclick="annullaCampoTesto('email-input')"
                                            class="shrink-0 text-xs font-medium text-gray-500 hover:text-gray-700 whitespace-nowrap px-2"
                                            style="display:none">Annulla</button>
                                @endif
                            @endif
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Serve per inviarti informazioni e comunicazioni relative all'evento/corso.{{ $emailSalvata ? ' Se il dato mostrato è corretto non serve modificarlo.' : '' }}</p>
                    </div>
                    {{-- Telefono --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1.5">Numero di telefono</label>
                        <div class="flex gap-2">
                            <select name="prefisso_telefono"
                                    {{ $readonly ? 'disabled' : '' }}
                                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 shrink-0 w-44 disabled:bg-gray-50 disabled:text-gray-500">
                                @foreach($prefissi as $codice => $etichetta)
                                    <option value="{{ $codice }}" {{ $prefissoSalvato === $codice ? 'selected' : '' }}>{{ $etichetta }}</option>
                                @endforeach
                            </select>
                            <input type="tel"
                                   name="telefono"
                                   id="telefono-input"
                                   inputmode="numeric"
                                   pattern="[0-9\s\-]{5,20}"
                                   value="{{ $telefonoSalvato }}"
                                   placeholder="es. 3801234567"
                                   {{ $readonly ? 'disabled' : '' }}
                                   {{ (! $readonly && ! $telefonoSbloccato) ? 'readonly' : '' }}
                                   class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 disabled:bg-gray-50 disabled:text-gray-500 {{ (! $readonly && ! $telefonoSbloccato) ? 'bg-gray-50 text-gray-500' : '' }}">
                            @if(! $readonly)
                                <button type="button" id="telefono-input-modifica" onclick="sbloccaCampoTesto('telefono-input')"
                                        class="shrink-0 text-xs font-medium text-red-700 hover:text-red-800 whitespace-nowrap px-2"
                                        style="{{ $telefonoSbloccato ? 'display:none' : '' }}">Modifica</button>
                                @if($telefonoSalvato)
                                    <button type="button" id="telefono-input-annulla" onclick="annullaCampoTesto('telefono-input')"
                                            class="shrink-0 text-xs font-medium text-gray-500 hover:text-gray-700 whitespace-nowrap px-2"
                                            style="display:none">Annulla</button>
                                @endif
                            @endif
                        </div>
                        @if($telefonoSalvato)
                            <p class="text-xs text-gray-400 mt-1">Se il dato mostrato è corretto non serve modificarlo.</p>
                        @endif
                    </div>
                </div>
            </div>
            @if(! $readonly)
                <script>
                function sbloccaCampoTesto(id) {
                    var el = document.getElementById(id);
                    el.dataset.valoreOriginale = el.value;
                    el.readOnly = false;
                    el.classList.remove('bg-gray-50', 'text-gray-500');
                    el.focus();
                    el.select();
                    var btnModifica = document.getElementById(id + '-modifica');
                    if (btnModifica) btnModifica.style.display = 'none';
                    var btnAnnulla = document.getElementById(id + '-annulla');
                    if (btnAnnulla) btnAnnulla.style.display = 'inline-block';
                }

                function annullaCampoTesto(id) {
                    var el = document.getElementById(id);
                    el.value = el.dataset.valoreOriginale ?? el.value;
                    el.readOnly = true;
                    el.classList.add('bg-gray-50', 'text-gray-500');
                    var btnModifica = document.getElementById(id + '-modifica');
                    if (btnModifica) btnModifica.style.display = 'inline-block';
                    var btnAnnulla = document.getElementById(id + '-annulla');
                    if (btnAnnulla) btnAnnulla.style.display = 'none';
                }
                </script>
            @endif

            {{-- 2. Ricevuta di pagamento --}}
            @if($corso->pagamento_previsto)
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 px-5 py-4 space-y-3">
                    <div>
                        <p class="text-sm font-semibold text-gray-700">Ricevuta di pagamento</p>
                        <p class="text-xs text-gray-400 mt-0.5">Allega la ricevuta o uno screenshot del pagamento effettuato.</p>
                    </div>

                    <div class="overflow-hidden">
                        @if($risposta?->ricevuta_path)
                            <div class="flex items-center gap-2 bg-green-50 border border-green-200 rounded-lg px-3 py-2 {{ $readonly ? '' : 'mb-2' }}">
                                <svg class="w-3.5 h-3.5 text-green-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                </svg>
                                <p class="text-xs text-green-800">{{ $readonly ? 'Ricevuta caricata.' : 'Ricevuta già caricata. Carica un nuovo file solo se vuoi sostituirla.' }}</p>
                            </div>
                        @elseif(!$readonly)
                            <p class="text-xs text-red-500 font-medium mb-2">Obbligatoria per completare la preregistrazione</p>
                        @endif
                        @if(!$readonly)
                            <label for="ricevuta-input"
                                   class="flex flex-col items-center justify-center gap-2 w-full border-2 border-dashed border-gray-300 rounded-xl px-4 py-6 cursor-pointer hover:border-red-400 hover:bg-red-50 transition-colors text-center"
                                   id="ricevuta-label">
                                <svg class="w-8 h-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
                                </svg>
                                <span class="text-sm font-semibold text-red-700" id="ricevuta-btn-text">Tocca per allegare la ricevuta</span>
                                <span class="text-xs text-gray-400">JPG · PNG · PDF · max 10 MB</span>
                            </label>
                            <input type="file"
                                   name="ricevuta"
                                   id="ricevuta-input"
                                   accept=".jpg,.jpeg,.png,.webp,.pdf"
                                   style="position:absolute;width:0;height:0;opacity:0;overflow:hidden;">
                            <p class="text-xs text-gray-500 mt-2 text-center hidden" id="ricevuta-nome-file"></p>
                        @endif
                    </div>

                    {{-- Data del pagamento --}}
                    <div>
                        <label for="data_pagamento" class="block text-sm font-medium text-gray-700 mb-1">
                            Data del pagamento
                            @if(!$readonly)<span class="text-red-500 ml-0.5">*</span>@endif
                        </label>
                        @if($readonly)
                            <p class="text-sm text-gray-800">
                                {{ $risposta?->data_pagamento ? $risposta->data_pagamento->format('d/m/Y') : '—' }}
                            </p>
                        @else
                            <input type="date"
                                   name="data_pagamento"
                                   id="data_pagamento"
                                   value="{{ old('data_pagamento', $risposta?->data_pagamento?->format('Y-m-d')) }}"
                                   max="{{ date('Y-m-d') }}"
                                   required
                                   class="block w-full rounded-xl border border-gray-300 px-3 py-2 text-sm focus:border-red-400 focus:outline-none focus:ring-1 focus:ring-red-400 @error('data_pagamento') border-red-400 @enderror">
                            @error('data_pagamento')
                                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        @endif
                    </div>
                </div>
            @endif

            {{-- Nullaosta regionale --}}
            @if($richiedeNullaosta && (!$readonly || $risposta?->nullaosta_path))
                <div class="bg-white rounded-2xl shadow-sm border border-amber-200 px-5 py-4 space-y-3">
                    <div>
                        <p class="text-sm font-semibold text-gray-700">Nullaosta del Presidente del Comitato Locale</p>
                        <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mt-1.5 leading-snug">
                            Sei iscritto a un comitato di una <strong>regione diversa</strong> da quella organizzatrice del corso. Allega in formato PDF il nullaosta firmato dal Presidente del tuo Comitato Locale, in cui siano specificati:
                        </p>
                        <ul class="text-xs text-amber-800 mt-2 space-y-1 pl-1">
                            <li class="flex gap-1.5"><span class="text-amber-400 shrink-0">–</span><span>I tuoi dati: <strong>cognome, nome e comitato</strong> di appartenenza</span></li>
                            <li class="flex gap-1.5"><span class="text-amber-400 shrink-0">–</span><span>I dati del presidente: <strong>cognome, nome e comitato</strong></span></li>
                            <li class="flex gap-1.5"><span class="text-amber-400 shrink-0">–</span><span>Il <strong>corso</strong> a cui sei iscritto e le relative <strong>date</strong></span></li>
                        </ul>
                    </div>
                    <div>
                        @if($risposta?->nullaosta_path)
                            <div class="flex items-center gap-2 bg-green-50 border border-green-200 rounded-lg px-3 py-2 {{ $readonly ? '' : 'mb-2' }}">
                                <svg class="w-3.5 h-3.5 text-green-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                </svg>
                                <p class="text-xs text-green-800">{{ $readonly ? 'Nullaosta caricato.' : 'Nullaosta già caricato. Carica un nuovo file solo se vuoi sostituirlo.' }}</p>
                            </div>
                        @elseif(!$readonly)
                            <p class="text-xs text-red-500 font-medium mb-2">Obbligatorio per completare la preregistrazione</p>
                        @endif
                        @if(!$readonly)
                            <label for="nullaosta-input"
                                   class="flex flex-col items-center justify-center gap-2 w-full border-2 border-dashed border-amber-300 rounded-xl px-4 py-6 cursor-pointer hover:border-amber-500 hover:bg-amber-50 transition-colors text-center"
                                   id="nullaosta-label">
                                <svg class="w-8 h-8 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
                                </svg>
                                <span class="text-sm font-semibold text-amber-700" id="nullaosta-btn-text">Tocca per allegare il nullaosta</span>
                                <span class="text-xs text-gray-400">Solo PDF · max 10 MB</span>
                            </label>
                            <input type="file"
                                   name="nullaosta"
                                   id="nullaosta-input"
                                   accept=".pdf"
                                   style="position:absolute;width:0;height:0;opacity:0;overflow:hidden;">
                            <p class="text-xs text-gray-500 mt-2 text-center hidden" id="nullaosta-nome-file"></p>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Documento aggiuntivo richiesto dal corso (dicitura libera dell'admin) --}}
            @if($corso->richiede_documento_aggiuntivo && (!$readonly || $risposta?->documento_aggiuntivo_path))
                <div class="bg-white rounded-2xl shadow-sm border border-amber-200 px-5 py-4 space-y-3">
                    <div>
                        <p class="text-sm font-semibold text-gray-700">Documento aggiuntivo</p>
                        <p class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mt-1.5 leading-snug whitespace-pre-line break-words">{{ $corso->dicitura_documento_aggiuntivo }}</p>
                    </div>
                    <div>
                        @if($risposta?->documento_aggiuntivo_path)
                            <div class="flex items-center gap-2 bg-green-50 border border-green-200 rounded-lg px-3 py-2 {{ $readonly ? '' : 'mb-2' }}">
                                <svg class="w-3.5 h-3.5 text-green-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                </svg>
                                <p class="text-xs text-green-800">{{ $readonly ? 'Documento caricato.' : 'Documento già caricato. Carica un nuovo file solo se vuoi sostituirlo.' }}</p>
                            </div>
                        @elseif(!$readonly)
                            <p class="text-xs text-red-500 font-medium mb-2">Obbligatorio per completare la preregistrazione</p>
                        @endif
                        @if(!$readonly)
                            <label for="documento_aggiuntivo-input"
                                   class="flex flex-col items-center justify-center gap-2 w-full border-2 border-dashed border-amber-300 rounded-xl px-4 py-6 cursor-pointer hover:border-amber-500 hover:bg-amber-50 transition-colors text-center"
                                   id="documento_aggiuntivo-label">
                                <svg class="w-8 h-8 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
                                </svg>
                                <span class="text-sm font-semibold text-amber-700" id="documento_aggiuntivo-btn-text">Tocca per allegare il documento</span>
                                <span class="text-xs text-gray-400">Solo PDF · max 10 MB</span>
                            </label>
                            <input type="file"
                                   name="documento_aggiuntivo"
                                   id="documento_aggiuntivo-input"
                                   accept=".pdf"
                                   style="position:absolute;width:0;height:0;opacity:0;overflow:hidden;">
                            <p class="text-xs text-gray-500 mt-2 text-center hidden" id="documento_aggiuntivo-nome-file"></p>
                        @endif
                    </div>
                </div>
            @endif

            {{-- 2. Presenze e vitto --}}
            @if($sezioni->isNotEmpty())
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-5 py-3 border-b border-gray-100">
                        <p class="text-sm font-semibold text-gray-700">Presenze e vitto</p>
                        <p class="text-xs text-gray-400 mt-0.5">Indica cosa prevedi di consumare durante il corso.</p>
                        <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-4 py-2 mt-2 leading-snug">
                            Selezionando anche un solo pasto sarà richiesto il <strong>consenso al trattamento dei dati sulla salute</strong> (allergie e intolleranze), obbligatorio per garantire la tua sicurezza alimentare.
                        </p>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @foreach($sezioni as $sezione)
                            @php $chiave = $sezione['tipo'] . '_' . $sezione['data']; @endphp
                            <div class="px-5 py-4">
                                <label class="flex items-center justify-between gap-3 cursor-pointer select-none">
                                    <span class="text-sm text-gray-800">
                                        {{ $sezione['label'] }}
                                        @if($sezione['obbligatorio']) <span class="text-red-500">*</span> @endif
                                    </span>
                                    <input type="checkbox"
                                           name="{{ $chiave }}"
                                           value="1"
                                           data-pasto-checkbox
                                           {{ $readonly ? 'disabled' : '' }}
                                           class="sr-only"
                                           {{ old($chiave, data_get($risposta, 'risposte.'.$chiave)) ? 'checked' : '' }}>
                                    <span class="toggle-switch"></span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- 3. Allergie e regime alimentare --}}
            @php
                // Priorità: old() → risposta form → anagrafica soci
                $allergeniSalvati = old('allergeni', $risposta?->risposte['allergeni'] ?? $partecipante?->allergeni ?? []);
                $dieteSalvate     = old('diete',     $risposta?->risposte['diete']     ?? $partecipante?->diete     ?? []);
            @endphp
            {{-- Visibile solo se è selezionato almeno un pasto (allineato ai form PWA) --}}
            <div id="sezione-allergeni" class="hidden bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100">
                    <p class="text-sm font-semibold text-gray-700">Allergie e regime alimentare</p>
                    <p class="text-xs text-gray-400 mt-0.5">Seleziona tutto ciò che ti riguarda. Questi dati sono usati esclusivamente per la gestione dei pasti.</p>
                </div>
                <div class="px-5 py-4 space-y-5">
                    {{-- Allergeni --}}
                    @if($allergeniList->isNotEmpty())
                        <div>
                            <p class="text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">Allergeni <span class="font-normal normal-case text-gray-400">— spunta quelli che ti riguardano</span></p>
                            <div class="grid grid-cols-2 gap-2">
                                @foreach($allergeniList as $opzione)
                                    <label class="relative flex min-w-0" title="{{ $opzione->etichetta }}">
                                        <input type="checkbox"
                                               name="allergeni[]"
                                               value="{{ $opzione->nome }}"
                                               {{ $readonly ? 'disabled' : '' }}
                                               class="peer sr-only"
                                               {{ in_array($opzione->nome, $allergeniSalvati) ? 'checked' : '' }}>
                                        <span class="w-full min-w-0 flex items-center px-2.5 py-1.5 pr-6 rounded-full border border-gray-300 bg-white cursor-pointer select-none transition-colors peer-checked:bg-blue-50 peer-checked:border-blue-400 peer-checked:text-blue-700 peer-checked:font-medium peer-disabled:opacity-60 peer-disabled:cursor-default"><span class="truncate min-w-0 text-xs text-gray-700">{{ $opzione->etichetta }}</span></span>
                                        <svg class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-blue-600 opacity-0 peer-checked:opacity-100 transition-opacity" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Intolleranze --}}
                    @if($intolleranzeList->isNotEmpty())
                        <div class="pt-4 border-t border-gray-100">
                            <p class="text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">Intolleranze</p>
                            <div class="grid grid-cols-2 gap-2">
                                @foreach($intolleranzeList as $opzione)
                                    <label class="relative flex min-w-0" title="{{ $opzione->etichetta }}">
                                        <input type="checkbox"
                                               name="diete[]"
                                               value="{{ $opzione->nome }}"
                                               {{ $readonly ? 'disabled' : '' }}
                                               class="peer sr-only"
                                               {{ in_array($opzione->nome, $dieteSalvate) ? 'checked' : '' }}>
                                        <span class="w-full min-w-0 flex items-center px-2.5 py-1.5 pr-6 rounded-full border border-gray-300 bg-white cursor-pointer select-none transition-colors peer-checked:bg-blue-50 peer-checked:border-blue-400 peer-checked:text-blue-700 peer-checked:font-medium peer-disabled:opacity-60 peer-disabled:cursor-default"><span class="truncate min-w-0 text-xs text-gray-700">{{ $opzione->etichetta }}</span></span>
                                        <svg class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-blue-600 opacity-0 peer-checked:opacity-100 transition-opacity" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Regime alimentare --}}
                    @if($dieteList->isNotEmpty())
                        <div class="pt-4 border-t border-gray-100">
                            <p class="text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">Regime alimentare <span class="font-normal normal-case text-gray-400">— scelte etiche, religiose o di salute</span></p>
                            <div class="grid grid-cols-2 gap-2">
                                @foreach($dieteList as $opzione)
                                    <label class="relative flex min-w-0" title="{{ $opzione->etichetta }}">
                                        <input type="checkbox"
                                               name="diete[]"
                                               value="{{ $opzione->nome }}"
                                               {{ $readonly ? 'disabled' : '' }}
                                               class="peer sr-only"
                                               {{ in_array($opzione->nome, $dieteSalvate) ? 'checked' : '' }}>
                                        <span class="w-full min-w-0 flex items-center px-2.5 py-1.5 pr-6 rounded-full border border-gray-300 bg-white cursor-pointer select-none transition-colors peer-checked:bg-blue-50 peer-checked:border-blue-400 peer-checked:text-blue-700 peer-checked:font-medium peer-disabled:opacity-60 peer-disabled:cursor-default"><span class="truncate min-w-0 text-xs text-gray-700">{{ $opzione->etichetta }}</span></span>
                                        <svg class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-blue-600 opacity-0 peer-checked:opacity-100 transition-opacity" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Esclusioni --}}
                    @if($esclusioniList->isNotEmpty())
                        <div class="pt-4 border-t border-gray-100">
                            <p class="text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">Esclusioni alimentari</p>
                            <div class="grid grid-cols-2 gap-2">
                                @foreach($esclusioniList as $opzione)
                                    <label class="relative flex min-w-0" title="{{ $opzione->etichetta }}">
                                        <input type="checkbox"
                                               name="diete[]"
                                               value="{{ $opzione->nome }}"
                                               {{ $readonly ? 'disabled' : '' }}
                                               class="peer sr-only"
                                               {{ in_array($opzione->nome, $dieteSalvate) ? 'checked' : '' }}>
                                        <span class="w-full min-w-0 flex items-center px-2.5 py-1.5 pr-6 rounded-full border border-gray-300 bg-white cursor-pointer select-none transition-colors peer-checked:bg-blue-50 peer-checked:border-blue-400 peer-checked:text-blue-700 peer-checked:font-medium peer-disabled:opacity-60 peer-disabled:cursor-default"><span class="truncate min-w-0 text-xs text-gray-700">{{ $opzione->etichetta }}</span></span>
                                        <svg class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-blue-600 opacity-0 peer-checked:opacity-100 transition-opacity" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Altro (allergia molto specifica non in lista) --}}
                    <div class="pt-4 border-t border-gray-100">
                        <label class="block text-xs font-semibold text-gray-600 mb-1 uppercase tracking-wide">
                            Altro
                            <span class="font-normal normal-case text-gray-400">— solo se non coperto dalle voci sopra</span>
                        </label>
                        <p class="text-xs text-gray-400 mb-2">Indica solo allergie o intolleranze <strong>alimentari molto specifiche</strong> non presenti nelle liste (es. "kiwi", "fragole"). Riguarda solo il cibo che ti verrà servito: non serve indicare qui allergie non alimentari (pollini, farmaci, lattice, ecc.). Per le esigenze comuni usa le checkbox. Se non hai allergie lascia vuoto. <strong>Max 50 caratteri.</strong></p>
                        <input type="text"
                               name="note_extra"
                               maxlength="50"
                               placeholder="es. kiwi, fragole…"
                               {{ $readonly ? 'disabled' : '' }}
                               value="{{ old('note_extra', $risposta?->risposte['note_extra'] ?? $partecipante?->note_alimentari ?? '') }}"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 disabled:bg-gray-50 disabled:text-gray-500">
                        <p class="text-xs text-gray-400 mt-1.5">Per esigenze particolari che esulano dalla gestione pasti, scrivi alla segreteria del campo.</p>
                    </div>
                </div>
            </div>

            {{-- 4. Informativa dati personali (Art. 13 GDPR) --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 px-5 py-4 space-y-3">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox"
                           name="privacy_accettata"
                           value="1"
                           {{ $readonly ? 'disabled' : 'required' }}
                           class="mt-0.5 w-5 h-5 shrink-0 rounded border-gray-300 text-red-600 focus:ring-red-500 disabled:opacity-60"
                           {{ old('privacy_accettata', $risposta?->privacy_accettata) ? 'checked' : '' }}>
                    <span class="text-sm text-gray-700 leading-snug">
                        Ho letto e accetto il trattamento dei dati personali per la gestione dell'evento, ai sensi del Reg. UE 2016/679 (GDPR).
                        <span class="text-red-500">*</span>
                    </span>
                </label>
                @if($privacyInformativa || $privacyInformativaPdf)
                    <div class="pl-8">
                        <button type="button"
                                onclick="toggleAccordion('acc-informativa', this)"
                                class="inline-flex items-center gap-1 text-xs text-red-600 hover:text-red-700 font-medium">
                            <svg class="w-3 h-3 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                            </svg>
                            Leggi l'informativa completa
                        </button>
                        <div id="acc-informativa" class="hidden mt-2 space-y-2">
                            @if($privacyInformativa)
                                <div data-privacy-text class="text-xs text-gray-700 leading-relaxed whitespace-pre-line max-h-64 overflow-y-auto border border-gray-100 rounded-lg p-3 bg-gray-50">{{ $privacyInformativa }}</div>
                            @endif
                            @if($privacyInformativaPdf)
                                <a href="{{ Storage::url($privacyInformativaPdf) }}" target="_blank"
                                   class="inline-flex items-center gap-1 text-xs text-red-600 underline">
                                    📄 Scarica PDF
                                </a>
                            @endif
                        </div>
                    </div>
                @endif

                <label class="flex items-start gap-3 cursor-pointer pt-3 border-t border-gray-100">
                    <input type="checkbox"
                           name="consenso_gaia_accettata"
                           value="1"
                           {{ $readonly ? 'disabled' : 'required' }}
                           class="mt-0.5 w-5 h-5 shrink-0 rounded border-gray-300 text-red-600 focus:ring-red-500 disabled:opacity-60"
                           {{ old('consenso_gaia_accettata', $risposta?->consenso_gaia_accettata) ? 'checked' : '' }}>
                    <span class="text-sm text-gray-700 leading-snug">
                        Acconsento all'importazione e consultazione delle mie qualifiche da GAIA per le finalità di cui al punto 3.2 dell'informativa sopra (verifica dei requisiti minimi previsti dal corso).
                        <span class="text-red-500">*</span>
                    </span>
                </label>
            </div>

            {{-- 5. Consenso dati sulla salute (Art. 9 GDPR) — visibile solo con almeno un pasto selezionato --}}
            <div id="blocco-consenso-salute" class="hidden bg-white rounded-2xl shadow-sm border border-gray-100 px-5 py-4 space-y-3">
                <div id="avviso-pasti-obbligatorio" class="hidden bg-amber-50 border border-amber-200 rounded-lg px-4 py-2 text-xs text-amber-800">
                    Hai selezionato uno o più pasti: il consenso al trattamento dei dati sulla salute è <strong>obbligatorio</strong>.
                </div>
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox"
                           name="consenso_salute_accettata"
                           id="consenso_salute"
                           value="1"
                           {{ $readonly ? 'disabled' : '' }}
                           class="mt-0.5 w-5 h-5 shrink-0 rounded border-gray-300 text-red-600 focus:ring-red-500 disabled:opacity-60"
                           {{ old('consenso_salute_accettata', $risposta?->consenso_salute_accettata) ? 'checked' : '' }}>
                    <span class="text-sm text-gray-700 leading-snug">
                        Acconsento al trattamento dei miei dati sulla salute (allergie e intolleranze) per la gestione dei pasti durante l'evento.
                        <span class="text-xs text-gray-400 block mt-0.5">Art. 9, par. 2, lett. a) GDPR — consenso esplicito</span>
                    </span>
                </label>
                <div class="pl-8">
                    <button type="button"
                            onclick="toggleAccordion('acc-salute', this)"
                            class="inline-flex items-center gap-1 text-xs text-red-600 hover:text-red-700 font-medium">
                        <svg class="w-3 h-3 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                        </svg>
                        Perché raccogliamo questi dati
                    </button>
                    <div id="acc-salute" class="hidden mt-2">
                        <p class="text-xs text-gray-500 leading-relaxed bg-gray-50 border border-gray-100 rounded-lg p-3">
                            I dati relativi alla salute (allergie, intolleranze e regime alimentare) saranno comunicati esclusivamente al personale di cucina o al fornitore della ristorazione per garantire la tua sicurezza alimentare durante l'evento.
                        </p>
                    </div>
                </div>
            </div>

            {{-- 6. Consenso foto/video (Art. 6.1.a GDPR) — punto 3.4 dell'informativa unica --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 px-5 py-4 space-y-3">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="checkbox"
                           name="foto_video_accettata"
                           value="1"
                           {{ $readonly ? 'disabled' : 'required' }}
                           class="mt-0.5 w-5 h-5 shrink-0 rounded border-gray-300 text-red-600 focus:ring-red-500 disabled:opacity-60"
                           {{ old('foto_video_accettata', $risposta?->foto_video_accettata) ? 'checked' : '' }}>
                    <span class="text-sm text-gray-700 leading-snug">
                        Autorizzo la ripresa fotografica e video durante l'evento per fini istituzionali e comunicativi della Croce Rossa Italiana (punto 3.4 dell'informativa sopra).
                        <span class="text-xs text-gray-400 block mt-0.5">Art. 6, par. 1, lett. a) GDPR</span>
                        <span class="text-red-500">*</span>
                    </span>
                </label>
            </div>

            {{-- Errori validazione --}}
            @if($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-sm text-red-700">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($readonly)
                <div class="bg-gray-100 border border-gray-200 rounded-xl px-4 py-3 text-center text-sm text-gray-500">
                    Form in sola lettura — per modifiche contatta la segreteria di formazione.
                </div>
            @elseif($preview)
                {{-- Bottone chiaramente diverso in anteprima (colore/testo, come il form staff):
                     non deve poter sembrare un normale invio a chi lo clicca per errore. --}}
                <button type="submit"
                        class="w-full bg-amber-500 hover:bg-amber-600 active:bg-amber-700 text-white font-semibold py-3.5 rounded-xl transition-colors text-sm shadow-sm">
                    Anteprima (non salva)
                </button>
            @else
                <button type="submit"
                        class="w-full bg-red-700 hover:bg-red-800 active:bg-red-900 text-white font-semibold py-3.5 rounded-xl transition-colors text-sm shadow-sm">
                    {{ $giaSalvata ? 'Aggiorna risposte' : 'Invia preregistrazione' }}
                </button>
            @endif

            <p class="text-center text-xs text-gray-400 pb-4">
                Croce Rossa Italiana · Comitato Regionale Toscana
            </p>
        </form>

    </div>

    <script>
        // Formatta i testi privacy: titoli numerati, elenchi puntati, paragrafi
        document.querySelectorAll('[data-privacy-text]').forEach(el => {
            const lines  = el.textContent.split('\n');
            let   html   = '';
            let   inList = false;

            lines.forEach(raw => {
                const line = raw.trimEnd();

                if (!line.trim()) {
                    if (inList) { html += '</ul>'; inList = false; }
                    return; // salta righe vuote (fungono da spaziatura)
                }

                // Titolo principale TUTTO MAIUSCOLO (es. "INFORMATIVA SUL TRATTAMENTO…")
                if (/^[A-ZÀÈÉÌÒÙÜ\s\d.,–\-\/()]+$/.test(line.trim()) && line.trim().length > 4) {
                    if (inList) { html += '</ul>'; inList = false; }
                    html += `<p class="font-semibold text-gray-800 mt-3 mb-1 text-xs">${line.trim()}</p>`;
                    return;
                }

                // Sezione numerata (es. "1. TITOLARE DEL TRATTAMENTO")
                if (/^\d+\.\s/.test(line.trim())) {
                    if (inList) { html += '</ul>'; inList = false; }
                    html += `<p class="font-semibold text-gray-800 mt-3 mb-1 text-xs">${line.trim()}</p>`;
                    return;
                }

                // Sotto-sezione lettera (es. "a) Dati comuni")
                if (/^[a-z]\)\s/.test(line.trim())) {
                    if (inList) { html += '</ul>'; inList = false; }
                    html += `<p class="font-medium text-gray-700 mt-1.5 mb-0.5 text-xs">${line.trim()}</p>`;
                    return;
                }

                // Voce lista (inizia con - o •)
                if (/^[-•]\s/.test(line.trim())) {
                    if (!inList) { html += '<ul class="list-none space-y-0.5 my-1">'; inList = true; }
                    html += `<li class="flex gap-1.5 text-gray-600"><span class="text-gray-400 shrink-0">–</span><span>${line.trim().replace(/^[-•]\s/, '')}</span></li>`;
                    return;
                }

                // Paragrafo normale
                if (inList) { html += '</ul>'; inList = false; }
                html += `<p class="text-gray-600 mt-1 leading-relaxed">${line.trim()}</p>`;
            });

            if (inList) html += '</ul>';
            el.innerHTML = html;
        });

        function toggleAccordion(contentId, btn) {
            const content = document.getElementById(contentId);
            const icon    = btn.querySelector('svg');
            const isOpen  = !content.classList.contains('hidden');
            content.classList.toggle('hidden', isOpen);
            if (icon) icon.style.transform = isOpen ? '' : 'rotate(180deg)';
        }

        const consensoSalute     = document.getElementById('consenso_salute');
        const avvisoObbligatorio = document.getElementById('avviso-pasti-obbligatorio');
        const sezioneAllergeni   = document.getElementById('sezione-allergeni');
        const bloccoSalute       = document.getElementById('blocco-consenso-salute');

        // Raccoglie tutti i controlli pasto (checkbox pasti + select pernotto)
        function haSelezionatoPasto() {
            const checkboxPasti = document.querySelectorAll('[data-pasto-checkbox]');
            for (const cb of checkboxPasti) {
                if (cb.checked) return true;
            }
            return false;
        }

        function aggiornaObbligatorietaPasti() {
            const hasPasto = haSelezionatoPasto();
            consensoSalute.required = hasPasto;
            if (!hasPasto && consensoSalute) consensoSalute.checked = false;
            if (avvisoObbligatorio) avvisoObbligatorio.classList.toggle('hidden', !hasPasto);
            if (sezioneAllergeni) sezioneAllergeni.classList.toggle('hidden', !hasPasto);
            if (bloccoSalute)     bloccoSalute.classList.toggle('hidden', !hasPasto);
        }

        // Ascolta modifiche ai pasti
        document.querySelectorAll('[data-pasto-checkbox]').forEach(cb => {
            cb.addEventListener('change', aggiornaObbligatorietaPasti);
        });

        // Inizializzazione (per il caso in cui la pagina venga ricaricata con old input)
        aggiornaObbligatorietaPasti();

        // Upload ricevuta — mostra nome file selezionato
        const ricevutaInput = document.getElementById('ricevuta-input');
        const ricevutaLabel = document.getElementById('ricevuta-label');
        const ricevutaBtnText = document.getElementById('ricevuta-btn-text');
        const ricevutaNome = document.getElementById('ricevuta-nome-file');

        if (ricevutaInput) {
            ricevutaInput.addEventListener('change', function () {
                const file = this.files[0];
                if (file) {
                    ricevutaBtnText.textContent = 'Tocca per cambiare file';
                    ricevutaLabel.classList.add('border-green-400', 'bg-green-50');
                    ricevutaLabel.classList.remove('border-gray-300', 'hover:border-red-400', 'hover:bg-red-50');
                    ricevutaNome.textContent = '✓ ' + file.name;
                    ricevutaNome.classList.remove('hidden');
                }
            });
        }

        const nullaostaInput   = document.getElementById('nullaosta-input');
        const nullaostaLabel   = document.getElementById('nullaosta-label');
        const nullaostaBtnText = document.getElementById('nullaosta-btn-text');
        const nullaostaNome    = document.getElementById('nullaosta-nome-file');

        if (nullaostaInput) {
            nullaostaInput.addEventListener('change', function () {
                const file = this.files[0];
                if (file) {
                    nullaostaBtnText.textContent = 'Tocca per cambiare file';
                    nullaostaLabel.classList.add('border-green-400', 'bg-green-50');
                    nullaostaLabel.classList.remove('border-amber-300', 'hover:border-amber-500', 'hover:bg-amber-50');
                    nullaostaNome.textContent = '✓ ' + file.name;
                    nullaostaNome.classList.remove('hidden');
                }
            });
        }
        const docAggInput   = document.getElementById('documento_aggiuntivo-input');
        const docAggLabel   = document.getElementById('documento_aggiuntivo-label');
        const docAggBtnText = document.getElementById('documento_aggiuntivo-btn-text');
        const docAggNome    = document.getElementById('documento_aggiuntivo-nome-file');

        if (docAggInput) {
            docAggInput.addEventListener('change', function () {
                const file = this.files[0];
                if (file) {
                    docAggBtnText.textContent = 'Tocca per cambiare file';
                    docAggLabel.classList.add('border-green-400', 'bg-green-50');
                    docAggLabel.classList.remove('border-amber-300', 'hover:border-amber-500', 'hover:bg-amber-50');
                    docAggNome.textContent = '✓ ' + file.name;
                    docAggNome.classList.remove('hidden');
                }
            });
        }
</script>

<x-form-footer />
</body>
</html>
