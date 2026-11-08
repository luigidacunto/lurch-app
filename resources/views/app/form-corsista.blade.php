@extends('app.layout')

@section('title', 'Preregistrazione — ' . $corso->nome)

@push('styles')
<style>
    .back-btn {
        display: inline-flex; align-items: center; gap: 6px;
        color: #C8102E; font-size: 14px; font-weight: 600;
        text-decoration: none; margin-bottom: 16px;
    }
    .back-btn svg { width: 16px; height: 16px; }
    .page-title { font-size: 18px; font-weight: 800; color: #111827; margin-bottom: 4px; }
    .page-sub   { font-size: 13px; color: #6b7280; margin-bottom: 20px; }

    .section-card {
        background: #fff; border-radius: 12px; padding: 16px;
        margin-bottom: 14px; box-shadow: 0 1px 4px rgba(0,0,0,0.07);
    }
    .section-card h2 {
        font-size: 11px; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.8px; color: #9ca3af; margin-bottom: 14px;
    }

    /* Form fields */
    .form-group { margin-bottom: 14px; }
    .form-group label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 5px; }
    .form-group select, .form-group input[type="tel"], .form-group input[type="email"], .form-group input[type="text"] {
        width: 100%; padding: 10px 12px; border: 1px solid #d1d5db;
        border-radius: 8px; font-size: 14px; color: #111827;
        background: #fff; box-sizing: border-box; appearance: none;
    }
    .form-group select:focus, .form-group input:focus {
        outline: none; border-color: #C8102E; box-shadow: 0 0 0 2px rgba(200,16,46,0.1);
    }

    /* Checkbox rows */
    .check-row {
        display: flex; align-items: flex-start; gap: 10px;
        padding: 10px 0; border-bottom: 1px solid #f3f4f6; cursor: pointer;
    }
    .check-row:last-child { border-bottom: none; }
    .check-row input[type="checkbox"] { width: 18px; height: 18px; flex-shrink: 0; margin-top: 1px; accent-color: #C8102E; }
    .check-row span { font-size: 14px; color: #374151; line-height: 1.4; }
    .check-required { font-size: 11px; color: #C8102E; font-weight: 600; margin-left: 4px; }

    /* Privacy text */
    .privacy-text {
        font-size: 12px; color: #6b7280; line-height: 1.6; white-space: pre-line;
        max-height: 120px; overflow-y: auto; border: 1px solid #e5e7eb;
        border-radius: 8px; padding: 10px; margin-bottom: 12px; background: #f9fafb;
    }

    /* Submit button */
    .btn-submit {
        display: block; width: 100%; padding: 14px;
        background: #C8102E; color: #fff; border: none;
        border-radius: 10px; font-size: 16px; font-weight: 700;
        cursor: pointer; margin-top: 8px; text-align: center;
    }
    .btn-submit:active { background: #a30d25; }

    /* Errors */
    .error-list {
        background: #fef2f2; border: 1px solid #fca5a5; border-radius: 10px;
        padding: 12px 14px; margin-bottom: 16px; font-size: 13px; color: #b91c1c;
    }
    .error-list li { margin-bottom: 4px; }
    .success-banner {
        background: #dcfce7; border: 1px solid #86efac; border-radius: 10px;
        padding: 12px 14px; margin-bottom: 16px; font-size: 13px; color: #15803d; font-weight: 600;
    }

    /* Read-only */
    .fonte-badge {
        display: inline-block; font-size: 10px; font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.4px;
        padding: 2px 7px; border-radius: 6px; margin-left: 6px;
    }
    .fonte-form     { background: #e0f2fe; color: #0369a1; }
    .fonte-admin    { background: #fef3c7; color: #92400e; }
    .data-row {
        display: flex; justify-content: space-between; align-items: center;
        padding: 9px 0; border-bottom: 1px solid #f3f4f6; font-size: 14px;
    }
    .data-row:last-child { border-bottom: none; }
    .data-label { color: #6b7280; font-size: 12px; }
    .data-val   { color: #111827; font-weight: 600; text-align: right; }
    .presenza-row {
        display: flex; justify-content: space-between; align-items: center;
        padding: 9px 0; border-bottom: 1px solid #f3f4f6; font-size: 14px; color: #374151;
    }
    .presenza-row:last-child { border-bottom: none; }
    .checkin-banner {
        display: flex; align-items: center; gap: 8px;
        background: #f0fdf4; border: 1px solid #86efac;
        border-radius: 10px; padding: 10px 14px; margin-bottom: 14px;
        font-size: 13px; color: #15803d; font-weight: 600;
    }
    .prereg-banner {
        display: flex; align-items: center; gap: 8px;
        background: #eff6ff; border: 1px solid #93c5fd;
        border-radius: 10px; padding: 10px 14px; margin-bottom: 14px;
        font-size: 13px; color: #1d4ed8; font-weight: 600;
    }
    .tag-chip {
        display: inline-block; background: #f3f4f6; color: #374151;
        border-radius: 20px; font-size: 12px; padding: 3px 10px; margin: 2px;
    }
    .consenso-ok   { color: #15803d; font-weight: 600; }
    .consenso-no   { color: #9ca3af; }
    .empty-state   { font-size: 13px; color: #9ca3af; font-style: italic; }
    .quick-btns { display: flex; gap: 6px; margin-bottom: 10px; flex-wrap: wrap; }
    .quick-btn {
        font-size: 12px; font-weight: 600; color: #C8102E;
        background: #fff0f2; border: 1px solid #fca5a5;
        border-radius: 20px; padding: 4px 12px; cursor: pointer;
    }
    .quick-btn:active { background: #ffe0e5; }

    /* Campo bloccato/sbloccabile (comitato, telefono, email) — stesso pattern del form via link */
    .campo-bloccato {
        display: flex; justify-content: space-between; align-items: center; gap: 10px;
        background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px;
        padding: 10px 12px; font-size: 14px; color: #111827;
    }
    .btn-modifica { font-size: 12px; font-weight: 600; color: #C8102E; background: none; border: none; cursor: pointer; padding: 0; flex-shrink: 0; white-space: nowrap; }
    .btn-annulla  { font-size: 12px; font-weight: 600; color: #6b7280; background: none; border: none; cursor: pointer; padding: 0; margin-top: 6px; white-space: nowrap; }
    .campo-hint   { font-size: 12px; color: #9ca3af; margin-top: 4px; }
    .campo-riga   { display: flex; align-items: center; gap: 8px; }
    .campo-riga input { flex: 1; min-width: 0; }
    .campo-readonly { background: #f9fafb !important; color: #6b7280; }

    .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }

    /* Griglia pillole per allergeni/diete/intolleranze/esclusioni: 2 colonne fisse, testo
       troncato con "…" se troppo lungo (titolo HTML mostra il testo per intero), spunta
       visibile a prescindere dal colore (accessibilità daltonismo). */
    .chip-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .chip-item { display: block; }
    .chip {
        position: relative; display: flex; align-items: center; min-width: 0;
        padding: 7px 26px 7px 12px; border-radius: 999px; border: 1px solid #d1d5db;
        background: #fff; cursor: pointer; transition: background-color .15s, border-color .15s;
    }
    .chip-label { font-size: 12px; color: #374151; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }
    .chip-check {
        position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
        width: 13px; height: 13px; color: #2563eb; opacity: 0; transition: opacity .15s; pointer-events: none;
    }
    input:checked ~ .chip { background: #eff6ff; border-color: #93c5fd; }
    input:checked ~ .chip .chip-label { color: #1d4ed8; font-weight: 600; }
    input:checked ~ .chip .chip-check { opacity: 1; }
    input:disabled ~ .chip { opacity: .6; cursor: default; }
</style>
@endpush

@section('content')

<a href="{{ route('app.evento', $evento) }}" class="back-btn">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    {{ $evento->nome }}
</a>

<div class="page-title">Preregistrazione corsista</div>
<div class="page-sub">{{ $corso->nome }}</div>

@if(session('success'))
    <div class="success-banner">{{ session('success') }}</div>
@endif

@if(session('doc_errore'))
    <div class="error-list"><ul><li>{{ session('doc_errore') }}</li></ul></div>
@endif

@if($errors->any())
    <div class="error-list">
        <ul>@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

{{-- ═══════════════════════════════════════════════════════════════
     MODALITÀ READ-ONLY (form già inviato)
════════════════════════════════════════════════════════════════════ --}}
@if($readonly && $risposta)

    @php
        $daCheckin = $rispostaCheckin !== null;
        $datiDisplay = $daCheckin
            ? array_merge($risposta->risposte ?? [], $rispostaCheckin->risposte ?? [])
            : ($risposta->risposte ?? []);
    @endphp

    @if($daCheckin)
        <div class="checkin-banner">
            ✓ Dati confermati al check-in del {{ $rispostaCheckin->created_at->format('d/m/Y') }}
        </div>
    @else
        <div class="prereg-banner">
            ℹ Dati dal form di preregistrazione del {{ $risposta->compiled_at?->format('d/m/Y') ?? '—' }}
        </div>
    @endif

    {{-- Dati personali --}}
    <div class="section-card">
        <h2>Dati personali</h2>
        <div class="data-row">
            <span class="data-label">Comitato Regionale</span>
            <span class="data-val">{{ $socio->comitatoRegionale?->nome ?? '—' }}</span>
        </div>
        <div class="data-row">
            <span class="data-label">Comitato Locale</span>
            <span class="data-val">{{ $socio->comitatoLocale?->nome ?? '—' }}</span>
        </div>
        <div class="data-row">
            <span class="data-label">Telefono</span>
            <span class="data-val">{{ ($datiDisplay['prefisso_telefono'] ?? '') . ' ' . ($datiDisplay['telefono'] ?? $socio->telefono ?? '—') }}</span>
        </div>
        @if(!empty($datiDisplay['email']))
        <div class="data-row">
            <span class="data-label">Email comunicata</span>
            <span class="data-val">{{ $datiDisplay['email'] }}</span>
        </div>
        @endif
    </div>

    {{-- Documento aggiuntivo richiesto dal corso (dicitura libera dell'admin) --}}
    @if($corso->richiede_documento_aggiuntivo)
    <div class="section-card">
        <h2>Documento aggiuntivo</h2>
        <p style="font-size:13px;color:#92400e;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:8px 12px;margin-bottom:8px;line-height:1.5;white-space:pre-line;overflow-wrap:anywhere;">{{ $corso->dicitura_documento_aggiuntivo }}</p>
        <div class="form-group">
            <label for="documento_aggiuntivo-input">File richiesto (solo PDF, max 10 MB) <span class="check-required">*</span></label>
            <input type="file" name="documento_aggiuntivo" id="documento_aggiuntivo-input" accept=".pdf">
            <p class="campo-hint" id="documento_aggiuntivo-nome-file" style="display:none;"></p>
        </div>
    </div>
    @endif

    {{-- Pasti e pernotti --}}
    <div class="section-card">
        <h2>Pasti e pernotti</h2>
        @if($presenze->isEmpty())
            <p class="empty-state">Nessun pasto o pernotto selezionato.</p>
        @else
            @foreach($presenze as $p)
                <div class="presenza-row">
                    <span>{{ ucfirst($p->tipo) }} · {{ \Carbon\Carbon::parse($p->data)->translatedFormat('l j F') }}</span>
                    @if($p->fonte === 'admin')
                        <span class="fonte-badge fonte-admin">Segreteria</span>
                    @else
                        <span class="fonte-badge fonte-form">Form</span>
                    @endif
                </div>
            @endforeach
        @endif
    </div>

    {{-- Esigenze alimentari (solo se consenso prestato) --}}
    @if($risposta->consenso_salute_accettata)
    <div class="section-card">
        <h2>Esigenze alimentari</h2>
        <div class="data-row">
            <span class="data-label">Allergeni</span>
            <span class="data-val">
                @forelse($datiDisplay['allergeni'] ?? [] as $a)
                    <span class="tag-chip">{{ $a }}</span>
                @empty
                    <span class="consenso-no">Nessuno</span>
                @endforelse
            </span>
        </div>
        <div class="data-row">
            <span class="data-label">Diete</span>
            <span class="data-val">
                @forelse($datiDisplay['diete'] ?? [] as $d)
                    <span class="tag-chip">{{ $d }}</span>
                @empty
                    <span class="consenso-no">Nessuna</span>
                @endforelse
            </span>
        </div>
        @if(!empty($datiDisplay['note_extra']))
        <div class="data-row">
            <span class="data-label">Note</span>
            <span class="data-val">{{ $datiDisplay['note_extra'] }}</span>
        </div>
        @endif
    </div>
    @endif

    {{-- Consensi --}}
    <div class="section-card">
        <h2>Consensi</h2>
        <div class="data-row">
            <span class="data-label">Privacy</span>
            <span class="data-val {{ $risposta->privacy_accettata ? 'consenso-ok' : 'consenso-no' }}">
                {{ $risposta->privacy_accettata ? 'Accettata' : 'Non accettata' }}
            </span>
        </div>
        <div class="data-row">
            <span class="data-label">Qualifiche da GAIA</span>
            <span class="data-val {{ $risposta->consenso_gaia_accettata ? 'consenso-ok' : 'consenso-no' }}">
                {{ $risposta->consenso_gaia_accettata ? 'Accettato' : 'Non accettato' }}
            </span>
        </div>
        <div class="data-row">
            <span class="data-label">Foto / Video</span>
            <span class="data-val {{ $risposta->foto_video_accettata ? 'consenso-ok' : 'consenso-no' }}">
                {{ $risposta->foto_video_accettata ? 'Accettato' : 'Non accettato' }}
            </span>
        </div>
        <div class="data-row">
            <span class="data-label">Trattamento dati salute</span>
            <span class="data-val {{ $risposta->consenso_salute_accettata ? 'consenso-ok' : 'consenso-no' }}">
                {{ $risposta->consenso_salute_accettata ? 'Accettato' : 'Non necessario' }}
            </span>
        </div>
    </div>

    {{-- Pagamento / nullaosta (recap) --}}
    @if($corso->pagamento_previsto || $risposta->nullaosta_path || $corso->richiede_documento_aggiuntivo)
    <div class="section-card">
        <h2>Documenti</h2>
        @if($corso->pagamento_previsto)
            <div class="data-row">
                <span class="data-label">Ricevuta di pagamento</span>
                <span class="data-val {{ $risposta->ricevuta_path ? 'consenso-ok' : 'consenso-no' }}">
                    {{ $risposta->ricevuta_path ? 'Caricata' : 'Mancante' }}
                </span>
            </div>
            <div class="data-row">
                <span class="data-label">Data del pagamento</span>
                <span class="data-val">{{ $risposta->data_pagamento ? $risposta->data_pagamento->format('d/m/Y') : '—' }}</span>
            </div>
        @endif
        @if($risposta->nullaosta_path)
            <div class="data-row">
                <span class="data-label">Nullaosta</span>
                <span class="data-val consenso-ok">Caricato</span>
            </div>
        @endif

        {{-- Sostituzione documenti finché non verificati (parità col form pubblico) --}}
        @if($corso->pagamento_previsto && $risposta->ricevuta_path)
            <x-sostituzione-documento
                slot="ricevuta" label="Ricevuta di pagamento" accept=".jpg,.jpeg,.png,.webp,.pdf"
                :action="route('app.evento.form.corsista.documenti.sostituisci', [$evento, $iscrizione, 'ricevuta'])"
                :caricato="(bool) $risposta->ricevuta_path"
                :verificato="(bool) $risposta->pagamento_verificato" />
        @endif
        @if($richiedeNullaosta && $risposta->nullaosta_path)
            <x-sostituzione-documento
                slot="nullaosta" label="Nullaosta del Presidente" accept=".pdf"
                :action="route('app.evento.form.corsista.documenti.sostituisci', [$evento, $iscrizione, 'nullaosta'])"
                :caricato="(bool) $risposta->nullaosta_path"
                :verificato="(bool) $risposta->nullaosta_verificato" />
        @endif
        @if($corso->richiede_documento_aggiuntivo)
            <x-sostituzione-documento
                slot="documento_aggiuntivo" label="Documento aggiuntivo" accept=".pdf"
                :action="route('app.evento.form.corsista.documenti.sostituisci', [$evento, $iscrizione, 'documento_aggiuntivo'])"
                :caricato="(bool) $risposta->documento_aggiuntivo_path"
                :verificato="(bool) $risposta->documento_aggiuntivo_verificato"
                :richiesto="true" :dicitura="$corso->dicitura_documento_aggiuntivo" />
        @endif
    </div>
    @endif

    {{-- Verifica email (indirizzo cambiato in fase di compilazione) --}}
    @if(session('email_verificata'))
        <div class="success-banner">Email verificata con successo!</div>
    @elseif($emailDaVerificare)
        <div class="section-card">
            <h2>Conferma il tuo indirizzo email</h2>
            <p style="font-size:13px;color:#6b7280;line-height:1.5;margin-bottom:8px;">
                Serve ad accertarci che l'indirizzo <strong>{{ $emailDaVerificare }}</strong> riceva correttamente
                le email. Ti abbiamo inviato un'email con un link e un codice di 6 cifre: clicca il link, oppure
                inserisci il codice qui sotto.
            </p>
            <form method="POST" action="{{ route('app.evento.form.corsista.verifica-email', [$evento, $iscrizione]) }}"
                  style="display:flex;gap:8px;align-items:center;">
                @csrf
                <input type="text" name="codice" maxlength="6" inputmode="numeric" pattern="[0-9]{6}"
                       placeholder="000000" autocomplete="one-time-code"
                       style="width:120px;text-align:center;letter-spacing:.3em;font-family:monospace;">
                <button type="submit" class="quick-btn" style="background:#b91c1c;color:#fff;border-color:#b91c1c;">Verifica</button>
            </form>
        </div>
    @endif

{{-- ═══════════════════════════════════════════════════════════════
     MODALITÀ FORM COMPILABILE
════════════════════════════════════════════════════════════════════ --}}
@else

<form method="POST" action="{{ route('app.evento.form.corsista.store', [$evento, $iscrizione]) }}" enctype="multipart/form-data">
    @csrf

    {{-- Comitato --}}
    @php
        $selRegionale = old('comitato_regionale_id', $socio->comitato_regionale_id);
        $selLocale    = old('comitato_locale_id', $socio->comitato_locale_id);
        $comitatoSbloccato = ! $selRegionale || old('comitato_regionale_id') !== null || $errors->has('comitato_regionale_id');

        $telefonoSalvato   = old('telefono', $socio->telefono ?? '');
        $emailSalvata      = old('email', $socio->email ?? '');
        $telefonoSbloccato = ! $telefonoSalvato || old('telefono') !== null || $errors->has('telefono');
        $emailSbloccata    = ! $emailSalvata    || old('email')    !== null || $errors->has('email');
    @endphp
    <div class="section-card">
        <h2>Il tuo comitato</h2>
        @if($selRegionale)
            <div id="comitato-bloccato-wrap" style="{{ $comitatoSbloccato ? 'display:none' : '' }}">
                <div class="campo-bloccato" style="flex-direction:column;align-items:stretch;gap:4px;">
                    <span>{{ $socio->comitatoRegionale?->nome ?? '—' }} / {{ $socio->comitatoLocale?->nome ?? '—' }}</span>
                    <button type="button" class="btn-modifica" onclick="sbloccaComitato()">Modifica comitato</button>
                </div>
                <p class="campo-hint">Se il dato mostrato è corretto non serve modificarlo.</p>
            </div>
        @endif
        <div id="comitato-sblocco-wrap" style="{{ $comitatoSbloccato ? '' : 'display:none' }}">
            <div class="form-group">
                <label for="sel-regionale">Comitato Regionale <span class="check-required">*</span></label>
                <select name="comitato_regionale_id" id="sel-regionale" {{ $comitatoSbloccato ? 'required' : 'disabled' }}>
                    <option value="">— Seleziona —</option>
                    @foreach($comitatiRegionali as $cr)
                        <option value="{{ $cr->id }}" @selected($selRegionale == $cr->id)>{{ $cr->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label for="sel-locale">Comitato Locale <span class="check-required">*</span></label>
                <select name="comitato_locale_id" id="sel-locale" {{ $comitatoSbloccato ? 'required' : 'disabled' }}>
                    <option value="">— Seleziona —</option>
                </select>
            </div>
            @if($selRegionale)
                <button type="button" id="comitato-annulla" class="btn-annulla" onclick="annullaComitato()" style="display:none">Annulla modifica</button>
            @endif
        </div>
        @if(! $comitatoSbloccato)
            <input type="hidden" name="comitato_regionale_id" id="comitato-regionale-hidden" value="{{ $selRegionale }}">
            <input type="hidden" name="comitato_locale_id" id="comitato-locale-hidden" value="{{ $selLocale }}">
        @endif
    </div>

    {{-- Contatti --}}
    <div class="section-card">
        <h2>Contatti</h2>
        <div class="form-group">
            <label>Telefono</label>
            <div class="campo-riga">
                <input type="tel" id="campo-telefono" name="telefono" value="{{ $telefonoSalvato }}"
                       {{ $telefonoSbloccato ? '' : 'readonly' }}
                       class="{{ $telefonoSbloccato ? '' : 'campo-readonly' }}"
                       placeholder="es. 3401234567">
                <button type="button" id="telefono-modifica" class="btn-modifica" onclick="sbloccaCampoTesto('telefono')"
                        style="{{ $telefonoSbloccato ? 'display:none' : '' }}">Modifica</button>
                @if($telefonoSalvato)
                    <button type="button" id="telefono-annulla" class="btn-annulla" onclick="annullaCampoTesto('telefono')" style="display:none">Annulla</button>
                @endif
            </div>
            @if($telefonoSalvato)<p class="campo-hint">Se il dato mostrato è corretto non serve modificarlo.</p>@endif
            <input type="hidden" name="prefisso_telefono" value="+39">
        </div>
        <div class="form-group">
            <label>Indirizzo email <span class="check-required">*</span></label>
            <div class="campo-riga">
                <input type="email" id="campo-email" name="email" value="{{ $emailSalvata }}" required
                       {{ $emailSbloccata ? '' : 'readonly' }}
                       class="{{ $emailSbloccata ? '' : 'campo-readonly' }}"
                       placeholder="es. mario@esempio.it">
                <button type="button" id="email-modifica" class="btn-modifica" onclick="sbloccaCampoTesto('email')"
                        style="{{ $emailSbloccata ? 'display:none' : '' }}">Modifica</button>
                @if($emailSalvata)
                    <button type="button" id="email-annulla" class="btn-annulla" onclick="annullaCampoTesto('email')" style="display:none">Annulla</button>
                @endif
            </div>
            <p class="campo-hint">Serve per le comunicazioni relative all'evento/corso. Se lo modifichi ti chiederemo un codice di conferma.</p>
        </div>
    </div>

    {{-- Ricevuta di pagamento --}}
    @if($corso->pagamento_previsto)
    <div class="section-card">
        <h2>Ricevuta di pagamento</h2>
        <p style="font-size:13px;color:#6b7280;margin-bottom:12px;">Allega la ricevuta o uno screenshot del pagamento effettuato.</p>

        <div class="form-group">
            <label for="ricevuta-input">File ricevuta (JPG, PNG o PDF, max 10 MB) <span class="check-required">*</span></label>
            <input type="file" name="ricevuta" id="ricevuta-input" accept=".jpg,.jpeg,.png,.webp,.pdf">
            <p class="campo-hint" id="ricevuta-nome-file" style="display:none;"></p>
        </div>

        <div class="form-group">
            <label for="data_pagamento">Data del pagamento <span class="check-required">*</span></label>
            <input type="date" name="data_pagamento" id="data_pagamento"
                   value="{{ old('data_pagamento') }}" max="{{ date('Y-m-d') }}" required>
        </div>
    </div>
    @endif

    {{-- Nullaosta del Presidente del Comitato Locale (regione diversa) --}}
    @if($richiedeNullaosta)
    <div class="section-card">
        <h2>Nullaosta del Presidente del Comitato Locale</h2>
        <p style="font-size:13px;color:#92400e;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:8px 12px;margin-bottom:8px;line-height:1.5;">
            Sei iscritto a un comitato di una <strong>regione diversa</strong> da quella organizzatrice del corso.
            Allega in PDF il nullaosta firmato dal Presidente del tuo Comitato Locale, con i tuoi dati (cognome, nome, comitato),
            i dati del presidente e il corso con le relative date.
        </p>
        <div class="form-group">
            <label for="nullaosta-input">File nullaosta (solo PDF, max 10 MB) <span class="check-required">*</span></label>
            <input type="file" name="nullaosta" id="nullaosta-input" accept=".pdf">
            <p class="campo-hint" id="nullaosta-nome-file" style="display:none;"></p>
        </div>
    </div>
    @endif

    {{-- Pasti e pernotti --}}
    @if($sezioni->isNotEmpty())
    <div class="section-card">
        <h2>Pasti e pernotti</h2>
        <div class="quick-btns">
            <button type="button" class="quick-btn" onclick="setAllPasti(true)">Seleziona tutti</button>
            <button type="button" class="quick-btn" onclick="setAllPasti(false)">Deseleziona</button>
        </div>
        @foreach($sezioni as $sezione)
            @php $chiave = $sezione['tipo'] . '_' . $sezione['data']; @endphp
            <label class="check-row">
                <input type="checkbox" name="{{ $chiave }}" value="1" class="pasto-cb"
                    @checked(old($chiave))>
                <span>{{ ucfirst($sezione['tipo']) }} · {{ \Carbon\Carbon::parse($sezione['data'])->translatedFormat('l j F') }}</span>
            </label>
        @endforeach
    </div>
    @endif

    {{-- Esigenze alimentari (visibile solo se si seleziona almeno un pasto) --}}
    <div class="section-card" id="sezione-alim" style="display:none;">
        <h2>Esigenze alimentari</h2>
        <p style="font-size:13px;color:#6b7280;margin-bottom:12px;">Compila solo se hai esigenze specifiche.</p>

        @if($allergeniList->isNotEmpty())
        <div class="form-group">
            <label>Allergeni</label>
            <div class="chip-grid">
                @foreach($allergeniList as $al)
                    <label class="chip-item" title="{{ $al->etichetta }}">
                        <input type="checkbox" name="allergeni[]" value="{{ $al->nome }}" class="sr-only"
                            @checked(in_array($al->nome, old('allergeni', $socio->allergeni ?? [])))>
                        <span class="chip">
                            <span class="chip-label">{{ $al->etichetta }}</span>
                            <svg class="chip-check" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                        </span>
                    </label>
                @endforeach
            </div>
        </div>
        @endif

        @if($intolleranzeList->isNotEmpty())
        <div class="form-group">
            <label>Intolleranze</label>
            <div class="chip-grid">
                @foreach($intolleranzeList as $in)
                    <label class="chip-item" title="{{ $in->etichetta }}">
                        <input type="checkbox" name="diete[]" value="{{ $in->nome }}" class="sr-only"
                            @checked(in_array($in->nome, old('diete', $socio->diete ?? [])))>
                        <span class="chip">
                            <span class="chip-label">{{ $in->etichetta }}</span>
                            <svg class="chip-check" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                        </span>
                    </label>
                @endforeach
            </div>
        </div>
        @endif

        @if($dieteList->isNotEmpty())
        <div class="form-group">
            <label>Diete particolari</label>
            <div class="chip-grid">
                @foreach($dieteList as $di)
                    <label class="chip-item" title="{{ $di->etichetta }}">
                        <input type="checkbox" name="diete[]" value="{{ $di->nome }}" class="sr-only"
                            @checked(in_array($di->nome, old('diete', $socio->diete ?? [])))>
                        <span class="chip">
                            <span class="chip-label">{{ $di->etichetta }}</span>
                            <svg class="chip-check" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                        </span>
                    </label>
                @endforeach
            </div>
        </div>
        @endif

        @if($esclusioniList->isNotEmpty())
        <div class="form-group">
            <label>Esclusioni alimentari</label>
            <div class="chip-grid">
                @foreach($esclusioniList as $es)
                    <label class="chip-item" title="{{ $es->etichetta }}">
                        <input type="checkbox" name="diete[]" value="{{ $es->nome }}" class="sr-only"
                            @checked(in_array($es->nome, old('diete', $socio->diete ?? [])))>
                        <span class="chip">
                            <span class="chip-label">{{ $es->etichetta }}</span>
                            <svg class="chip-check" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" /></svg>
                        </span>
                    </label>
                @endforeach
            </div>
        </div>
        @endif

        <div class="form-group">
            <label for="note-extra">Note alimentari (max 50 caratteri)</label>
            <input type="text" name="note_extra" id="note-extra" maxlength="50"
                   value="{{ old('note_extra') }}" placeholder="es. kiwi, fragole">
        </div>
    </div>

    {{-- 1. Privacy GDPR --}}
    <div class="section-card">
        <label class="check-row">
            <input type="checkbox" name="privacy_accettata" value="1" required
                @checked(old('privacy_accettata'))>
            <span>Ho letto e accetto il trattamento dei dati personali per la gestione dell'evento, ai sensi del Reg. UE 2016/679 (GDPR). <span class="check-required">*</span></span>
        </label>
        @if($privacyInformativa || $privacyInformativaPdf)
            <details style="margin:4px 0 0 28px;">
                <summary style="font-size:12px;color:#C8102E;font-weight:600;cursor:pointer;list-style:none;-webkit-appearance:none;">▸ Leggi l'informativa completa</summary>
                @if($privacyInformativa)
                    <div class="privacy-text" style="margin-top:8px;">{{ $privacyInformativa }}</div>
                @endif
                @if($privacyInformativaPdf)
                    <a href="{{ \Storage::url($privacyInformativaPdf) }}" target="_blank" style="font-size:12px;color:#C8102E;text-decoration:underline;display:inline-block;margin-top:6px;">📄 Scarica PDF</a>
                @endif
            </details>
        @endif

        <label class="check-row" style="margin-top:10px;padding-top:10px;border-top:1px solid #f3f4f6;">
            <input type="checkbox" name="consenso_gaia_accettata" value="1" required
                @checked(old('consenso_gaia_accettata'))>
            <span>Acconsento all'importazione e consultazione delle mie qualifiche da GAIA per le finalità di cui al punto 3.2 dell'informativa sopra (verifica dei requisiti minimi previsti dal corso). <span class="check-required">*</span></span>
        </label>
    </div>

    {{-- 2. Consenso dati sulla salute — visibile solo se è selezionato almeno un pasto, come le esigenze alimentari --}}
    <div class="section-card" id="blocco-consenso-salute" style="display:none;">
        <label class="check-row">
            <input type="checkbox" name="consenso_salute_accettata" value="1" id="cb-salute"
                @checked(old('consenso_salute_accettata'))>
            <span>Acconsento al trattamento dei miei dati sulla salute (allergie e intolleranze) per la gestione dei pasti durante l'evento.
                <span style="display:block;font-size:11px;color:#9ca3af;margin-top:2px;">Art. 9, par. 2, lett. a) GDPR — consenso esplicito</span>
            </span>
        </label>
        <details style="margin:4px 0 0 28px;">
            <summary style="font-size:12px;color:#C8102E;font-weight:600;cursor:pointer;list-style:none;-webkit-appearance:none;">▸ Perché raccogliamo questi dati</summary>
            <div class="privacy-text" style="margin-top:8px;">I dati relativi alla salute (allergie, intolleranze e regime alimentare) saranno comunicati esclusivamente al personale di cucina o al fornitore della ristorazione per garantire la tua sicurezza alimentare durante l'evento.</div>
        </details>
    </div>

    {{-- 3. Consenso foto/video — punto 3.4 dell'informativa unica --}}
    <div class="section-card">
        <label class="check-row">
            <input type="checkbox" name="foto_video_accettata" value="1" required
                @checked(old('foto_video_accettata'))>
            <span>Autorizzo la ripresa fotografica e video durante l'evento per fini istituzionali e comunicativi della Croce Rossa Italiana (punto 3.4 dell'informativa sopra).
                <span style="display:block;font-size:11px;color:#9ca3af;margin-top:2px;">Art. 6, par. 1, lett. a) GDPR</span>
            </span>
        </label>
    </div>

    <button type="submit" class="btn-submit">Invia preregistrazione</button>
</form>

@endif

@endsection

@push('scripts')
<script>
(function () {
    const comitatiLocali = @json(json_decode($comitatiLocaliJson, true));
    const selReg   = document.getElementById('sel-regionale');
    const selLoc   = document.getElementById('sel-locale');
    const preselectLocale = {{ old('comitato_locale_id', $socio->comitato_locale_id ?? 'null') }};

    function aggiornaLocali(regionaleId) {
        selLoc.innerHTML = '<option value="">— Seleziona —</option>';
        const lista = comitatiLocali[regionaleId] ?? [];
        lista.forEach(function (c) {
            const opt = new Option(c.nome, c.id);
            if (preselectLocale && c.id == preselectLocale) opt.selected = true;
            selLoc.add(opt);
        });
    }

    selReg.addEventListener('change', function () { aggiornaLocali(this.value); });
    if (selReg.value) aggiornaLocali(selReg.value);

    @if(!$readonly)
    window.sbloccaComitato = function () {
        var bloccato  = document.getElementById('comitato-bloccato-wrap');
        var sblocco   = document.getElementById('comitato-sblocco-wrap');
        var hidReg    = document.getElementById('comitato-regionale-hidden');
        var hidLoc    = document.getElementById('comitato-locale-hidden');
        var btnAnnulla = document.getElementById('comitato-annulla');
        if (bloccato) bloccato.style.display = 'none';
        if (sblocco)  sblocco.style.display = '';
        selReg.disabled = false; selReg.required = true;
        selLoc.disabled = false; selLoc.required = true;
        if (hidReg) hidReg.disabled = true;
        if (hidLoc) hidLoc.disabled = true;
        if (btnAnnulla) btnAnnulla.style.display = 'inline-block';
    };

    window.annullaComitato = function () {
        var bloccato  = document.getElementById('comitato-bloccato-wrap');
        var sblocco   = document.getElementById('comitato-sblocco-wrap');
        var hidReg    = document.getElementById('comitato-regionale-hidden');
        var hidLoc    = document.getElementById('comitato-locale-hidden');
        var btnAnnulla = document.getElementById('comitato-annulla');
        if (hidReg) { hidReg.disabled = false; selReg.value = hidReg.value; }
        aggiornaLocali(selReg.value);
        if (hidLoc) { hidLoc.disabled = false; selLoc.value = hidLoc.value; }
        selReg.disabled = true; selReg.required = false;
        selLoc.disabled = true; selLoc.required = false;
        if (sblocco)  sblocco.style.display = 'none';
        if (bloccato) bloccato.style.display = '';
        if (btnAnnulla) btnAnnulla.style.display = 'none';
    };

    window.sbloccaCampoTesto = function (campo) {
        var el = document.getElementById('campo-' + campo);
        el.dataset.valoreOriginale = el.value;
        el.readOnly = false;
        el.classList.remove('campo-readonly');
        el.focus();
        el.select();
        var btnMod = document.getElementById(campo + '-modifica');
        var btnAnn = document.getElementById(campo + '-annulla');
        if (btnMod) btnMod.style.display = 'none';
        if (btnAnn) btnAnn.style.display = 'inline-block';
    };

    window.annullaCampoTesto = function (campo) {
        var el = document.getElementById('campo-' + campo);
        el.value = el.dataset.valoreOriginale ?? el.value;
        el.readOnly = true;
        el.classList.add('campo-readonly');
        var btnMod = document.getElementById(campo + '-modifica');
        var btnAnn = document.getElementById(campo + '-annulla');
        if (btnMod) btnMod.style.display = 'inline-block';
        if (btnAnn) btnAnn.style.display = 'none';
    };

    const pastoCbs    = document.querySelectorAll('.pasto-cb');
    const sezioneAlim = document.getElementById('sezione-alim');
    const bloccoSalute = document.getElementById('blocco-consenso-salute');
    const cbSalute    = document.getElementById('cb-salute');

    function aggiornaSezAlim() {
        const hasPasto = Array.from(pastoCbs).some(function (cb) { return cb.checked; });
        sezioneAlim.style.display  = hasPasto ? 'block' : 'none';
        bloccoSalute.style.display = hasPasto ? 'block' : 'none';
        if (cbSalute) { cbSalute.required = hasPasto; if (! hasPasto) cbSalute.checked = false; }
    }

    window.setAllPasti = function (val) {
        pastoCbs.forEach(function (cb) { cb.checked = val; });
        aggiornaSezAlim();
    };

    pastoCbs.forEach(function (cb) { cb.addEventListener('change', aggiornaSezAlim); });
    aggiornaSezAlim();

    // Mostra il nome del file selezionato per ricevuta e nullaosta
    ['ricevuta', 'nullaosta', 'documento_aggiuntivo'].forEach(function (nome) {
        var input = document.getElementById(nome + '-input');
        var label = document.getElementById(nome + '-nome-file');
        if (! input || ! label) return;
        input.addEventListener('change', function () {
            if (input.files && input.files[0]) {
                label.textContent = '✓ ' + input.files[0].name;
                label.style.display = 'block';
            } else {
                label.style.display = 'none';
            }
        });
    });
    @endif
})();
</script>
@endpush
