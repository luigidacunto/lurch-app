<x-filament-panels::page>



    @php
        $corso  = $this->record;
        $evento = $corso->evento;

        $iscrizioni  = $corso->iscrizioni->sortBy('partecipante.cognome');
        $attive      = $iscrizioni->where('stato', '!=', 'ritirato');
        $ritirati    = $iscrizioni->where('stato', 'ritirato')->count();
        $totale      = $iscrizioni->count();                                                                        // tutti, inclusi ritirati
        $conRicevuta = $iscrizioni->filter(fn ($i) => $i->rispostaPreregistrazione?->ricevuta_path)->count();      // tutti con ricevuta
        $verificate  = $iscrizioni->filter(fn ($i) => $i->rispostaPreregistrazione?->pagamento_verificato)->count(); // tutti verificati
        $mancanti    = $attive->filter(fn ($i) => ! $i->rispostaPreregistrazione?->ricevuta_path)->count();        // solo attivi senza ricevuta
    @endphp

    {{-- Header info corso --}}
    <x-filament::section>
        <div style="display:flex; gap:32px; flex-wrap:wrap; align-items:center;">
            <div>
                <p style="font-size:11px; color:#9ca3af; text-transform:uppercase; letter-spacing:.05em; margin-bottom:2px;">Corso</p>
                <p style="font-size:15px; font-weight:700; color:#111827;">{{ $corso->nome }}</p>
                @if($corso->numero_protocollo)
                    <p style="font-size:11px; color:#9ca3af; font-family:monospace;">{{ $corso->numero_protocollo }}</p>
                @endif
            </div>
            <div>
                <p style="font-size:11px; color:#9ca3af; text-transform:uppercase; letter-spacing:.05em; margin-bottom:2px;">Evento</p>
                <p style="font-size:13px; color:#374151;">{{ $evento->nome }}</p>
            </div>
            @if($corso->quota_partecipazione)
            <div>
                <p style="font-size:11px; color:#9ca3af; text-transform:uppercase; letter-spacing:.05em; margin-bottom:2px;">Quota</p>
                <p style="font-size:15px; font-weight:700; color:#7c3aed;">€ {{ number_format($corso->quota_partecipazione, 2, ',', '.') }}</p>
            </div>
            @endif
        </div>
    </x-filament::section>

    {{-- Statistiche --}}
    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:12px;">
        <div style="background:#fff; border:1px solid #f3f4f6; border-radius:12px; padding:16px; text-align:center;">
            <p style="font-size:24px; font-weight:700; color:#111827;">{{ $totale }}</p>
            <p style="font-size:11px; color:#9ca3af; text-transform:uppercase; letter-spacing:.05em;">Totale</p>
        </div>
        <div style="background:#fff; border:1px solid #f3f4f6; border-radius:12px; padding:16px; text-align:center;">
            <p style="font-size:24px; font-weight:700; color:#7c3aed;">{{ $conRicevuta }}</p>
            <p style="font-size:11px; color:#9ca3af; text-transform:uppercase; letter-spacing:.05em;">Pagamenti</p>
        </div>
        <div style="background:#fff; border:1px solid #f3f4f6; border-radius:12px; padding:16px; text-align:center;">
            <p style="font-size:24px; font-weight:700; color:#16a34a;">{{ $verificate }}</p>
            <p style="font-size:11px; color:#9ca3af; text-transform:uppercase; letter-spacing:.05em;">Verificati</p>
        </div>
        <div style="background:#fff; border:1px solid {{ $ritirati > 0 ? '#fed7aa' : '#f3f4f6' }}; border-radius:12px; padding:16px; text-align:center;">
            <p style="font-size:24px; font-weight:700; color:{{ $ritirati > 0 ? '#ea580c' : '#9ca3af' }};">{{ $ritirati }}</p>
            <p style="font-size:11px; color:#9ca3af; text-transform:uppercase; letter-spacing:.05em;">Ritirati</p>
        </div>
        <div style="background:#fff; border:1px solid {{ $mancanti > 0 ? '#fde68a' : '#f3f4f6' }}; border-radius:12px; padding:16px; text-align:center;">
            <p style="font-size:24px; font-weight:700; color:{{ $mancanti > 0 ? '#b45309' : '#9ca3af' }};">{{ $mancanti }}</p>
            <p style="font-size:11px; color:#9ca3af; text-transform:uppercase; letter-spacing:.05em;">Mancanti</p>
        </div>
    </div>

    {{-- Lista iscritti --}}
    <x-filament::section>
        <x-slot name="heading">Ricevute pagamento</x-slot>

        {{-- Scroll orizzontale sotto una certa larghezza: le colonne sono a larghezza fissa,
             senza questo wrapper gli elementi escono dal bordo invece di andare in scroll. --}}
        <div style="overflow-x:auto;">
        <div style="min-width:820px;">

        {{-- Intestazione colonne --}}
        <div style="display:grid; grid-template-columns:1fr 90px 200px 160px 140px; gap:12px; padding:6px 0 8px; border-bottom:2px solid #e5e7eb; align-items:center;">
            <p style="font-size:10px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:.05em;">Nominativo</p>
            <p style="font-size:10px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:.05em;"></p>
            <p style="font-size:10px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:.05em; text-align:center;">Stato</p>
            <p style="font-size:10px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:.05em; text-align:center;">Data pagamento</p>
            <p style="font-size:10px; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:.05em;"></p>
        </div>

        @forelse($iscrizioni as $iscrizione)
            @php
                $p        = $iscrizione->partecipante;
                $risposta = $iscrizione->rispostaPreregistrazione;
                $ricevuta = $risposta?->ricevuta_path;
                $verificato    = $risposta?->pagamento_verificato ?? false;
                $verificatoAt  = $risposta?->pagamento_verificato_at;
                $verificatoDa  = $risposta?->verificatoDa?->name;
                $dataPagamento = $risposta?->data_pagamento;
                $ritirato      = $iscrizione->isRitirato();
            @endphp

            <div style="display:grid; grid-template-columns:1fr 90px 200px 160px 140px; gap:12px; padding:10px 0; border-bottom:1px solid #f3f4f6; align-items:center;">

                {{-- Col 1: Nominativo --}}
                <div>
                    <p style="font-size:13px; font-weight:600; color:#111827;">{{ $p?->cognome }} {{ $p?->nome }}</p>
                    <p style="font-size:11px; color:#9ca3af; margin-top:1px;">
                        {{ $p?->email ?? '—' }}
                        @if($p?->comitatoLocale) · {{ $p->comitatoLocale->nome }} @endif
                    </p>
                </div>

                {{-- Col 2: Ritirato (senza intestazione, sempre presente per non sballare l'allineamento dei pulsanti dopo) --}}
                <div style="display:flex; justify-content:center;">
                    @if($ritirato)
                        <span style="display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:600; color:#991b1b; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; padding:4px 8px; white-space:nowrap;">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                            Ritirato
                        </span>
                    @endif
                </div>

                {{-- Col 3: Badge stato (con "Annulla verifica" a sinistra se verificato) --}}
                <div style="display:flex; align-items:center; gap:6px; justify-content:center; flex-wrap:nowrap;">
                    @if($verificato)
                        <x-filament::icon-button
                            color="warning" size="sm" icon="heroicon-o-x-mark"
                            wire:click="mountAction('annullaVerifica', { iscrizione_id: {{ $iscrizione->id }} })"
                            tag="button" type="button" label="Annulla verifica"
                        />
                        <span style="display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:600; color:#15803d; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:4px 8px; white-space:nowrap;"
                              title="Verificato il {{ $verificatoAt?->format('d/m/Y H:i') }}{{ $verificatoDa ? ' da ' . $verificatoDa : '' }}">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            Verif. {{ $verificatoAt?->format('d/m') }}
                        </span>
                    @elseif($ricevuta)
                        <span style="display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; color:#7c3aed; background:#f5f3ff; border:1px solid #ddd6fe; border-radius:5px; flex-shrink:0;"
                              title="Ricevuta da verificare">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </span>
                        <x-filament::button
                            color="success" size="sm" icon="heroicon-o-check-circle"
                            wire:click="mountAction('verificaPagamento', { iscrizione_id: {{ $iscrizione->id }} })"
                            tag="button" type="button"
                        >Verifica</x-filament::button>
                    @else
                        <span style="display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:500; color:#b45309; background:#fef3c7; border:1px solid #fde68a; border-radius:6px; padding:4px 8px; white-space:nowrap;">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                            Mancante
                        </span>
                    @endif
                </div>

                {{-- Col 4: Data pagamento (solo se ha ricevuta) — bloccata una volta verificato il pagamento, si sblocca solo con "Annulla verifica" --}}
                <div style="display:flex; justify-content:center;" title="{{ $verificato ? 'Pagamento verificato: annulla la verifica per modificare la data' : '' }}">
                    @if($ricevuta)
                        <input type="date"
                               value="{{ $dataPagamento?->format('Y-m-d') }}"
                               max="{{ date('Y-m-d') }}"
                               wire:change="aggiornaDataPagamento({{ $iscrizione->id }}, $event.target.value)"
                               {{ $verificato ? 'disabled' : '' }}
                               style="font-size:12px; border:1px solid #e5e7eb; border-radius:6px; padding:4px 8px; color:#374151; background:{{ $verificato ? '#f3f4f6' : '#fff' }}; outline:none; width:136px;">
                    @else
                        <span style="font-size:11px; color:#d1d5db;">—</span>
                    @endif
                </div>

                {{-- Col 5: Visualizza ricevuta (anteprima in modale, mai in nuova scheda: alcuni browser scaricano invece di aprire)
                     + Sostituisci (solo finché non verificata: corregge un file caricato per errore senza resettare il form) --}}
                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                    @if($ricevuta)
                        <x-filament::button
                            color="gray" size="sm" icon="heroicon-o-eye"
                            wire:click="mountAction('visualizzaRicevuta', { iscrizione_id: {{ $iscrizione->id }}, nome: '{{ addslashes(trim(($p?->cognome ?? '') . ' ' . ($p?->nome ?? ''))) }}' })"
                            tag="button" type="button"
                        >Visualizza</x-filament::button>
                        @if(! $verificato)
                            <x-filament::button
                                color="gray" size="sm" icon="heroicon-o-arrow-path"
                                wire:click="mountAction('sostituisciRicevuta', { iscrizione_id: {{ $iscrizione->id }}, nome: '{{ addslashes(trim(($p?->cognome ?? '') . ' ' . ($p?->nome ?? ''))) }}' })"
                                tag="button" type="button"
                            >Sostituisci</x-filament::button>
                        @endif
                    @endif
                </div>

            </div>
        @empty
            <p style="font-size:13px; color:#9ca3af; padding:16px 0;">Nessun iscritto.</p>
        @endforelse

        </div>
        </div>
    </x-filament::section>

</x-filament-panels::page>
