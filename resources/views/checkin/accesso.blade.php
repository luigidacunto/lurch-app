<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check-in — {{ $evento->nome }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f3f4f6; margin: 0; padding: 0; }
        .header { background: #b91c1c; color: #fff; padding: 14px 16px; position: sticky; top: 0; z-index: 10;
            display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .header h1 { margin: 0; font-size: 1rem; font-weight: 700; }
        .header p  { margin: 4px 0 0; font-size: .8rem; opacity: .8; }
        .header .btn-ricerca { flex-shrink: 0; padding: 8px 12px; background: rgba(255,255,255,.15);
            color: #fff; border: 1.5px solid rgba(255,255,255,.5); border-radius: 8px; font-size: .82rem;
            font-weight: 600; text-decoration: none; white-space: nowrap; }
        .card { background: #fff; border-radius: 12px; margin: 12px; padding: 16px; box-shadow: 0 1px 4px rgba(0,0,0,.08); }
        .nome { font-size: 1.3rem; font-weight: 700; color: #111; margin: 0 0 2px; }
        .cf   { font-size: .8rem; color: #6b7280; font-family: monospace; margin: 0 0 4px; }
        .tipo-badge { display: inline-block; font-size: .72rem; font-weight: 600; padding: 2px 8px; border-radius: 20px; }
        .tipo-corsista { background: #dbeafe; color: #1d4ed8; }
        .tipo-staff    { background: #fef3c7; color: #92400e; }
        .tipo-entrambi { background: #d1fae5; color: #065f46; }
        .stato-box { border-radius: 10px; padding: 10px 14px; margin: 12px 0; font-size: .9rem; font-weight: 600; }
        .stato-dentro { background: #dcfce7; color: #15803d; }
        .stato-fuori  { background: #fee2e2; color: #b91c1c; }
        .stato-mai    { background: #f3f4f6; color: #6b7280; }
        .azione-box { border-radius: 10px; padding: 12px 14px; margin: 12px 0; border: 2px solid; }
        .azione-entrata { border-color: #16a34a; background: #f0fdf4; }
        .azione-uscita  { border-color: #dc2626; background: #fef2f2; }
        .azione-label { font-size: .8rem; font-weight: 600; color: #374151; text-transform: uppercase; letter-spacing: .05em; }
        .azione-tipo  { font-size: 1.2rem; font-weight: 800; margin: 2px 0 10px; }
        .entrata-color { color: #15803d; }
        .uscita-color  { color: #b91c1c; }
        input[type=datetime-local], input[type=text], input[type=email], select, textarea {
            width: 100%; padding: 10px 12px; border: 1.5px solid #d1d5db; border-radius: 8px;
            font-size: .95rem; margin-top: 4px; background: #fff; color: #111;
        }
        input:focus, select:focus { outline: none; border-color: #2563eb; }
        label { font-size: .82rem; font-weight: 600; color: #374151; }
        .btn-primary { display: block; width: 100%; padding: 14px; background: #2563eb; color: #fff;
            border: none; border-radius: 10px; font-size: 1.05rem; font-weight: 700; cursor: pointer; margin-top: 12px; }
        .btn-primary.verde { background: #16a34a; }
        .btn-primary.rosso { background: #dc2626; }
        .btn-secondary { display: block; width: 100%; padding: 12px; background: #f9fafb; color: #374151;
            border: 1.5px solid #d1d5db; border-radius: 10px; font-size: .95rem; font-weight: 600;
            cursor: pointer; margin-top: 8px; text-align: center; text-decoration: none; }
        .storico-item { display: flex; justify-content: space-between; align-items: center;
            padding: 8px 0; border-bottom: 1px solid #f3f4f6; font-size: .85rem; }
        .storico-item:last-child { border-bottom: none; }
        .pill-in  { background: #dcfce7; color: #15803d; padding: 2px 8px; border-radius: 20px; font-weight: 600; font-size: .75rem; }
        .pill-out { background: #fee2e2; color: #b91c1c; padding: 2px 8px; border-radius: 20px; font-weight: 600; font-size: .75rem; }
        .section-title { font-size: .75rem; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: .08em; margin: 16px 0 8px; }
        .form-row { margin-bottom: 12px; }
        .checkbox-row { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 8px; font-size: .88rem; }
        .checkbox-row input { width: auto; margin-top: 2px; flex-shrink: 0; }
        .checkbox-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-top: 6px; }
        .checkbox-grid label { font-weight: 400; font-size: .83rem; display: flex; align-items: center; gap: 6px; }
        .alert-success { background: #dcfce7; color: #15803d; border-radius: 10px; padding: 10px 14px; font-size: .9rem; font-weight: 600; margin-bottom: 12px; }
        .alert-error   { background: #fee2e2; color: #b91c1c; border-radius: 10px; padding: 10px 14px; font-size: .9rem; font-weight: 600; margin-bottom: 12px; }
        .select2col { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        details summary { cursor: pointer; list-style: none; }
        details summary::-webkit-details-marker { display: none; }
        .details-header { font-size: .8rem; font-weight: 700; color: #6b7280; text-transform: uppercase;
            letter-spacing: .06em; padding: 10px 0; display: flex; align-items: center; gap: 6px; border-bottom: 1px solid #f3f4f6; }
        details[open] .details-chevron { transform: rotate(90deg); }
        .details-chevron { display: inline-block; transition: transform .2s; }
    </style>
</head>
<body>

<div class="header">
    <div>
        <h1>Check-in / Check-out</h1>
        <p>{{ $evento->nome }}</p>
    </div>
    <a href="{{ route('checkin.ricerca', $evento) }}" class="btn-ricerca">← Ricerca</a>
</div>

<div style="padding: 12px;">

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="alert-success">✓ {{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-error">{{ session('error') }}</div>
    @endif

    {{-- Card partecipante --}}
    <div class="card">
        <p class="nome">{{ $socio->nome }} {{ $socio->cognome }}</p>
        @if($socio->codice_fiscale)
            <p class="cf">{{ $socio->codice_fiscale }}</p>
        @endif
        @php
            $tipoCss   = match($tipoIscrizione) { 'staff' => 'staff', 'corsista+staff' => 'entrambi', default => 'corsista' };
            $tipoLabel = match($tipoIscrizione) { 'staff' => 'Staff', 'corsista+staff' => 'Corsista + Staff', default => 'Corsista' };
        @endphp
        <span class="tipo-badge tipo-{{ $tipoCss }}">{{ $tipoLabel }}</span>
        @if(isset($iscrizioni) && $iscrizioni->isNotEmpty())
            <div style="margin-top:8px;">
                @foreach($iscrizioni as $isc)
                    <div style="font-size:.78rem;color:#374151;margin-top:3px;">
                        <span style="font-weight:600;">{{ $isc->corso->nome }}</span>
                        @if($isc->corso->numero_protocollo)
                            <span style="color:#9ca3af;margin-left:4px;">· {{ $isc->corso->numero_protocollo }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Stato attuale --}}
    <div class="card" style="padding: 12px 16px;">
        @if($ultimoAccesso)
            <div class="stato-box {{ $ultimoAccesso->tipo === 'entrata' ? 'stato-dentro' : 'stato-fuori' }}">
                {{ $ultimoAccesso->tipo === 'entrata' ? '✓ Attualmente DENTRO al campo' : '✗ Attualmente FUORI dal campo' }}
                <div style="font-size:.78rem;font-weight:400;margin-top:2px;">
                    Ultimo accesso: {{ $ultimoAccesso->timestamp_accesso->format('d/m/Y H:i') }}
                    — {{ $ultimoAccesso->operatore->name ?? 'operatore' }}
                </div>
            </div>
        @else
            <div class="stato-box stato-mai">Nessun accesso registrato</div>
        @endif
    </div>

    {{-- Form registrazione accesso --}}
    <div class="card">
        <form id="form-accesso" method="POST" action="{{ route('checkin.accesso', ['evento' => $evento->id, 'token' => $iscrizioneObj->badge_token]) }}">
            @csrf

            <div class="azione-box azione-{{ $tipoSuccessivo }}">
                <div class="azione-label">Stai registrando</div>
                <div class="azione-tipo {{ $tipoSuccessivo }}-color">
                    {{ strtoupper($tipoSuccessivo) }}
                </div>
                <div class="form-row">
                    <label style="display:flex;justify-content:space-between;align-items:baseline;">
                        Data e ora
                        <span id="oraserver-display" style="font-weight:400;font-size:.73rem;color:#6b7280;font-family:monospace;"></span>
                    </label>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <input type="datetime-local" id="timestamp_accesso" name="timestamp_accesso"
                            value="{{ old('timestamp_accesso', now()->format('Y-m-d\TH:i')) }}"
                            required style="flex:1;min-width:0;">
                        <button type="button" id="btn-ora-server"
                            style="padding:9px 11px;border:1.5px solid #d1d5db;border-radius:8px;background:#f9fafb;font-size:.8rem;font-weight:600;color:#374151;cursor:pointer;white-space:nowrap;flex-shrink:0;">
                            ↻ Ora
                        </button>
                    </div>
                    @error('timestamp_accesso')
                        <div style="color:#b91c1c;font-size:.8rem;margin-top:4px;font-weight:600;">⚠ {{ $message }}</div>
                    @enderror
                    <div id="ts-error" style="display:none;color:#b91c1c;font-size:.8rem;margin-top:4px;font-weight:600;"></div>
                </div>
            </div>

            {{-- Sezione dati personali — solo al primo check-in --}}
            @if($primoCheckin)
                <details open>
                    <summary>
                        <div class="details-header">
                            <span class="details-chevron">▶</span>
                            Conferma dati personali
                            <span style="color:#dc2626;margin-left:2px;">*</span>
                        </div>
                    </summary>

                    <div style="padding: 12px 0;">

                        <div class="section-title">Comitato di appartenenza</div>
                        <div class="select2col">
                            <div class="form-row">
                                <label>Comitato regionale *</label>
                                <select name="comitato_regionale_id" id="reg" required onchange="filtraLocali(this.value)">
                                    <option value="">— seleziona —</option>
                                    @foreach($datiForm['comitatiRegionali'] as $cr)
                                        <option value="{{ $cr->id }}"
                                            {{ ($prefill['comitato_regionale_id'] ?? $socio->comitato_regionale_id) == $cr->id ? 'selected' : '' }}>
                                            {{ $cr->nome }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-row">
                                <label>Comitato locale *</label>
                                <select name="comitato_locale_id" id="loc" required>
                                    <option value="">— seleziona —</option>
                                </select>
                            </div>
                        </div>

                        <div class="section-title">Contatti</div>
                        <div class="select2col">
                            <div class="form-row">
                                <label>Email</label>
                                <input type="email" name="email" value="{{ $prefill['email'] ?? $socio->email }}">
                            </div>
                            <div class="form-row">
                                <label>Telefono</label>
                                <input type="text" name="telefono" value="{{ $prefill['telefono'] ?? $socio->telefono }}"
                                    placeholder="3331234567">
                            </div>
                        </div>

                        <div class="section-title">Alimentazione</div>
                        <div class="form-row">
                            <label>Allergeni</label>
                            <div class="checkbox-grid">
                                @foreach($datiForm['allergeniList'] as $all)
                                    <label>
                                        <input type="checkbox" name="allergeni[]" value="{{ $all->nome }}"
                                            {{ in_array($all->nome, $prefill['allergeni'] ?? $socio->allergeni ?? []) ? 'checked' : '' }}>
                                        {{ $all->nome }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="form-row">
                            <label>Intolleranze</label>
                            <div class="checkbox-grid">
                                @foreach($datiForm['intolleranzeList'] as $intolleranza)
                                    <label>
                                        <input type="checkbox" name="diete[]" value="{{ $intolleranza->nome }}"
                                            {{ in_array($intolleranza->nome, $prefill['diete'] ?? $socio->diete ?? []) ? 'checked' : '' }}>
                                        {{ $intolleranza->nome }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="form-row">
                            <label>Diete particolari</label>
                            <div class="checkbox-grid">
                                @foreach($datiForm['dieteList'] as $dieta)
                                    <label>
                                        <input type="checkbox" name="diete[]" value="{{ $dieta->nome }}"
                                            {{ in_array($dieta->nome, $prefill['diete'] ?? $socio->diete ?? []) ? 'checked' : '' }}>
                                        {{ $dieta->nome }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="form-row">
                            <label>Esclusioni alimentari</label>
                            <div class="checkbox-grid">
                                @foreach($datiForm['esclusioniList'] as $esclusione)
                                    <label>
                                        <input type="checkbox" name="diete[]" value="{{ $esclusione->nome }}"
                                            {{ in_array($esclusione->nome, $prefill['diete'] ?? $socio->diete ?? []) ? 'checked' : '' }}>
                                        {{ $esclusione->nome }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="form-row">
                            <label>Note alimentari</label>
                            <input type="text" name="note_extra" maxlength="50"
                                value="{{ $prefill['note_extra'] ?? '' }}" placeholder="es. kiwi, fragole (solo alimentare, max 50 caratteri)">
                        </div>

                        @if(!empty($slotsStaff ?? []))
                            @php
                                $slotsPerGiorno = collect($slotsStaff)->groupBy('data');
                            @endphp
                            <div class="section-title">Disponibilità staff</div>
                            @foreach($slotsPerGiorno as $data => $turni)
                                <div style="margin-bottom:10px;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
                                    <div style="background:#f9fafb;padding:6px 12px;font-size:.75rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid #e5e7eb;">
                                        {{ \Carbon\Carbon::parse($data)->translatedFormat('l j F') }}
                                    </div>
                                    <div style="padding:8px 12px;">
                                        @foreach($turni as $slot)
                                            <div style="margin-bottom:8px;">
                                                <div style="font-size:.72rem;font-weight:700;color:#6b7280;margin-bottom:4px;text-transform:uppercase;">
                                                    {{ $slot['porzione'] === 'mattina' ? '🌅 Mattina' : '🌆 Pomeriggio' }}
                                                </div>
                                                <div style="display:flex;gap:16px;">
                                                    <div class="checkbox-row">
                                                        <input type="checkbox" name="{{ $slot['chiave'] }}_formazione" value="1"
                                                            {{ ($prefill[$slot['chiave'] . '_formazione'] ?? false) ? 'checked' : '' }}>
                                                        <span>Docenza</span>
                                                    </div>
                                                    <div class="checkbox-row">
                                                        <input type="checkbox" name="{{ $slot['chiave'] }}_logistica" value="1"
                                                            {{ ($prefill[$slot['chiave'] . '_logistica'] ?? false) ? 'checked' : '' }}>
                                                        <span>Logistica</span>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        @endif

                        @if(!empty($sezioni))
                            @php
                                $sezioniPerGiorno = collect($sezioni)->groupBy('data');
                            @endphp
                            <div class="section-title">Pasti e pernottamenti</div>
                            @foreach($sezioniPerGiorno as $data => $voci)
                                <div style="margin-bottom:10px;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
                                    <div style="background:#f9fafb;padding:6px 12px;font-size:.75rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;border-bottom:1px solid #e5e7eb;">
                                        {{ \Carbon\Carbon::parse($data)->translatedFormat('l j F') }}
                                    </div>
                                    <div style="padding:8px 12px;">
                                        @foreach($voci as $sezione)
                                            @php
                                                $emoji = match($sezione['tipo']) {
                                                    'colazione' => '☕',
                                                    'pranzo'    => '🍽',
                                                    'cena'      => '🌙',
                                                    'pernotto'  => '🛏',
                                                    default     => '•',
                                                };
                                            @endphp
                                            <div class="checkbox-row" style="margin-bottom:6px;">
                                                <input type="checkbox" name="{{ $sezione['chiave'] }}" value="1"
                                                    {{ ($prefill[$sezione['chiave']] ?? false) ? 'checked' : '' }}>
                                                <span>{{ $emoji }} {{ ucfirst($sezione['tipo']) }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        @endif

                        <div class="section-title">Consensi</div>
                        <div class="checkbox-row">
                            <input type="checkbox" name="privacy_accettata" value="1"
                                {{ ($prefill['privacy_accettata'] ?? false) ? 'checked' : '' }} required>
                            <span>Informativa privacy accettata *</span>
                        </div>
                        <div class="checkbox-row">
                            <input type="checkbox" name="consenso_salute_accettata" value="1"
                                {{ ($prefill['consenso_salute_accettata'] ?? false) ? 'checked' : '' }}>
                            <span>Consenso trattamento dati salute</span>
                        </div>
                        <div class="checkbox-row">
                            <input type="checkbox" name="foto_video_accettata" value="1"
                                {{ ($prefill['foto_video_accettata'] ?? false) ? 'checked' : '' }}>
                            <span>Consenso foto/video</span>
                        </div>
                    </div>
                </details>
            @endif

            <button type="submit" id="btn-submit"
                class="btn-primary {{ $tipoSuccessivo === 'entrata' ? 'verde' : 'rosso' }}">
                Conferma {{ strtoupper($tipoSuccessivo) }}
            </button>
        </form>

        <a href="{{ url()->previous() }}" class="btn-secondary">Annulla</a>
    </div>

    {{-- Storico oggi --}}
    @if($storiciOggi->isNotEmpty())
        <div class="card">
            <div class="section-title">Storico di oggi</div>
            @foreach($storiciOggi as $acc)
                <div class="storico-item">
                    <div>
                        <span class="pill-{{ $acc->tipo === 'entrata' ? 'in' : 'out' }}">
                            {{ $acc->tipo === 'entrata' ? 'ENTRATA' : 'USCITA' }}
                        </span>
                        <span style="margin-left:8px;color:#374151;">
                            {{ $acc->timestamp_accesso->format('H:i') }}
                        </span>
                    </div>
                    <span style="color:#9ca3af;font-size:.78rem;">{{ $acc->operatore->name ?? '—' }}</span>
                </div>
            @endforeach
        </div>
    @endif

</div>

<script>
// ── Comitati locali (primo check-in) ────────────────────────────────────────
const locali = {!! $primoCheckin ? ($datiForm['comitatiLocaliJson'] ?? '{}') : '{}' !!};
const selectLoc = document.getElementById('loc');
const prefillLocale = {{ $prefill['comitato_locale_id'] ?? $socio->comitato_locale_id ?? 'null' }};

function filtraLocali(regioneId) {
    if (!selectLoc) return;
    selectLoc.innerHTML = '<option value="">— seleziona —</option>';
    const lista = locali[regioneId] || [];
    lista.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = c.nome;
        if (c.id == prefillLocale) opt.selected = true;
        selectLoc.appendChild(opt);
    });
}
const selReg = document.getElementById('reg');
if (selReg && selReg.value) filtraLocali(selReg.value);

// ── Ora server ──────────────────────────────────────────────────────────────
// Calcola il drift tra ora server (PHP) e ora client al momento del caricamento.
// Usato per correggere dispositivi con orologio sbagliato.
const serverTimeAtLoad  = new Date('{{ now()->toIso8601String() }}');
const clientTimeAtLoad  = new Date();
const serverClientDrift = serverTimeAtLoad - clientTimeAtLoad; // ms

function getServerTime() {
    return new Date(Date.now() + serverClientDrift);
}

function formatForInput(date) {
    const pad = n => String(n).padStart(2, '0');
    return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate())
         + 'T' + pad(date.getHours()) + ':' + pad(date.getMinutes());
}

// Display "server HH:MM:SS" aggiornato ogni secondo accanto all'etichetta
const displayOra = document.getElementById('oraserver-display');
function aggiornaDisplayOra() {
    if (!displayOra) return;
    const t   = getServerTime();
    const pad = n => String(n).padStart(2, '0');
    displayOra.textContent = 'server ' + pad(t.getHours()) + ':' + pad(t.getMinutes()) + ':' + pad(t.getSeconds());
}
aggiornaDisplayOra();
setInterval(aggiornaDisplayOra, 1000);

// Bottone ↻ Ora: sovrascrive il campo con l'ora server corrente
document.getElementById('btn-ora-server')?.addEventListener('click', function () {
    tsInput.value  = formatForInput(getServerTime());
    tsModificato   = false; // dopo reset manuale, l'auto-update può riprendere
    nascondiErroreTs();
});

// ── Validazione + disable button ────────────────────────────────────────────
const form    = document.getElementById('form-accesso');
const tsInput = document.getElementById('timestamp_accesso');
const btnSub  = document.getElementById('btn-submit');
const tsErr   = document.getElementById('ts-error');
let   tsModificato = false;

function mostraErroreTs(msg) {
    tsErr.textContent      = '⚠ ' + msg;
    tsErr.style.display    = 'block';
    tsInput.style.borderColor = '#dc2626';
}

function nascondiErroreTs() {
    tsErr.style.display    = 'none';
    tsErr.textContent      = '';
    tsInput.style.borderColor = '';
}

tsInput.addEventListener('change', function () {
    tsModificato = true;
    nascondiErroreTs();
});

// Auto-aggiorna il campo ogni minuto solo se l'operatore non l'ha toccato
setInterval(function () {
    if (!tsModificato) tsInput.value = formatForInput(getServerTime());
}, 60 * 1000);

form.addEventListener('submit', function (e) {
    nascondiErroreTs();

    if (tsInput.value) {
        const selezionato = new Date(tsInput.value);
        const limite      = new Date(Date.now() + serverClientDrift + 30 * 60 * 1000);
        if (selezionato > limite) {
            e.preventDefault();
            mostraErroreTs('La data e ora non può essere più di 30 minuti nel futuro.');
            tsInput.focus();
            return;
        }
    }

    btnSub.disabled       = true;
    btnSub.textContent    = 'Registrazione in corso…';
    btnSub.style.opacity  = '0.65';
    btnSub.style.cursor   = 'not-allowed';
});
</script>
</body>
</html>
