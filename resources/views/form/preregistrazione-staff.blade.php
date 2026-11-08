<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preregistrazione Staff — {{ $evento->nome }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 min-h-screen">

    {{-- Header --}}
    <div class="bg-red-700 text-white px-4 py-4 shadow">
        <div class="max-w-lg mx-auto">
            <p class="text-xs font-medium uppercase tracking-wide opacity-80">Staff — Preregistrazione</p>
            <h1 class="text-lg font-bold leading-tight mt-0.5">{{ $evento->nome }}</h1>
            <p class="text-xs opacity-75 mt-1">
                {{ $evento->data_inizio->format('d/m/Y') }}
                @if(!$evento->data_inizio->eq($evento->data_fine))
                    — {{ $evento->data_fine->format('d/m/Y') }}
                @endif
                @if($evento->sede) · {{ $evento->sede }} @endif
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
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="flex-shrink:0"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                    Form già compilato — sola lettura
                </p>
                <p class="mt-1 text-xs text-blue-700">Per richiedere modifiche contatta la segreteria dell'evento.</p>
            </div>
        @endif

        @php
            $risposte        = $risposta?->risposte ?? [];
            $dispSalvate     = collect($risposte['disponibilita'] ?? $dispPrefill);

            // Tipo unico per slot (formazione ha priorità se, su dati storici, risultassero
            // entrambi salvati per la stessa fascia — da quando il form è a scelta singola
            // non può più succedere per le nuove risposte, ma i dati vecchi vanno letti lo stesso).
            function staffSlotTipo(array $dispSalvate, string $data, string $porzione): ?string {
                $voci = collect($dispSalvate)->where('data', $data)->where('porzione', $porzione);
                if ($voci->firstWhere('tipo', 'formazione')) return 'formazione';
                if ($voci->firstWhere('tipo', 'logistica')) return 'logistica';
                return null;
            }
        @endphp

        <form method="POST" action="{{ route('form-staff.store', $iscrizione->token) }}" enctype="multipart/form-data" novalidate class="space-y-6">
        @csrf

        {{-- Dati identificativi --}}
        @php
            $selRegionale = old('comitato_regionale_id', $partecipante->comitato_regionale_id);
            $selLocale    = old('comitato_locale_id',    $partecipante->comitato_locale_id);
            $comitatoSbloccato = ! $selRegionale || old('comitato_regionale_id') !== null || $errors->has('comitato_regionale_id');
        @endphp
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100">
                <p class="text-sm font-semibold text-gray-700">Dati personali</p>
            </div>
            <div class="px-5 py-4 space-y-4">
                <div>
                    <p class="text-xs text-gray-400">Cognome e nome</p>
                    <p class="text-base font-semibold text-gray-900">{{ $partecipante->cognome }} {{ $partecipante->nome }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400">Codice fiscale</p>
                    <p class="font-mono text-sm text-gray-700">{{ $partecipante->codice_fiscale }}</p>
                </div>
                @if($readonly)
                    <div>
                        <p class="text-xs text-gray-400">Comitato regionale</p>
                        <p class="text-sm text-gray-700">{{ $partecipante->comitatoRegionale?->nome ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Comitato locale</p>
                        <p class="text-sm text-gray-700">{{ $partecipante->comitatoLocale?->nome ?? '—' }}</p>
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

        {{-- Contatti --}}
        @php
            $emailSalvata      = old('email', $partecipante->email ?? '');
            $telefonoSalvato   = old('telefono', $partecipante->telefono ?? '');
            $emailSbloccata    = ! $emailSalvata    || old('email')    !== null || $errors->has('email');
            $telefonoSbloccato = ! $telefonoSalvato || old('telefono') !== null || $errors->has('telefono');
        @endphp
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 px-5 py-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide font-medium mb-3">Contatti</p>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="email">Email <span class="text-red-500">*</span></label>
                    @if($readonly)
                        <p class="text-sm text-gray-900">{{ $risposta?->risposte['email'] ?? $partecipante->email ?? '—' }}</p>
                    @else
                        <div class="flex items-center gap-2">
                            <input type="email" id="email" name="email" required
                                value="{{ $emailSalvata }}"
                                {{ $emailSbloccata ? '' : 'readonly' }}
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500 @error('email') border-red-400 @enderror {{ $emailSbloccata ? '' : 'bg-gray-50 text-gray-500' }}"
                                placeholder="es. nome.cognome@cri.it">
                            <button type="button" id="email-modifica" onclick="sbloccaCampoTesto('email')"
                                    class="shrink-0 text-xs font-medium text-red-700 hover:text-red-800 whitespace-nowrap px-2"
                                    style="{{ $emailSbloccata ? 'display:none' : '' }}">Modifica</button>
                            @if($emailSalvata)
                                <button type="button" id="email-annulla" onclick="annullaCampoTesto('email')"
                                        class="shrink-0 text-xs font-medium text-gray-500 hover:text-gray-700 whitespace-nowrap px-2"
                                        style="display:none">Annulla</button>
                            @endif
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Serve per inviarti informazioni e comunicazioni relative all'evento.{{ $emailSalvata ? ' Se il dato mostrato è corretto non serve modificarlo.' : '' }}</p>
                        @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    @endif
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Numero di cellulare</label>
                    @if($readonly)
                        <p class="text-sm text-gray-900">
                            {{ $risposta?->risposte['prefisso_telefono'] ?? $partecipante->prefisso_telefono ?? '+39' }}
                            {{ $risposta?->risposte['telefono'] ?? $partecipante->telefono ?? '—' }}
                        </p>
                    @else
                        <div class="flex gap-2">
                            <select name="prefisso_telefono" class="border border-gray-300 rounded-lg px-2 py-2 text-sm w-24 focus:ring-2 focus:ring-red-500">
                                @foreach(['+39'=>'🇮🇹 +39', '+41'=>'🇨🇭 +41', '+33'=>'🇫🇷 +33', '+49'=>'🇩🇪 +49', '+44'=>'🇬🇧 +44'] as $pfx => $label)
                                    <option value="{{ $pfx }}" {{ old('prefisso_telefono', $partecipante->prefisso_telefono ?? '+39') === $pfx ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            <input type="tel" id="telefono" name="telefono"
                                value="{{ $telefonoSalvato }}"
                                {{ $telefonoSbloccato ? '' : 'readonly' }}
                                class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 @error('telefono') border-red-400 @enderror {{ $telefonoSbloccato ? '' : 'bg-gray-50 text-gray-500' }}"
                                placeholder="3xx xxxxxxx">
                            <button type="button" id="telefono-modifica" onclick="sbloccaCampoTesto('telefono')"
                                    class="shrink-0 text-xs font-medium text-red-700 hover:text-red-800 whitespace-nowrap px-2"
                                    style="{{ $telefonoSbloccato ? 'display:none' : '' }}">Modifica</button>
                            @if($telefonoSalvato)
                                <button type="button" id="telefono-annulla" onclick="annullaCampoTesto('telefono')"
                                        class="shrink-0 text-xs font-medium text-gray-500 hover:text-gray-700 whitespace-nowrap px-2"
                                        style="display:none">Annulla</button>
                            @endif
                        </div>
                        @if($telefonoSalvato)
                            <p class="text-xs text-gray-400 mt-1">Se il dato mostrato è corretto non serve modificarlo.</p>
                        @endif
                        @error('telefono')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
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

        {{-- Disponibilità --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 px-5 py-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide font-medium mb-1">Disponibilità operative</p>
            <p class="text-xs text-gray-400 mb-3">Per ogni fascia indica il ruolo principale in cui sarai presente: una sola tipologia per fascia. Include anche il pomeriggio prima e la mattina dopo per chi può essere presente per montaggio/smontaggio (solo come logistica).</p>

            @if(!$readonly)
            <div class="flex gap-2 mb-3 flex-wrap">
                <button type="button" onclick="selezionaTuttiTipo('formazione')"
                        class="inline-flex items-center gap-1.5 text-xs font-medium text-violet-700 bg-violet-50 border border-violet-200 rounded-lg px-3 py-1.5 hover:bg-violet-100 transition">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Tutta docenza
                </button>
                <button type="button" onclick="selezionaTuttiTipo('logistica')"
                        class="inline-flex items-center gap-1.5 text-xs font-medium text-teal-700 bg-teal-50 border border-teal-200 rounded-lg px-3 py-1.5 hover:bg-teal-100 transition">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Tutta logistica
                </button>
                <button type="button" onclick="deselezionaTuttiDisponibilita()"
                        class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-500 bg-gray-50 border border-gray-200 rounded-lg px-3 py-1.5 hover:bg-gray-100 transition">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Deseleziona tutto
                </button>
            </div>
            @endif

            <div class="space-y-4">
                @foreach(collect($slots)->groupBy('data') as $data => $slotsGiorno)
                    @php $labelGiorno = \Carbon\Carbon::parse($data)->translatedFormat('l j F'); @endphp

                    <div class="border-t border-gray-100 pt-3 first:border-t-0 first:pt-0">
                        <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">{{ $labelGiorno }}</p>

                        <div class="grid grid-cols-[1fr_auto_auto] gap-x-4 gap-y-2.5 items-center">
                            <div></div>
                            <p class="text-[11px] font-medium text-violet-700 text-center">Docenza</p>
                            <p class="text-[11px] font-medium text-teal-700 text-center">Logistica</p>

                            @foreach($slotsGiorno as $slot)
                                @php
                                    $porz     = $slot['porzione'] === 'mattina' ? 'Mattina' : 'Pomeriggio';
                                    $nomeCampo = 'disponibilita[' . $slot['chiave'] . ']';
                                    $tipoSalvato = staffSlotTipo($dispSalvate->toArray(), $slot['data'], $slot['porzione']);
                                    $valSlot  = $readonly
                                        ? $tipoSalvato
                                        : old('disponibilita.' . $slot['chiave'], $tipoSalvato);
                                @endphp

                                <p class="text-sm font-medium text-gray-900">{{ $porz }}</p>

                                <div class="flex justify-center">
                                    @if($slot['extra'])
                                        <span class="text-gray-300">—</span>
                                    @elseif(!$readonly)
                                        <label class="inline-flex">
                                            <input type="radio" name="{{ $nomeCampo }}" value="formazione" {{ $valSlot === 'formazione' ? 'checked' : '' }}
                                                aria-label="Docenza — {{ $labelGiorno }} {{ $porz }}" class="sr-only">
                                            <span class="toggle-switch toggle-violet"></span>
                                        </label>
                                    @else
                                        <span class="toggle-switch toggle-violet {{ $valSlot === 'formazione' ? 'is-on' : '' }}"></span>
                                    @endif
                                </div>

                                <div class="flex justify-center">
                                    @if(!$readonly)
                                        <label class="inline-flex">
                                            <input type="{{ $slot['extra'] ? 'checkbox' : 'radio' }}" name="{{ $nomeCampo }}" value="logistica" {{ $valSlot === 'logistica' ? 'checked' : '' }}
                                                aria-label="Logistica — {{ $labelGiorno }} {{ $porz }}" class="sr-only">
                                            <span class="toggle-switch toggle-teal"></span>
                                        </label>
                                    @else
                                        <span class="toggle-switch toggle-teal {{ $valSlot === 'logistica' ? 'is-on' : '' }}"></span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-3 pt-3 border-t border-gray-100">
                <p class="text-xs text-gray-400 leading-relaxed">
                    <strong>Docenza</strong> — docenti e direttori di corso.
                    <strong>Logistica</strong> — cucina, pulizie, movimentazione e ogni altra mansione operativa del campo.
                    Una sola tipologia per fascia; negli slot di montaggio/smontaggio (segnati con "—" per la docenza) è disponibile solo la logistica.
                </p>
            </div>
        </div>

        {{-- Nullaosta del Presidente del Comitato Locale — solo per chi ha indicato almeno
             uno slot come Logistica (i docenti seguono un iter separato non gestito qui) --}}
        @php
            $dispTipiIniziali = old('disponibilita') !== null
                ? collect(old('disponibilita'))
                : $dispSalvate->pluck('tipo');
            $hasLogisticaIniziale = $dispTipiIniziali->contains('logistica');
        @endphp
        <div class="bg-white rounded-2xl shadow-sm border border-amber-200 px-5 py-4 space-y-3 {{ $hasLogisticaIniziale ? '' : 'hidden' }}" id="nullaosta-card">
            <div>
                <p class="text-sm font-semibold text-gray-700">Nullaosta del Presidente del Comitato Locale</p>
                <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mt-1.5 leading-snug">
                    Hai indicato disponibilità come <strong>Logistica</strong>: allega il nullaosta firmato dal Presidente del tuo Comitato Locale che autorizza la tua partecipazione in questo ruolo.
                </p>
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
                    <p class="text-xs text-red-500 font-medium mb-2" id="nullaosta-avviso-obbligatorio">Obbligatorio per completare la preregistrazione come Logistica</p>
                @endif
                @if(!$readonly)
                    <label for="nullaosta-input"
                           class="flex flex-col items-center justify-center gap-2 w-full border-2 border-dashed border-amber-300 rounded-xl px-4 py-6 cursor-pointer hover:border-amber-500 hover:bg-amber-50 transition-colors text-center"
                           id="nullaosta-label">
                        <svg class="w-8 h-8 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
                        </svg>
                        <span class="text-sm font-semibold text-amber-700" id="nullaosta-btn-text">Tocca per allegare il nullaosta</span>
                        <span class="text-xs text-gray-400">PDF o JPG · max 10 MB</span>
                    </label>
                    <input type="file"
                           name="nullaosta"
                           id="nullaosta-input"
                           accept=".pdf,.jpg,.jpeg"
                           style="position:absolute;width:0;height:0;opacity:0;overflow:hidden;">
                    <p class="text-xs text-gray-500 mt-2 text-center hidden" id="nullaosta-nome-file"></p>
                    <p class="text-xs text-gray-500 mt-2 leading-snug">
                        Serve un modello? Scarica la
                        <a href="{{ route('form-staff.modello-nullaosta', $iscrizione->token) }}" target="_blank" rel="noopener" class="text-blue-600 underline">Presa visione del Presidente precompilata</a>
                        con i dati dell'evento e i tuoi già inseriti. È facoltativo: il Presidente può firmare anche un proprio documento.
                    </p>
                @endif
            </div>
        </div>

        {{-- Presenze e vitto --}}
        @if($sezioni->isNotEmpty())
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100">
                <p class="text-sm font-semibold text-gray-700">Presenze e vitto</p>
                <p class="text-xs text-gray-400 mt-0.5">Indica cosa prevedi di consumare durante l'evento.</p>
                <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-4 py-2 mt-2 leading-snug">
                    Selezionando anche un solo pasto sarà richiesto il <strong>consenso al trattamento dei dati sulla salute</strong> (allergie e intolleranze).
                </p>
                @if(!$readonly)
                <div class="flex gap-2 mt-2 flex-wrap">
                    <button type="button" onclick="selezionaTuttiPasti(true)"
                            class="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-1.5 hover:bg-emerald-100 transition">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Seleziona tutti
                    </button>
                    <button type="button" onclick="selezionaTuttiPasti(false)"
                            class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-500 bg-gray-50 border border-gray-200 rounded-lg px-3 py-1.5 hover:bg-gray-100 transition">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Deseleziona tutti
                    </button>
                </div>
                @endif
            </div>
            <div class="divide-y divide-gray-100">
                @foreach($sezioni as $sezione)
                    @php $chiave = $sezione['tipo'] . '_' . $sezione['data']; @endphp
                    <div class="px-5 py-4">
                        <label class="flex items-center justify-between gap-3 cursor-pointer select-none">
                            <span class="text-sm text-gray-800">{{ $sezione['label'] }}</span>
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

        {{-- Allergie e regime alimentare --}}
        @php
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
                @if($allergeniList->isNotEmpty())
                <div>
                    <p class="text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">Allergeni <span class="font-normal normal-case text-gray-400">— spunta quelli che ti riguardano</span></p>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach($allergeniList as $opzione)
                            <label class="relative flex min-w-0" title="{{ $opzione->etichetta }}">
                                <input type="checkbox" name="allergeni[]" value="{{ $opzione->nome }}"
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

                @if($intolleranzeList->isNotEmpty())
                <div class="pt-4 border-t border-gray-100">
                    <p class="text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">Intolleranze</p>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach($intolleranzeList as $opzione)
                            <label class="relative flex min-w-0" title="{{ $opzione->etichetta }}">
                                <input type="checkbox" name="diete[]" value="{{ $opzione->nome }}"
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

                @if($dieteList->isNotEmpty())
                <div class="pt-4 border-t border-gray-100">
                    <p class="text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">Regime alimentare <span class="font-normal normal-case text-gray-400">— scelte etiche, religiose o di salute</span></p>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach($dieteList as $opzione)
                            <label class="relative flex min-w-0" title="{{ $opzione->etichetta }}">
                                <input type="checkbox" name="diete[]" value="{{ $opzione->nome }}"
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

                @if($esclusioniList->isNotEmpty())
                <div class="pt-4 border-t border-gray-100">
                    <p class="text-xs font-semibold text-gray-600 mb-2 uppercase tracking-wide">Esclusioni alimentari</p>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach($esclusioniList as $opzione)
                            <label class="relative flex min-w-0" title="{{ $opzione->etichetta }}">
                                <input type="checkbox" name="diete[]" value="{{ $opzione->nome }}"
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
                           value="{{ old('note_extra', $risposta?->risposte['note_extra'] ?? '') }}"
                           class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-transparent disabled:bg-gray-50 disabled:text-gray-500">
                    <p class="text-xs text-gray-400 mt-1.5">Per esigenze particolari che esulano dalla gestione pasti, scrivi alla segreteria del campo.</p>
                </div>
            </div>
        </div>

        {{-- Informativa dati personali (Art. 13 GDPR) --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 px-5 py-4 space-y-3">
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="privacy_accettata" value="1"
                       {{ $readonly ? 'disabled' : 'required' }}
                       class="mt-0.5 w-5 h-5 shrink-0 rounded border-gray-300 text-red-600 focus:ring-red-500 disabled:opacity-60"
                       {{ old('privacy_accettata', $risposta?->privacy_accettata) ? 'checked' : '' }}>
                <span class="text-sm text-gray-700 leading-snug">
                    Ho letto e accetto il trattamento dei dati personali per la gestione dell'evento, ai sensi del Reg. UE 2016/679 (GDPR).
                    <span class="text-red-500">*</span>
                </span>
            </label>
            @error('privacy_accettata')
                <p class="text-xs text-red-600 pl-8">{{ $message }}</p>
            @enderror
            @if($privacyInformativa || $privacyInformativaPdf)
                <div class="pl-8">
                    <button type="button" onclick="toggleAccordion('acc-informativa', this)"
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
        </div>

        {{-- Consenso dati sulla salute (Art. 9 GDPR) — visibile solo con almeno un pasto selezionato --}}
        <div id="blocco-consenso-salute" class="hidden bg-white rounded-2xl shadow-sm border border-gray-100 px-5 py-4 space-y-3">
            <div id="avviso-pasti-obbligatorio" class="hidden bg-amber-50 border border-amber-200 rounded-lg px-4 py-2 text-xs text-amber-800">
                Hai selezionato uno o più pasti: il consenso al trattamento dei dati sulla salute è <strong>obbligatorio</strong>.
            </div>
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="consenso_salute_accettata" id="consenso_salute" value="1"
                       {{ $readonly ? 'disabled' : '' }}
                       class="mt-0.5 w-5 h-5 shrink-0 rounded border-gray-300 text-red-600 focus:ring-red-500 disabled:opacity-60"
                       {{ old('consenso_salute_accettata', $risposta?->consenso_salute_accettata) ? 'checked' : '' }}>
                <span class="text-sm text-gray-700 leading-snug">
                    Acconsento al trattamento dei miei dati sulla salute (allergie e intolleranze) per la gestione dei pasti durante l'evento.
                    <span class="text-xs text-gray-400 block mt-0.5">Art. 9, par. 2, lett. a) GDPR — consenso esplicito</span>
                </span>
            </label>
            @error('consenso_salute_accettata')
                <p class="text-xs text-red-600 pl-8">{{ $message }}</p>
            @enderror
            <div class="pl-8">
                <button type="button" onclick="toggleAccordion('acc-salute', this)"
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

        {{-- Consenso foto/video (Art. 6.1.a GDPR) — punto 3.4 dell'informativa unica --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 px-5 py-4 space-y-3">
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="foto_video_accettata" value="1"
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

        {{-- Pulsante invio --}}
        @if(!$readonly)
            @if($preview)
                <button type="submit" name="preview" value="1"
                    class="w-full bg-amber-500 text-white font-semibold py-3 px-6 rounded-xl text-sm hover:bg-amber-600 transition">
                    Anteprima (non salva)
                </button>
            @else
                <button type="submit"
                    class="w-full bg-red-700 text-white font-bold py-3 px-6 rounded-xl text-base hover:bg-red-800 active:scale-95 transition">
                    Invia preregistrazione
                </button>
            @endif
        @endif

        </form>

    </div>

    <p class="text-center text-xs text-gray-300 py-6">Croce Rossa Italiana · Gestione Staff</p>

<script>
    // Formatta i testi privacy: titoli numerati, elenchi puntati, paragrafi
    document.querySelectorAll('[data-privacy-text]').forEach(el => {
        const lines  = el.textContent.split('\n');
        let   html   = '';
        let   inList = false;
        lines.forEach(raw => {
            const line = raw.trimEnd();
            if (!line.trim()) { if (inList) { html += '</ul>'; inList = false; } return; }
            if (/^[A-ZÀÈÉÌÒÙÜ\s\d.,–\-\/()]+$/.test(line.trim()) && line.trim().length > 4) {
                if (inList) { html += '</ul>'; inList = false; }
                html += `<p class="font-semibold text-gray-800 mt-3 mb-1 text-xs">${line.trim()}</p>`; return;
            }
            if (/^\d+\.\s/.test(line.trim())) {
                if (inList) { html += '</ul>'; inList = false; }
                html += `<p class="font-semibold text-gray-800 mt-3 mb-1 text-xs">${line.trim()}</p>`; return;
            }
            if (/^[a-z]\)\s/.test(line.trim())) {
                if (inList) { html += '</ul>'; inList = false; }
                html += `<p class="font-medium text-gray-700 mt-1.5 mb-0.5 text-xs">${line.trim()}</p>`; return;
            }
            if (/^[-•]\s/.test(line.trim())) {
                if (!inList) { html += '<ul class="list-none space-y-0.5 my-1">'; inList = true; }
                html += `<li class="flex gap-1.5 text-gray-600"><span class="text-gray-400 shrink-0">–</span><span>${line.trim().replace(/^[-•]\s/, '')}</span></li>`; return;
            }
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

    function haSelezionatoPasto() {
        for (const cb of document.querySelectorAll('[data-pasto-checkbox]')) {
            if (cb.checked) return true;
        }
        return false;
    }

    function aggiornaObbligatorietaPasti() {
        const hasPasto = haSelezionatoPasto();
        if (consensoSalute) consensoSalute.required = hasPasto;
        if (!hasPasto && consensoSalute) consensoSalute.checked = false;
        if (avvisoObbligatorio) avvisoObbligatorio.classList.toggle('hidden', !hasPasto);
        if (sezioneAllergeni) sezioneAllergeni.classList.toggle('hidden', !hasPasto);
        if (bloccoSalute)     bloccoSalute.classList.toggle('hidden', !hasPasto);
    }

    document.querySelectorAll('[data-pasto-checkbox]').forEach(cb => {
        cb.addEventListener('change', aggiornaObbligatorietaPasti);
    });

    aggiornaObbligatorietaPasti();

    const nullaostaCard  = document.getElementById('nullaosta-card');
    const nullaostaInput = document.getElementById('nullaosta-input');
    const nullaostaLabel = document.getElementById('nullaosta-label');
    const nullaostaBtnText = document.getElementById('nullaosta-btn-text');
    const nullaostaNome  = document.getElementById('nullaosta-nome-file');

    function haSelezionatoLogistica() {
        for (const el of document.querySelectorAll('input[name^="disponibilita["][value="logistica"]')) {
            if (el.checked) return true;
        }
        return false;
    }

    function aggiornaObbligatorietaNullaosta() {
        const hasLogistica = haSelezionatoLogistica();
        if (nullaostaCard) nullaostaCard.classList.toggle('hidden', !hasLogistica);
        if (nullaostaInput) nullaostaInput.required = hasLogistica && !@json((bool) $risposta?->nullaosta_path);
    }

    document.querySelectorAll('input[name^="disponibilita["]').forEach(function (input) {
        input.addEventListener('change', aggiornaObbligatorietaNullaosta);
    });

    aggiornaObbligatorietaNullaosta();

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

    function selezionaTuttiTipo(tipo) {
        document.querySelectorAll('input[name^="disponibilita["][value="' + tipo + '"]').forEach(cb => {
            cb.checked = true;
        });
        aggiornaObbligatorietaNullaosta();
    }

    function deselezionaTuttiDisponibilita() {
        document.querySelectorAll('input[name^="disponibilita["]').forEach(cb => {
            cb.checked = false;
        });
        aggiornaObbligatorietaNullaosta();
    }

    function selezionaTuttiPasti(stato) {
        document.querySelectorAll('[data-pasto-checkbox]').forEach(cb => {
            cb.checked = stato;
        });
        aggiornaObbligatorietaPasti();
    }

    // I radio "disponibilita[...]" per slot devono poter tornare a "nessuna scelta" per
    // correggere un errore: un radio nativo non si deseleziona ricliccandolo. Ricordiamo
    // lo stato prima del click (pointerdown, copre mouse e touch) e, se era già selezionato,
    // lo deselezioniamo dopo che il browser lo ha (ri)attivato.
    document.querySelectorAll('input[name^="disponibilita["]').forEach(function (input) {
        // pointerdown va sull'etichetta visibile (l'input reale è sr-only, il click sul suo
        // "contenitore" non genera pointerdown sull'input stesso, solo il click forwardato).
        var label = input.closest('label');
        if (label) {
            label.addEventListener('pointerdown', function () {
                input.dataset.eraSelezionato = input.checked ? '1' : '0';
            });
        }
        input.addEventListener('click', function () {
            if (input.dataset.eraSelezionato === '1') {
                input.checked = false;
            }
        });
    });
</script>

<x-form-footer />
</body>
</html>
