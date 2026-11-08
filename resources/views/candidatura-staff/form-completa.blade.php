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

        @if($dichiarazioneEsterno)
            <div class="bg-amber-50 border border-amber-300 rounded-xl px-4 py-3 text-sm text-amber-800">
                Hai dichiarato di non far parte dell'associazione. Compila i dati richiesti: la tua candidatura
                sarà valutata dagli organizzatori.
            </div>
        @elseif(! $socio)
            <div class="bg-amber-50 border border-amber-300 rounded-xl px-4 py-3 text-sm text-amber-800">
                Il codice fiscale inserito non risulta tra i soci registrati. Compila i dati richiesti: la tua candidatura
                sarà valutata dagli organizzatori.
            </div>
        @endif

        @if($errors->any())
            <div class="bg-red-50 border border-red-300 rounded-xl px-4 py-3 text-sm text-red-800">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('candidatura-staff.salva', $evento->candidature_staff_uuid) }}" class="space-y-5">
            @csrf

            @php
                $selRegionale = old('comitato_regionale_id', $socio->comitato_regionale_id ?? null);
                $selLocale    = old('comitato_locale_id',    $socio->comitato_locale_id ?? null);
                $comitatoSbloccato = ! $selRegionale || old('comitato_regionale_id') !== null || $errors->has('comitato_regionale_id');
            @endphp

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100">
                    <p class="text-sm font-semibold text-gray-700">Dati personali</p>
                </div>
                <div class="px-5 py-4 space-y-4">
                    <div>
                        <p class="text-xs font-medium text-gray-600 mb-0.5">Codice fiscale</p>
                        <p class="text-sm font-mono text-gray-900">{{ $codiceFiscale }}</p>
                    </div>

                    @if($socio)
                        <div>
                            <p class="text-xs font-medium text-gray-600 mb-0.5">Cognome e nome</p>
                            <p class="text-base font-semibold text-gray-900">{{ $socio->cognome }} {{ $socio->nome }}</p>
                        </div>
                    @else
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1.5">Nome <span class="text-red-500">*</span></label>
                                <input type="text" name="nome" required value="{{ old('nome') }}"
                                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 @error('nome') border-red-400 @enderror">
                                @error('nome')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1.5">Cognome <span class="text-red-500">*</span></label>
                                <input type="text" name="cognome" required value="{{ old('cognome') }}"
                                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 @error('cognome') border-red-400 @enderror">
                                @error('cognome')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    @endif

                    @php
                        $mostraEmailReale    = old('email') !== null || $errors->has('email');
                        $mostraTelefonoReale = old('telefono') !== null || $errors->has('telefono');
                    @endphp

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1.5">Email <span class="text-red-500">*</span></label>
                        @if($socio && $socio->email)
                            <div id="email-mascherato-wrap" class="flex items-center gap-2" style="{{ $mostraEmailReale ? 'display:none' : '' }}">
                                <input type="text" value="{{ $emailMascherata }}" disabled
                                       class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-500">
                                <button type="button" onclick="sbloccaCampo('email')"
                                        class="shrink-0 text-xs font-medium text-red-700 hover:text-red-800 whitespace-nowrap px-2">Modifica</button>
                            </div>
                            <div class="flex items-center gap-2" style="{{ $mostraEmailReale ? '' : 'display:none' }}" id="email-reale-wrap">
                                <input type="email" name="email" id="email-reale" value="{{ old('email') }}" required
                                       {{ $mostraEmailReale ? '' : 'disabled' }}
                                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 @error('email') border-red-400 @enderror">
                                <button type="button" onclick="annullaCampo('email')"
                                        class="shrink-0 text-xs font-medium text-gray-500 hover:text-gray-700 whitespace-nowrap px-2">Annulla</button>
                            </div>
                            <p class="text-xs text-gray-400 mt-1">Se il dato mostrato è corretto non serve modificarlo.</p>
                        @else
                            <input type="email" name="email" required value="{{ old('email') }}"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 @error('email') border-red-400 @enderror">
                        @endif
                        @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1.5">Telefono</label>
                        @if($socio && $socio->telefono)
                            <div id="telefono-mascherato-wrap" class="flex items-center gap-2" style="{{ $mostraTelefonoReale ? 'display:none' : '' }}">
                                <input type="text" value="{{ $telefonoMascherato }}" disabled
                                       class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-500">
                                <button type="button" onclick="sbloccaCampo('telefono')"
                                        class="shrink-0 text-xs font-medium text-red-700 hover:text-red-800 whitespace-nowrap px-2">Modifica</button>
                            </div>
                            <div class="flex items-center gap-2" style="{{ $mostraTelefonoReale ? '' : 'display:none' }}" id="telefono-reale-wrap">
                                <input type="tel" name="telefono" id="telefono-reale" value="{{ old('telefono') }}"
                                       {{ $mostraTelefonoReale ? '' : 'disabled' }}
                                       class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 @error('telefono') border-red-400 @enderror">
                                <button type="button" onclick="annullaCampo('telefono')"
                                        class="shrink-0 text-xs font-medium text-gray-500 hover:text-gray-700 whitespace-nowrap px-2">Annulla</button>
                            </div>
                        @else
                            <input type="tel" name="telefono" value="{{ old('telefono') }}"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 @error('telefono') border-red-400 @enderror">
                        @endif
                        @error('telefono')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>

                    @if($socio && $selRegionale)
                        <div id="comitato-bloccato-wrap" class="space-y-3" style="{{ $comitatoSbloccato ? 'display:none' : '' }}">
                            <div>
                                <p class="text-xs font-medium text-gray-600 mb-0.5">Comitato regionale</p>
                                <p class="text-sm text-gray-900">{{ $socio->comitatoRegionale?->nome ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-gray-600 mb-0.5">Comitato locale</p>
                                <p class="text-sm text-gray-900">{{ $socio->comitatoLocale?->nome ?? '—' }}</p>
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
                        @if($socio && $selRegionale)
                            <button type="button" id="comitato-annulla" onclick="annullaComitato()"
                                    class="text-xs font-medium text-gray-500 hover:text-gray-700" style="display:none">Annulla modifica</button>
                        @endif
                    </div>
                    @if(! $comitatoSbloccato)
                        <input type="hidden" name="comitato_regionale_id" id="comitato-regionale-hidden" value="{{ $selRegionale }}">
                        <input type="hidden" name="comitato_locale_id" id="comitato-locale-hidden" value="{{ $selLocale }}">
                    @endif
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100">
                    <p class="text-sm font-semibold text-gray-700">Disponibilità</p>
                    <p class="text-xs text-gray-500 mt-0.5">Includi anche il pomeriggio prima e la mattina dopo se puoi essere presente per montaggio/smontaggio (solo come logistica).</p>
                    <p class="text-xs text-gray-500 mt-1">Per ogni fascia indica il ruolo principale in cui sarai presente: se in quella fascia farai anche docenza, seleziona <strong>Docenza</strong>, ha priorità sulla logistica.</p>
                </div>
                <div class="px-5 py-4 space-y-4">
                    @foreach(collect($slotDisponibilita)->groupBy('data') as $data => $slotGiorno)
                        @php $labelGiorno = \Carbon\Carbon::parse($data)->translatedFormat('l j F'); @endphp
                        <div class="border-t border-gray-100 pt-4 first:border-t-0 first:pt-0">
                            <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide mb-2">{{ $labelGiorno }}</p>

                            <div class="grid grid-cols-[1fr_auto_auto] gap-x-4 gap-y-2.5 items-center">
                                <div></div>
                                <p class="text-[11px] font-medium text-violet-700 text-center">Docenza</p>
                                <p class="text-[11px] font-medium text-teal-700 text-center">Logistica</p>

                                @foreach($slotGiorno as $slot)
                                    @php
                                        $porz        = ucfirst($slot['porzione']);
                                        $valoreSlot  = old('disponibilita.' . $slot['chiave']);
                                    @endphp

                                    <p class="text-sm font-medium text-gray-900">{{ $porz }}</p>

                                    <div class="flex justify-center">
                                        @if($slot['extra'])
                                            <span class="text-gray-300">—</span>
                                        @else
                                            <label class="inline-flex">
                                                <input type="radio" name="disponibilita[{{ $slot['chiave'] }}]" value="docente"
                                                       class="disponibilita-docente sr-only"
                                                       aria-label="Docenza — {{ $labelGiorno }} {{ $porz }}"
                                                       {{ $valoreSlot === 'docente' ? 'checked' : '' }}>
                                                <span class="toggle-switch toggle-violet"></span>
                                            </label>
                                        @endif
                                    </div>

                                    <div class="flex justify-center">
                                        <label class="inline-flex">
                                            <input type="{{ $slot['extra'] ? 'checkbox' : 'radio' }}" name="disponibilita[{{ $slot['chiave'] }}]" value="logistica"
                                                   class="sr-only"
                                                   aria-label="Logistica — {{ $labelGiorno }} {{ $porz }}"
                                                   {{ $valoreSlot === 'logistica' ? 'checked' : '' }}>
                                            <span class="toggle-switch toggle-teal"></span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                    @error('disponibilita')<p class="text-xs text-red-600 mt-2">{{ $message }}</p>@enderror
                </div>
            </div>

            <div id="blocco-corsi" class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden" style="display:none">
                <div class="px-5 py-3 border-b border-gray-100">
                    <p class="text-sm font-semibold text-gray-700">Corsi in cui farai docenza</p>
                    <p class="text-xs text-gray-500 mt-0.5">Seleziona almeno un corso tra quelli dell'evento.</p>
                </div>
                <div class="px-5 py-4 space-y-2">
                    @forelse($corsi as $corso)
                        <label class="flex items-center gap-2 text-sm text-gray-700 border border-gray-200 rounded-lg px-3 py-2">
                            <input type="checkbox" name="corsi[]" value="{{ $corso->id }}"
                                   {{ in_array($corso->id, old('corsi', [])) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-red-600 focus:ring-red-500">
                            {{ $corso->nome }}
                            @if($corso->numero_protocollo)
                                <span class="text-gray-400">(Prot. {{ $corso->numero_protocollo }})</span>
                            @endif
                        </label>
                    @empty
                        <p class="text-xs text-amber-700">Nessun corso ancora presente su questo evento: contatta gli organizzatori.</p>
                    @endforelse
                    @error('corsi')<p class="text-xs text-red-600 mt-2">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100">
                    <p class="text-sm font-semibold text-gray-700">Consenso al trattamento dei dati</p>
                </div>
                <div class="px-5 py-4 space-y-3">
                    <label class="flex items-start gap-2.5">
                        <input type="checkbox" name="privacy_accettata" value="1" required
                               class="mt-0.5 rounded border-gray-300 text-red-600 focus:ring-red-500">
                        <span class="text-sm text-gray-700">
                            Ho letto l'informativa e acconsento al trattamento dei miei dati personali ai fini della candidatura come staff per questo evento, ai sensi del Reg. UE 2016/679 (GDPR). <span class="text-red-500">*</span>
                        </span>
                    </label>
                    @error('privacy_accettata')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror

                    @if($privacyInformativa)
                        <div class="pl-7">
                            <button type="button" onclick="toggleAccordion('acc-informativa', this)"
                                    class="inline-flex items-center gap-1 text-xs text-red-600 hover:text-red-700 font-medium">
                                <svg class="w-3 h-3 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                                </svg>
                                Leggi l'informativa completa
                            </button>
                            <div id="acc-informativa" class="hidden mt-2">
                                <div class="text-xs text-gray-700 leading-relaxed whitespace-pre-line max-h-64 overflow-y-auto border border-gray-100 rounded-lg p-3 bg-gray-50">{{ $privacyInformativa }}</div>
                            </div>
                        </div>
                    @endif

                    @unless($dichiarazioneEsterno)
                        <label class="flex items-start gap-2.5 pt-2 border-t border-gray-100">
                            <input type="checkbox" name="consenso_gaia_accettata" value="1" required
                                   class="mt-0.5 rounded border-gray-300 text-red-600 focus:ring-red-500">
                            <span class="text-sm text-gray-700">
                                Acconsento all'importazione e consultazione delle mie qualifiche da GAIA per le finalità di cui al punto 3.2 dell'informativa. <span class="text-red-500">*</span>
                            </span>
                        </label>
                        @error('consenso_gaia_accettata')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    @endunless
                </div>
            </div>

            <button type="submit" class="w-full bg-red-700 text-white rounded-lg py-2.5 text-sm font-semibold hover:bg-red-800">
                Invia candidatura
            </button>
        </form>
    </div>

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
            if (seleziona && String(seleziona) === String(l.id)) opt.selected = true;
            selLoc.appendChild(opt);
        });
    }

    selReg.addEventListener('change', function () { aggiornaLocali(this.value, null); });
    aggiornaLocali(selReg.value, initLocale);

function sbloccaCampo(nome) {
    document.getElementById(nome + '-mascherato-wrap').style.display = 'none';
    document.getElementById(nome + '-reale-wrap').style.display = 'flex';
    var reale = document.getElementById(nome + '-reale');
    reale.disabled = false;
    reale.value = '';
    reale.focus();
}

function annullaCampo(nome) {
    document.getElementById(nome + '-mascherato-wrap').style.display = 'flex';
    document.getElementById(nome + '-reale-wrap').style.display = 'none';
    var reale = document.getElementById(nome + '-reale');
    reale.disabled = true;
    reale.value = '';
}

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

function aggiornaBloccoCorsi() {
    var qualcheDocente = document.querySelector('.disponibilita-docente:checked') !== null;
    document.getElementById('blocco-corsi').style.display = qualcheDocente ? '' : 'none';
}
document.querySelectorAll('.disponibilita-docente, input[name^="disponibilita["][value="logistica"]').forEach(function (input) {
    input.addEventListener('change', aggiornaBloccoCorsi);
});

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
        aggiornaBloccoCorsi();
    });
});
aggiornaBloccoCorsi();

function toggleAccordion(contentId, btn) {
    var content = document.getElementById(contentId);
    var icon    = btn.querySelector('svg');
    var isOpen  = !content.classList.contains('hidden');
    content.classList.toggle('hidden', isOpen);
    if (icon) icon.style.transform = isOpen ? '' : 'rotate(180deg)';
}
</script>

<x-form-footer />
</body>
</html>
