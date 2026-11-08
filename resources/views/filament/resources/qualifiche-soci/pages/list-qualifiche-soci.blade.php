<x-filament-panels::page>
    <x-filament-actions::modals />

    <div class="space-y-6">

        {{-- Preview import (visibile solo quando è stato caricato un file) --}}
        @if ($this->importPreview !== null)
            @php
                $totInserimenti   = collect($this->importPreview)->where('stato', 'inserimento')->count();
                $totAggiornamenti = collect($this->importPreview)->where('stato', 'aggiornamento')->count();
                $totErroriCf      = collect($this->importPreview)->where('stato', 'errore_cf')->count();
                $totErroriQ       = collect($this->importPreview)->where('stato', 'errore_qualifica')->count();
                $totSaltate       = collect($this->importPreview)->where('stato', 'saltata')->count();
                $totCreazioniBase = collect($this->importPreview)->sum(
                    fn($r) => collect($r['aggiorna_base'] ?? [])->where('crea_base', true)->count()
                );
                $hasErrori        = $totErroriCf > 0 || $totErroriQ > 0;
            @endphp

            <x-filament::section>
                <x-slot name="heading">Anteprima import</x-slot>
                <x-slot name="description">
                    Controlla i dati prima di confermare. Le righe con errore non verranno importate.
                </x-slot>

                {{-- Tab stato — mutualmente esclusivi, "Totali" attivo di default --}}
                @php
                    $totObsolete    = collect($this->importPreview)->where('stato', 'obsoleta')->count();
                    $totImportabili = $totInserimenti + $totAggiornamenti;
                    $totProblemi    = $totErroriCf + $totErroriQ + $totSaltate;
                    $totTotale      = count($this->importPreview);

                    $tabs = [
                        ['gruppo' => null,            'label' => 'Totali',       'count' => $totTotale,      'color' => '475569', 'bg' => 'f1f5f9', 'border' => 'e2e8f0'],
                        ['gruppo' => 'inserimento',    'label' => 'Nuovi',         'count' => $totInserimenti, 'color' => '15803d', 'bg' => 'f0fdf4', 'border' => 'bbf7d0'],
                        ['gruppo' => 'aggiornamento',  'label' => 'Aggiornati',    'count' => $totAggiornamenti, 'color' => '1d4ed8', 'bg' => 'eff6ff', 'border' => 'bfdbfe'],
                        ['gruppo' => 'obsoleta',       'label' => 'Già aggiornati', 'count' => $totObsolete,   'color' => '64748b', 'bg' => 'f8fafc', 'border' => 'e2e8f0'],
                        ['gruppo' => 'problemi',       'label' => 'Errori',       'count' => $totProblemi,    'color' => 'dc2626', 'bg' => 'fef2f2', 'border' => 'fecaca'],
                    ];
                @endphp
                <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:10px; margin-bottom:12px;">
                    @foreach ($tabs as $tab)
                        @php $attivo = $this->filtroAnteprimaStato === $tab['gruppo']; @endphp
                        <div
                            wire:click="impostaFiltroAnteprima({{ $tab['gruppo'] ? "'{$tab['gruppo']}'" : 'null' }})"
                            style="background:#{{ $tab['bg'] }}; border:2px solid {{ $attivo ? '#' . $tab['color'] : '#' . $tab['border'] }}; border-radius:10px; padding:14px 16px; cursor:pointer; user-select:none;{{ $attivo ? ' box-shadow:0 0 0 2px #' . $tab['color'] . '33;' : '' }}"
                        >
                            <div style="font-size:1.6rem; font-weight:700; color:#{{ $tab['color'] }}; line-height:1;">{{ $tab['count'] }}</div>
                            <div style="font-size:0.78rem; color:#{{ $tab['color'] }}; margin-top:3px;">{{ $tab['label'] }}</div>
                        </div>
                    @endforeach
                    @if ($totCreazioniBase > 0)
                    <div style="background:#eff6ff; border:2px solid #bfdbfe; border-radius:10px; padding:14px 16px;">
                        <div style="font-size:1.6rem; font-weight:700; color:#1d4ed8; line-height:1;">{{ $totCreazioniBase }}</div>
                        <div style="font-size:0.78rem; color:#1e40af; margin-top:3px;">Qualifiche base da creare</div>
                    </div>
                    @endif
                </div>

                {{-- Ricerca testuale --}}
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:16px; flex-wrap:wrap;">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="cercaAnteprima"
                        placeholder="Cerca per nome, CF o qualifica..."
                        style="flex:1; min-width:220px; padding:7px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:0.82rem;"
                    >
                    @if ($this->filtroAnteprimaStato !== null || trim($this->cercaAnteprima) !== '')
                        <x-filament::button
                            wire:click="resetFiltriAnteprima"
                            color="gray"
                            size="sm"
                            icon="heroicon-m-x-mark"
                        >Reset filtri</x-filament::button>
                    @endif
                </div>

                {{-- Avviso errori qualifica --}}
                @if ($totErroriQ > 0)
                    <div style="display:flex; gap:10px; align-items:flex-start; margin-bottom:16px; padding:12px 16px; background:#fffbeb; border:1px solid #fde68a; border-radius:8px; color:#92400e; font-size:0.85rem;">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:18px;height:18px;flex-shrink:0;margin-top:1px;color:#d97706;">
                            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                        </svg>
                        <span><strong>Attenzione:</strong> alcune righe fanno riferimento a qualifiche non presenti nel catalogo. Aggiornare il catalogo corsi prima di importare. Le righe con errore verranno saltate automaticamente.</span>
                    </div>
                @endif

                {{-- Tabella preview con scroll fisso --}}
                @php
                    $borderColor = [
                        'inserimento'      => '#16a34a',
                        'aggiornamento'    => '#2563eb',
                        'errore_cf'        => '#dc2626',
                        'errore_qualifica' => '#d97706',
                        'saltata'          => '#d1d5db',
                        'obsoleta'         => '#cbd5e1',
                    ];
                    $rowBg = [
                        'inserimento'      => '#f0fdf4',
                        'aggiornamento'    => '#eff6ff',
                        'errore_cf'        => '#fef2f2',
                        'errore_qualifica' => '#fffbeb',
                        'saltata'          => '#f9fafb',
                        'obsoleta'         => '#f8fafc',
                    ];
                    $badgeStyle = [
                        'inserimento'      => 'background:#dcfce7;color:#166534;',
                        'aggiornamento'    => 'background:#dbeafe;color:#1e40af;',
                        'errore_cf'        => 'background:#fee2e2;color:#b91c1c;',
                        'errore_qualifica' => 'background:#fef3c7;color:#92400e;',
                        'saltata'          => 'background:#f3f4f6;color:#6b7280;',
                        'obsoleta'         => 'background:#f1f5f9;color:#64748b;',
                    ];
                    $badgeLabel = [
                        'inserimento'      => 'Nuovo',
                        'aggiornamento'    => 'Aggiorna',
                        'errore_cf'        => 'CF mancante',
                        'errore_qualifica' => 'Qual. mancante',
                        'saltata'          => 'Saltata',
                        'obsoleta'         => 'Già aggiornato',
                    ];
                @endphp

                <div style="border:1px solid #e5e7eb; border-radius:10px; overflow:hidden;">
                    <div style="max-height:520px; overflow-y:auto; overflow-x:auto;">
                        <table style="width:100%; border-collapse:collapse; font-size:0.8125rem;">
                            <thead>
                                <tr style="position:sticky; top:0; z-index:2; background:#f8fafc; border-bottom:2px solid #e2e8f0;">
                                    <th style="padding:9px 12px; text-align:left; font-size:0.7rem; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:#64748b; white-space:nowrap; width:44px;">#</th>
                                    <th style="padding:9px 12px; text-align:left; font-size:0.7rem; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:#64748b; white-space:nowrap;">Stato</th>
                                    <th style="padding:9px 12px; text-align:left; font-size:0.7rem; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:#64748b; white-space:nowrap;">Cognome Nome</th>
                                    <th style="padding:9px 12px; text-align:left; font-size:0.7rem; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:#64748b; white-space:nowrap;">C.F.</th>
                                    <th style="padding:9px 12px; text-align:left; font-size:0.7rem; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:#64748b; white-space:nowrap;">Qualifica</th>
                                    <th style="padding:9px 12px; text-align:left; font-size:0.7rem; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:#64748b; white-space:nowrap;">Acquisizione</th>
                                    <th style="padding:9px 12px; text-align:left; font-size:0.7rem; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:#64748b; white-space:nowrap;">Ultimo rinnovo</th>
                                    <th style="padding:9px 12px; text-align:left; font-size:0.7rem; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:#64748b;">Note / Azioni automatiche</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $righeFiltrate = $this->righeAnteprimaFiltrate(); @endphp
                                @forelse ($righeFiltrate as $riga)
                                    @php
                                        $stato  = $riga['stato'];
                                        $bc     = $borderColor[$stato] ?? '#d1d5db';
                                        $bg     = $rowBg[$stato] ?? '#f9fafb';
                                        $bs     = $badgeStyle[$stato] ?? 'background:#f3f4f6;color:#6b7280;';
                                        $bl     = $badgeLabel[$stato] ?? $stato;
                                    @endphp
                                    <tr style="background:{{ $bg }}; border-bottom:1px solid #f1f5f9;">
                                        <td style="padding:8px 12px; border-left:3px solid {{ $bc }}; color:#94a3b8; font-size:0.75rem; font-variant-numeric:tabular-nums;">{{ $riga['riga'] }}</td>
                                        <td style="padding:8px 12px; white-space:nowrap;">
                                            <span style="{{ $bs }} display:inline-block; padding:2px 8px; border-radius:9999px; font-size:0.72rem; font-weight:600;">{{ $bl }}</span>
                                        </td>
                                        <td style="padding:8px 12px; font-weight:500; color:#1e293b; white-space:nowrap;">{{ $riga['cognome'] }} {{ $riga['nome'] }}</td>
                                        <td style="padding:8px 12px; font-family:monospace; font-size:0.78rem; color:#475569; white-space:nowrap; letter-spacing:0.02em;">{{ $riga['cf'] }}</td>
                                        <td style="padding:8px 12px; color:#334155; max-width:200px;">{{ $riga['qualifica_nome_raw'] ?? '—' }}</td>
                                        <td style="padding:8px 12px; color:#334155; white-space:nowrap;">
                                            {{ $riga['data_acquisizione'] ?? '—' }}
                                            @if ($stato === 'aggiornamento' && !empty($riga['data_acquisizione_attuale']) && $riga['data_acquisizione_attuale'] !== $riga['data_acquisizione'])
                                                <span style="display:block; font-size:0.72rem; color:#94a3b8;">era: {{ $riga['data_acquisizione_attuale'] }}</span>
                                            @endif
                                        </td>
                                        <td style="padding:8px 12px; color:#334155; white-space:nowrap;">{{ $riga['data_ultimo_rinnovo'] ?? '—' }}</td>
                                        <td style="padding:8px 12px;">
                                            @if (!empty($riga['errore']))
                                                <span style="color:#dc2626; font-size:0.78rem;">{{ $riga['errore'] }}</span>
                                            @endif
                                            @foreach ($riga['aggiorna_base'] ?? [] as $base)
                                                <div style="margin-top:3px;">
                                                    @if ($base['socio_ha_base'])
                                                        <span style="display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:6px; background:#dbeafe; color:#1e40af; font-size:0.72rem; font-weight:500;">
                                                            ↻ aggiorna scadenza <strong>{{ $base['qualifica_codice'] }}</strong>
                                                        </span>
                                                    @elseif ($base['crea_base'] ?? false)
                                                        <span style="display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:6px; background:#f3e8ff; color:#7e22ce; font-size:0.72rem; font-weight:500;">
                                                            + crea <strong>{{ $base['qualifica_codice'] }}</strong> dal {{ $base['data_minima'] }}
                                                        </span>
                                                    @else
                                                        <span style="display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:6px; background:#fef3c7; color:#92400e; font-size:0.72rem;">
                                                            {{ $base['qualifica_codice'] }} non in archivio
                                                        </span>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" style="padding:24px 12px; text-align:center; color:#94a3b8; font-size:0.85rem;">
                                            Nessuna riga corrisponde al filtro attivo.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    {{-- Footer tabella con totale righe --}}
                    <div style="padding:8px 16px; background:#f8fafc; border-top:1px solid #e5e7eb; font-size:0.78rem; color:#64748b; display:flex; justify-content:space-between; align-items:center;">
                        <span>
                            @if ($this->filtroAnteprimaStato !== null || trim($this->cercaAnteprima) !== '')
                                {{ $righeFiltrate->count() }} righe mostrate (filtrate) su {{ $totTotale }} totali
                            @else
                                {{ $totTotale }} righe analizzate · {{ $totImportabili }} verranno importate · {{ $totObsolete }} già aggiornate · {{ $totProblemi }} da correggere
                            @endif
                        </span>
                        <span style="color:#94a3b8;">Scorri per vedere tutte le righe</span>
                    </div>
                </div>

                {{-- Azioni conferma/annulla --}}
                <div style="display:flex; gap:10px; margin-top:16px; align-items:center; flex-wrap:wrap;">
                    {{ $this->confermaImportAction }}
                    <x-filament::button
                        wire:click="annullaImport"
                        color="gray"
                        icon="heroicon-m-x-mark"
                        size="md"
                    >
                        Annulla
                    </x-filament::button>
                    @if ($totProblemi > 0)
                        <span style="font-size:0.8rem; color:#94a3b8;">{{ $totProblemi }} righe con errore verranno saltate automaticamente</span>
                    @endif
                </div>
            </x-filament::section>
        @endif

        {{-- Pannello filtri --}}
        <x-filament::section>
            <x-slot name="heading">Filtri di ricerca</x-slot>
            <x-slot name="description">Seleziona i criteri e avvia la ricerca per visualizzare i soci.</x-slot>
            {{ $this->form }}
        </x-filament::section>

        {{-- Risultati ricerca --}}
        @if ($this->ricercaAvviata)
        <div class="space-y-4">

            {{-- Legenda colori scadenza: mostrata se almeno una qualifica richiesta ha scadenza --}}
            @php
                $tipoScadenza = null;
                foreach ($this->qualificheRichieste as $_qid) {
                    $__q = \App\Models\CatalogoCorso::find($_qid);
                    if ($__q && $__q->scadenza_tipo && $__q->scadenza_tipo !== 'nessuna') {
                        $tipoScadenza = $__q->scadenza_tipo;
                        break;
                    }
                }
            @endphp

            @if ($tipoScadenza !== null)
                <div style="border:1px solid #e5e7eb; background:#f9fafb; border-radius:0.5rem; padding:1rem 1.25rem; font-size:0.8125rem; color:#4b5563;">
                    <div style="display:flex; align-items:flex-start; gap:0.5rem; margin-bottom:1rem; color:#6b7280; line-height:1.5;">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:15px;height:15px;flex-shrink:0;margin-top:1px;">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-7-4a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM9 9a.75.75 0 0 0 0 1.5h.253a.25.25 0 0 1 .244.304l-.459 2.066A1.75 1.75 0 0 0 10.747 15H11a.75.75 0 0 0 0-1.5h-.253a.25.25 0 0 1-.244-.304l.459-2.066A1.75 1.75 0 0 0 9.253 9H9Z" clip-rule="evenodd"/>
                        </svg>
                        <span>Il colore della data di scadenza dipende dal criterio applicato alla qualifica. Le regioni con validità regionale personalizzata potrebbero applicare un criterio diverso da quello nazionale.</span>
                    </div>
                    <div style="display:flex; flex-wrap:wrap; gap:1.5rem;">
                        <div style="flex:1; min-width:230px;">
                            <div style="font-weight:600; color:#374151; margin-bottom:0.5rem; font-size:0.8rem; text-transform:uppercase; letter-spacing:.04em;">Data esatta (mesi)</div>
                            <div style="display:flex; flex-direction:column; gap:0.375rem;">
                                <span style="display:inline-flex; align-items:center; gap:0.5rem;">
                                    <span style="display:inline-block; width:10px; height:10px; border-radius:9999px; background:#22c55e; flex-shrink:0;"></span>
                                    Valida — scade tra più di 6 mesi
                                </span>
                                <span style="display:inline-flex; align-items:center; gap:0.5rem;">
                                    <span style="display:inline-block; width:10px; height:10px; border-radius:9999px; background:#facc15; flex-shrink:0;"></span>
                                    In scadenza — entro 6 mesi
                                </span>
                                <span style="display:inline-flex; align-items:center; gap:0.5rem;">
                                    <span style="display:inline-block; width:10px; height:10px; border-radius:9999px; background:#ef4444; flex-shrink:0;"></span>
                                    Scaduta
                                </span>
                            </div>
                        </div>
                        <div style="width:1px; background:#e5e7eb; flex-shrink:0;"></div>
                        <div style="flex:1; min-width:230px;">
                            <div style="font-weight:600; color:#374151; margin-bottom:0.5rem; font-size:0.8rem; text-transform:uppercase; letter-spacing:.04em;">Anno solare</div>
                            <div style="display:flex; flex-direction:column; gap:0.375rem;">
                                <span style="display:inline-flex; align-items:center; gap:0.5rem;">
                                    <span style="display:inline-block; width:10px; height:10px; border-radius:9999px; background:#22c55e; flex-shrink:0;"></span>
                                    Valida — scade l'anno prossimo o oltre
                                </span>
                                <span style="display:inline-flex; align-items:center; gap:0.5rem;">
                                    <span style="display:inline-block; width:10px; height:10px; border-radius:9999px; background:#facc15; flex-shrink:0;"></span>
                                    In scadenza — scade entro fine anno
                                </span>
                                <span style="display:inline-flex; align-items:center; gap:0.5rem;">
                                    <span style="display:inline-block; width:10px; height:10px; border-radius:9999px; background:#ef4444; flex-shrink:0;"></span>
                                    Scaduta — anno di scadenza già passato
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{ $this->table }}

        </div>{{-- /space-y-4 --}}
        @else
            <x-filament::section>
                <div class="flex flex-col items-center justify-center py-12 text-center gap-3">
                    <x-filament::icon
                        icon="heroicon-o-funnel"
                        class="h-12 w-12 text-gray-300"
                    />
                    <p class="text-gray-500 text-sm">
                        Seleziona almeno un filtro e clicca <strong>Avvia ricerca</strong> per visualizzare i soci.
                    </p>
                </div>
            </x-filament::section>
        @endif

    </div>
</x-filament-panels::page>
