<x-filament-panels::page>
    @php
        $evento = $this->record;
        $corsi  = $evento->corsi->sortBy('id');
        $stats  = $this->stats;

        // Dati presenze (pre-calcolati in GestioneEvento.php da presenze_evento)
        $righePresenze   = $this->righePresenze;
        $pernotti        = $this->pernotti;
        $listaPernottati = collect($this->listaPernottati);
        $totali          = $this->totali;

        // Colazioni: già dedotte dal pernotto effettivo e incluse in $righePresenze da GrigliaPastiBuilder.
        $coloreTipo = ['pranzo' => '#f59e0b', 'cena' => '#8b5cf6', 'pernotto' => '#0ea5e9', 'colazione' => '#f97316'];
        $labelTipo  = ['pranzo' => 'Pranzi',  'cena' => 'Cene',    'pernotto' => 'Pernotti', 'colazione' => 'Colazioni'];
        $bgTipo     = ['pranzo' => '#fffbeb', 'cena' => '#f5f3ff', 'pernotto' => '#f0f9ff',  'colazione' => '#fff7ed'];
    @endphp

    {{-- Intestazione ──────────────────────────────────────────────────────── --}}
    <x-filament::section>
        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap;">
            <div style="display:flex; flex-direction:column; gap:4px;">
                <h2 style="font-size:18px; font-weight:700; color:#111827; margin:0;">{{ $evento->nome }}</h2>
                <span style="font-size:13px; color:#6b7280;">
                    {{ $evento->data_inizio->format('d/m/Y') }}
                    @if(!$evento->data_inizio->eq($evento->data_fine)) — {{ $evento->data_fine->format('d/m/Y') }} @endif
                    @if($evento->sede) · {{ $evento->sede }} @endif
                </span>
                <span style="font-size:12px; color:#9ca3af;">{{ $corsi->count() }} {{ $corsi->count() === 1 ? 'corso' : 'corsi' }}</span>
            </div>
            @if($evento->referenti->isNotEmpty())
            <div style="min-width:200px; background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:10px 14px;">
                <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:#0369a1; margin-bottom:6px;">Referenti</div>
                @foreach($evento->referenti as $ref)
                <div style="display:flex; align-items:center; gap:8px; {{ !$loop->last ? 'margin-bottom:6px;' : '' }}">
                    <div>
                        <div style="font-size:13px; font-weight:600; color:#0c4a6e;">{{ $ref->name }}</div>
                        <a href="mailto:{{ $ref->email }}" style="font-size:12px; color:#0284c7; text-decoration:none;">{{ $ref->email }}</a>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </x-filament::section>

    {{-- Statistiche globali ─────────────────────────────────────────────────── --}}

    {{-- Partecipanti unici (visione aggregata) --}}
    <x-filament::section>
        <div style="text-align:center; padding:2px 0;">
            <div style="font-size:32px; font-weight:800; color:#111827;">{{ $stats['partecipanti_unici'] }}</div>
            <div style="font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:#6b7280; margin-top:2px;">Partecipanti unici all'evento</div>
            <div style="font-size:11px; color:#9ca3af; margin-top:4px;">Iscritti ai corsi + Staff, senza duplicati tra ruoli e tra corsi</div>
        </div>
    </x-filament::section>

    {{-- Riga iscrizioni ai corsi --}}
    <div style="display:grid; grid-template-columns:repeat(5,1fr); gap:12px;">
        @foreach([
            [$stats['iscrizioni_totale'],    'Iscrizioni corsi',    '#111827'],
            [$stats['iscritti_inviati'],     'Form inviati',        '#2563eb'],
            [$stats['iscritti_compilati'],   'Form compilati',      '#16a34a'],
            [$stats['iscritti_senza_email'], 'Senza email',         $stats['iscritti_senza_email'] > 0 ? '#d97706' : '#9ca3af'],
        ] as [$val, $label, $color])
            <x-filament::section>
                <div style="text-align:center; padding:4px 0;">
                    <div style="font-size:26px; font-weight:700; color:{{ $color }};">{{ $val }}</div>
                    <div style="font-size:11px; color:#6b7280; margin-top:2px;">{{ $label }}</div>
                </div>
            </x-filament::section>
        @endforeach
        <x-filament::section>
            <div style="text-align:center; padding:4px 0;">
                <div style="font-size:26px; font-weight:700; color:{{ $conflittiCount > 0 ? '#dc2626' : '#9ca3af' }};">{{ $conflittiCount }}</div>
                <div style="font-size:11px; color:#6b7280; margin-top:2px;">{{ $conflittiCount > 0 ? '⚠ Sovrapposizioni' : 'Sovrapposizioni' }}
                </div>
            </div>
        </x-filament::section>
    </div>

    {{-- Riga staff --}}
    <div style="display:grid; grid-template-columns:repeat(5,1fr); gap:12px;">
        @foreach([
            [$stats['staff_totale'],        'Staff evento',        '#111827'],
            [$stats['staff_inviati'],       'Form staff inviati',  '#2563eb'],
            [$stats['staff_compilati'],     'Form staff compilati','#16a34a'],
            [$stats['staff_senza_email'],   'Senza email',         $stats['staff_senza_email'] > 0 ? '#d97706' : '#9ca3af'],
            [$stats['staff_anche_iscritti'],'Anche iscritti',      $stats['staff_anche_iscritti'] > 0 ? '#7c3aed' : '#9ca3af'],
        ] as [$val, $label, $color])
            <x-filament::section>
                <div style="text-align:center; padding:4px 0;">
                    <div style="font-size:26px; font-weight:700; color:{{ $color }};">{{ $val }}</div>
                    <div style="font-size:11px; color:#6b7280; margin-top:2px;">{{ $label }}</div>
                </div>
            </x-filament::section>
        @endforeach
    </div>

    {{-- Sovrapposizioni iscrizioni ──────────────────────────────────────────── --}}
    @if($conflittiCount > 0)
        <x-filament::section>
            <x-slot name="heading">
                <span style="color:#dc2626;">⚠ Sovrapposizioni iscrizioni — {{ $conflittiCount }} {{ $conflittiCount === 1 ? 'partecipante' : 'partecipanti' }}</span>
            </x-slot>
            <x-slot name="description">
                I seguenti partecipanti risultano iscritti a corsi con date sovrapposte. Verifica e contattali per risolvere il conflitto.
            </x-slot>

            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; font-size:13px;">
                    <thead>
                        <tr style="border-bottom:2px solid #fecaca; background:#fff5f5;">
                            <th style="text-align:left; padding:10px 14px; font-weight:600; color:#374151; white-space:nowrap;">Partecipante</th>
                            <th style="text-align:left; padding:10px 14px; font-weight:600; color:#374151;">Corso A</th>
                            <th style="text-align:center; padding:10px 8px; color:#9ca3af; font-size:16px;">⇄</th>
                            <th style="text-align:left; padding:10px 14px; font-weight:600; color:#374151;">Corso B</th>
                            <th style="text-align:center; padding:10px 14px; font-weight:600; color:#374151;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($conflittiDettaglio as $idx => $c)
                            <tr style="border-bottom:1px solid #fee2e2; background:{{ $idx % 2 === 0 ? '#ffffff' : '#fff9f9' }};">
                                <td style="padding:10px 14px; white-space:nowrap;">
                                    <div style="font-weight:600; color:#111827;">{{ $c['partecipante'] ?: '—' }}</div>
                                    @if($c['codice_fiscale'])
                                        <div style="font-family:monospace; font-size:11px; color:#6b7280; margin-top:2px;">{{ $c['codice_fiscale'] }}</div>
                                    @endif
                                    @if($c['email'])
                                        <div style="font-size:11px; color:#9ca3af;">{{ $c['email'] }}</div>
                                    @endif
                                </td>
                                <td style="padding:10px 14px;">
                                    <div style="font-weight:500; color:#111827;">{{ $c['corso_a_nome'] }}</div>
                                    <div style="font-size:11px; color:#6b7280; margin-top:2px;">{{ $c['corso_a_date'] }}</div>
                                    @if($c['corso_a_event'])<div style="font-size:11px; color:#9ca3af;">{{ $c['corso_a_event'] }}</div>@endif
                                </td>
                                <td style="padding:10px 8px; text-align:center; color:#dc2626; font-size:18px; vertical-align:middle;">⇄</td>
                                <td style="padding:10px 14px;">
                                    <div style="font-weight:500; color:#111827;">{{ $c['corso_b_nome'] }}</div>
                                    <div style="font-size:11px; color:#6b7280; margin-top:2px;">{{ $c['corso_b_date'] }}</div>
                                    @if($c['corso_b_event'])<div style="font-size:11px; color:#9ca3af;">{{ $c['corso_b_event'] }}</div>@endif
                                </td>
                                <td style="padding:10px 14px; text-align:center; vertical-align:middle;">
                                    @if($c['email'])
                                        <button
                                            wire:click="mountAction('inviaMailSovrapposizione', @js(['partecipante_id' => $c['partecipante_id']]))"
                                            style="display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:500; color:#2563eb; background:#eff6ff; border:1px solid #bfdbfe; border-radius:6px; padding:5px 10px; cursor:pointer; white-space:nowrap;"
                                            type="button"
                                            title="Invia mail di segnalazione a {{ $c['email'] }}">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                            Invia mail
                                        </button>
                                    @else
                                        <span style="font-size:11px; color:#d1d5db;">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    {{-- Comunicazione evento agli iscritti ─────────────────────────────────── --}}
    @php
        $hasComunicazione = $evento->hasComunicazioneValida();
    @endphp
    <x-filament::section>
        <x-slot name="heading">Comunicazione iscritti</x-slot>
        <x-slot name="description">
            @if($hasComunicazione)
                Invia la comunicazione informativa sull'evento (testo + allegati) agli iscritti di ogni corso.
            @else
                Nessun contenuto disponibile. Inserisci del testo o dei documenti nella sezione <strong>Contenuti e documenti</strong> dell'evento per abilitare l'invio.
            @endif
        </x-slot>

        @if($hasComunicazione)
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;">
                <x-filament::button
                    wire:click="mountAction('anteprimaInfoEvento')"
                    icon="heroicon-o-eye"
                    color="gray"
                    size="sm"
                    tag="button"
                    type="button"
                >Anteprima comunicazione</x-filament::button>
            </div>
        @endif

        {{-- Righe corso sempre visibili (non solo se $hasComunicazione): i pulsanti vanno
             disabilitati con un motivo, non far sparire l'intero blocco — stessa convenzione
             disabled+tooltip usata ovunque nel pannello per "non applicabile ora". --}}
        <div style="display:flex;flex-direction:column;gap:8px;">
            @foreach($corsi as $corso)
                @php
                    $nIscritti = $corso->iscrizioni->count();
                    $infoAt    = $corso->invio_massivo_info_at;
                    $infoPendenti   = $this->iscrizioniInfoPendentiPerCorso($corso)->count();
                    $corsoConcluso  = today()->gt($corso->data_fine);
                    $corsoAnnullato = $corso->isAnnullato();
                    $tooltipInvio = ! $hasComunicazione
                        ? 'Nessuna informazione definita da inviare: entra nella modifica evento per inserire testo o allegati'
                        : ($corsoAnnullato
                            ? 'Corso annullato: invio non disponibile'
                            : ($corsoConcluso
                                ? 'Corso concluso: invio non più disponibile'
                                : ($nIscritti === 0
                                    ? 'Nessun iscritto'
                                    : ($infoPendenti === 0 ? 'Tutti gli iscritti hanno già ricevuto la comunicazione' : null))));
                @endphp
                <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;gap:12px;flex-wrap:wrap;">
                    <div>
                        <span style="font-weight:600;font-size:13px;">{{ $corso->nome }}</span>
                        @if($corso->numero_protocollo)
                            <span style="font-size:11px;color:#6b7280;font-family:monospace;margin-left:6px;">{{ $corso->numero_protocollo }}</span>
                        @endif
                        <span style="font-size:12px;color:#9ca3af;margin-left:8px;">· {{ $nIscritti }} {{ $nIscritti === 1 ? 'iscritto' : 'iscritti' }}</span>
                        @if($infoAt)
                            <span style="font-size:11px;color:#16a34a;margin-left:8px;">✓ Ultimo invio: {{ $infoAt->format('d/m/Y H:i') }}</span>
                        @endif
                    </div>
                    <x-filament::button
                        wire:click="inviaInfoCorsisti({{ $corso->id }})"
                        wire:loading.attr="disabled"
                        wire:target="inviaInfoCorsisti({{ $corso->id }})"
                        icon="{{ $infoPendenti === 0 ? 'heroicon-o-check-circle' : 'heroicon-o-information-circle' }}"
                        color="{{ $infoPendenti === 0 ? 'gray' : 'warning' }}"
                        size="sm"
                        tag="button"
                        type="button"
                        :disabled="! $hasComunicazione || $nIscritti === 0 || $corsoConcluso || $corsoAnnullato || $infoPendenti === 0"
                        :tooltip="$tooltipInvio"
                    >
                        {{ $infoPendenti === 0 ? 'Nessuno da inviare' : "Invia a tutti ({$infoPendenti})" }}
                    </x-filament::button>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    {{-- Griglia pasti (pranzo/cena) ─────────────────────────────────────────── --}}
    @if(!empty($righePresenze))
        <x-filament::section>
            <x-slot name="heading">Griglia pasti</x-slot>
            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; font-size:13px;">
                    <thead>
                        <tr style="border-bottom:2px solid #e5e7eb;">
                            <th style="text-align:left; padding:8px 12px; font-weight:600; color:#374151;">Pasto</th>
                            <th style="text-align:center; padding:8px 12px; font-weight:600; color:#9ca3af;">Potenziali</th>
                            <th style="text-align:center; padding:8px 12px; font-weight:600; color:#374151;">Effettivi</th>
                            <th style="text-align:left; padding:8px 12px; font-weight:600; color:#991b1b;">Allergeni</th>
                            <th style="text-align:left; padding:8px 12px; font-weight:600; color:#92400e;">Regimi alimentari</th>
                            <th style="text-align:left; padding:8px 12px; font-weight:600; color:#374151;">Altre annotazioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($righePresenze as $idx => $riga)
                            @php
                                $allergeniPasto = collect();
                                $dietePasto     = collect();
                                $notePasto      = collect();
                                foreach ($riga['personeConEsigenze'] as $pe) {
                                    foreach ($pe['allergeni'] as $a) $allergeniPasto->push($a);
                                    foreach ($pe['diete']     as $d) $dietePasto->push($d);
                                    if (!empty($pe['note'])) $notePasto->push($pe['note']);
                                }
                                $allergeniPasto = $allergeniPasto->countBy()->sortDesc();
                                $dietePasto     = $dietePasto->countBy()->sortDesc();
                                $noteUniche     = $notePasto->unique()->values();
                            @endphp
                            <tr style="background:{{ $idx % 2 === 1 ? '#f9fafb' : 'white' }}; border-bottom:1px solid #f3f4f6;">
                                <td style="padding:10px 12px;">
                                    <span style="display:inline-block; font-size:10px; font-weight:700; text-transform:uppercase; background:{{ $bgTipo[$riga['tipo']] }}; color:{{ $coloreTipo[$riga['tipo']] }}; border-radius:4px; padding:1px 6px; margin-right:6px;">
                                        {{ $labelTipo[$riga['tipo']] }}
                                    </span>
                                    <span style="color:#374151;">{{ \Carbon\Carbon::parse($riga['data'])->translatedFormat('d/m') }}</span>
                                    <div style="font-size:11px; color:#9ca3af; margin-top:1px;">{{ $riga['label'] }}</div>
                                </td>
                                <td style="text-align:center; padding:10px 12px; color:#9ca3af; font-weight:600;">{{ $riga['potenziale'] }}</td>
                                <td style="text-align:center; padding:10px 12px;">
                                    <span style="font-size:15px; font-weight:700; color:{{ $riga['effettivo'] > 0 ? '#111827' : '#d1d5db' }};">{{ $riga['effettivo'] }}</span>
                                </td>
                                <td style="padding:10px 12px;">
                                    @if($allergeniPasto->isNotEmpty())
                                        <div style="display:flex; flex-wrap:wrap; gap:4px;">
                                            @foreach($allergeniPasto as $nome => $count)
                                                <span style="font-size:11px; background:#fee2e2; color:#991b1b; border-radius:4px; padding:1px 7px; white-space:nowrap;">{{ $nome }} <strong>×{{ $count }}</strong></span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span style="font-size:11px; color:#d1d5db;">—</span>
                                    @endif
                                </td>
                                <td style="padding:10px 12px;">
                                    @if($dietePasto->isNotEmpty())
                                        <div style="display:flex; flex-wrap:wrap; gap:4px;">
                                            @foreach($dietePasto as $nome => $count)
                                                <span style="font-size:11px; background:#fef3c7; color:#92400e; border-radius:4px; padding:1px 7px; white-space:nowrap;">{{ $nome }} <strong>×{{ $count }}</strong></span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span style="font-size:11px; color:#d1d5db;">—</span>
                                    @endif
                                </td>
                                <td style="padding:10px 12px; font-size:11px; color:#374151; font-style:italic;">
                                    @if($noteUniche->isNotEmpty())
                                        @foreach($noteUniche as $nota)
                                            <div>– {{ $nota }}</div>
                                        @endforeach
                                    @else
                                        <span style="color:#d1d5db;">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif

    {{-- Elenco nominativo per pasto: tutti i previsti, con le esigenze di chi ne ha ───────── --}}
    @php $pastiConPersone = collect($righePresenze)->filter(fn($r) => !empty($r['persone'])); @endphp
    @if($pastiConPersone->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">Esigenze alimentari per pasto — dettaglio nominativo</x-slot>
            <x-slot name="description">Per ogni pasto tutte le persone previste; accanto al nome, allergeni, diete e note di chi ne ha.</x-slot>

            <div style="border:1px solid #e5e7eb; border-radius:10px; overflow:hidden;">
                {{-- Intestazione colonne --}}
                <div style="display:grid; grid-template-columns:1fr 90px 110px 24px; gap:0; padding:8px 16px; background:#f9fafb; border-bottom:2px solid #e5e7eb;">
                    <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:#9ca3af;">Pasto</div>
                    <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:#9ca3af; text-align:center;">Presenti</div>
                    <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:#d97706; text-align:center;">Con esigenze</div>
                    <div></div>
                </div>

                @foreach($pastiConPersone as $idx => $riga)
                    @php $nPersone = count($riga['persone']); $nEsigenze = count($riga['personeConEsigenze']); @endphp
                    <details style="{{ !$loop->last ? 'border-bottom:1px solid #f3f4f6;' : '' }}">
                        <summary style="display:grid; grid-template-columns:1fr 90px 110px 24px; align-items:center; gap:0; padding:11px 16px; cursor:pointer; list-style:none; user-select:none; background:{{ $loop->iteration % 2 === 0 ? '#fafafa' : 'white' }};">
                            <div>
                                <span style="display:inline-block; font-size:10px; font-weight:700; text-transform:uppercase; background:{{ $bgTipo[$riga['tipo']] }}; color:{{ $coloreTipo[$riga['tipo']] }}; border-radius:4px; padding:1px 6px; margin-right:6px;">{{ $labelTipo[$riga['tipo']] }}</span>
                                <span style="font-size:13px; color:#374151; font-weight:500;">{{ \Carbon\Carbon::parse($riga['data'])->translatedFormat('l j F') }}</span>
                            </div>
                            <span style="font-size:14px; font-weight:600; color:#374151; text-align:center;">{{ $riga['effettivo'] }}</span>
                            <span style="font-size:14px; font-weight:700; color:{{ $nEsigenze ? '#d97706' : '#d1d5db' }}; text-align:center;">{{ $nEsigenze ?: '—' }}</span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; justify-self:center;">
                                <polyline points="6 9 12 15 18 9"/>
                            </svg>
                        </summary>

                        <div style="border-top:1px solid #f3f4f6; background:#fffdf5;">
                            @foreach($riga['persone'] as $pi => $persona)
                                <div style="display:flex; align-items:flex-start; gap:12px; padding:9px 24px; {{ $pi < $nPersone - 1 ? 'border-bottom:1px solid #f3f4f6;' : '' }} {{ $pi % 2 === 1 ? 'background:#faf9f0;' : '' }}">
                                    <div style="flex-shrink:0; font-size:13px; font-weight:600; color:#111827; min-width:180px;">{{ $persona['nome'] }}</div>
                                    <div style="flex:1; display:flex; flex-wrap:wrap; gap:4px; align-items:center;">
                                        @foreach($persona['allergeni'] as $a)
                                            <span style="font-size:11px; background:#fee2e2; color:#991b1b; border-radius:4px; padding:1px 6px;">{{ $a }}</span>
                                        @endforeach
                                        @foreach($persona['diete'] as $d)
                                            <span style="font-size:11px; background:#fef3c7; color:#92400e; border-radius:4px; padding:1px 6px;">{{ $d }}</span>
                                        @endforeach
                                        @if($persona['note'])
                                            <span style="font-size:11px; color:#6b7280; font-style:italic;">{{ $persona['note'] }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </details>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    {{-- Pernotti ────────────────────────────────────────────────────────────── --}}
    @if(!empty($pernotti))
        <x-filament::section>
            <x-slot name="heading">Pernotti</x-slot>
            <x-slot name="headerEnd">
                @if($listaPernottati->isNotEmpty())
                    <a href="{{ route('eventi.pernotti-export', $evento) }}"
                       target="_blank"
                       style="display:inline-flex; align-items:center; gap:6px; font-size:13px; font-weight:500; color:white; background:#16a34a; border-radius:8px; padding:6px 14px; text-decoration:none;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" style="flex-shrink:0">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Scarica Excel
                    </a>
                @endif
            </x-slot>

            {{-- Totali --}}
            @php
                $pernottantiStaff    = $listaPernottati->where('is_staff', true)->count();
                $pernottantiDiscenti = $listaPernottati->where('is_staff', false)->count();
            @endphp
            <div style="display:grid; grid-template-columns:repeat(5,1fr); gap:12px; margin-bottom:20px;">
                <div style="text-align:center; padding:12px; background:#f9fafb; border-radius:10px;">
                    <div style="font-size:22px; font-weight:700; color:#9ca3af;">{{ $totali['pernotti']['pot'] }}</div>
                    <div style="font-size:11px; color:#6b7280; margin-top:2px;">Pernotti potenziali</div>
                </div>
                <div style="text-align:center; padding:12px; background:#f0f9ff; border-radius:10px;">
                    <div style="font-size:22px; font-weight:700; color:#0ea5e9;">{{ $totali['pernotti']['eff'] }}</div>
                    <div style="font-size:11px; color:#6b7280; margin-top:2px;">Pernotti richiesti</div>
                </div>
                <div style="text-align:center; padding:12px; background:#eff6ff; border-radius:10px;">
                    <div style="font-size:22px; font-weight:700; color:#3b82f6;">{{ $pernottantiDiscenti }}</div>
                    <div style="font-size:11px; color:#6b7280; margin-top:2px;">Discenti</div>
                </div>
                <div style="text-align:center; padding:12px; background:#fff7ed; border-radius:10px;">
                    <div style="font-size:22px; font-weight:700; color:#f97316;">{{ $pernottantiStaff }}</div>
                    <div style="font-size:11px; color:#6b7280; margin-top:2px;">Staff</div>
                </div>
                <div style="text-align:center; padding:12px; background:#fff7ed; border-radius:10px;">
                    <div style="font-size:22px; font-weight:700; color:#f97316;">{{ $totali['colazioni']['eff'] }}</div>
                    <div style="font-size:11px; color:#6b7280; margin-top:2px;">Colazioni (da pernotti)</div>
                </div>
            </div>

            {{-- Righe per notte, espandibili --}}
            <div style="border:1px solid #e5e7eb; border-radius:10px; overflow:hidden;">
                {{-- Intestazione colonne --}}
                <div style="display:grid; grid-template-columns:1fr 80px 80px 80px 80px 24px; gap:0; padding:8px 16px; background:#f9fafb; border-bottom:2px solid #e5e7eb;">
                    <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:#9ca3af;">Notte</div>
                    <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:#9ca3af; text-align:center;">Potenziali</div>
                    <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:#0ea5e9; text-align:center;">Totale</div>
                    <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:#3b82f6; text-align:center;">Discenti</div>
                    <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:#f97316; text-align:center;">Staff</div>
                    <div></div>
                </div>

                @foreach($pernotti as $idx => $notte)
                    @php
                        $discentiNottePrev = array_values(array_filter($notte['persone'] ?? [], fn($p) => !$p['is_staff']));
                        $staffNottePrev    = array_values(array_filter($notte['persone'] ?? [], fn($p) => $p['is_staff']));
                        $anteprimaNomi     = collect($notte['persone'] ?? [])->take(3)->pluck('nome')->implode(', ');
                        $altriNotti        = max(0, count($notte['persone'] ?? []) - 3);
                    @endphp
                    <details style="{{ $idx < count($pernotti) - 1 ? 'border-bottom:1px solid #f3f4f6;' : '' }}">
                        <summary style="display:grid; grid-template-columns:1fr 80px 80px 80px 80px 24px; align-items:center; gap:0; padding:11px 16px; cursor:pointer; list-style:none; user-select:none; background:{{ $idx % 2 === 1 ? '#fafafa' : 'white' }};">
                            <div>
                                <span style="font-size:13px; color:#374151; font-weight:500;">{{ $notte['label'] }}</span>
                                @if($anteprimaNomi)
                                    <div style="font-size:11px; color:#9ca3af; margin-top:2px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:320px;">
                                        {{ $anteprimaNomi }}{{ $altriNotti > 0 ? ' +' . $altriNotti . ' altri' : '' }}
                                    </div>
                                @endif
                            </div>
                            <span style="font-size:14px; font-weight:600; color:#9ca3af; text-align:center;">{{ $notte['potenziale'] }}</span>
                            <span style="font-size:14px; font-weight:700; color:{{ $notte['effettivo'] > 0 ? '#0ea5e9' : '#d1d5db' }}; text-align:center;">{{ $notte['effettivo'] }}</span>
                            <span style="font-size:14px; font-weight:700; color:{{ count($discentiNottePrev) > 0 ? '#3b82f6' : '#d1d5db' }}; text-align:center;">{{ count($discentiNottePrev) }}</span>
                            <span style="font-size:14px; font-weight:700; color:{{ count($staffNottePrev) > 0 ? '#f97316' : '#d1d5db' }}; text-align:center;">{{ count($staffNottePrev) }}</span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0; justify-self:center;">
                                <polyline points="6 9 12 15 18 9"/>
                            </svg>
                        </summary>

                        @if(!empty($notte['persone']))
                            @php
                                $discentiNotte = $discentiNottePrev;
                                $staffNotte    = $staffNottePrev;
                            @endphp
                            <div style="border-top:1px solid #f3f4f6; background:#f8faff;">
                                @if(!empty($discentiNotte))
                                    @php
                                        $discentiPerCorso = collect($discentiNotte)->groupBy('corso')->sortKeys();
                                        $corsiCount = $discentiPerCorso->count();
                                    @endphp
                                    <div style="padding:7px 24px; background:#eff6ff; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:#3b82f6; border-bottom:1px solid #dbeafe;">
                                        Discenti ({{ count($discentiNotte) }})
                                    </div>
                                    @foreach($discentiPerCorso as $corsoNome => $personeDiCorso)
                                        @php
                                            $primoDelCorso     = $personeDiCorso->first();
                                            $protocolloCorso   = $primoDelCorso['corso_protocollo'] ?? null;
                                            $labelCorso        = $corsoNome . ($protocolloCorso ? ' (' . $protocolloCorso . ')' : '');
                                            $corsoIsLast       = $loop->last;
                                            $personeDiCorsoArr = $personeDiCorso->values()->all();
                                        @endphp
                                        <details style="{{ (!$corsoIsLast || !empty($staffNotte)) ? 'border-bottom:1px solid #e0eaff;' : '' }}">
                                            <summary style="display:flex; align-items:center; justify-content:space-between; padding:8px 24px; cursor:pointer; list-style:none; user-select:none; background:#f5f8ff;">
                                                <div style="display:flex; align-items:center; gap:8px; flex:1; min-width:0;">
                                                    <span style="font-size:12px; font-weight:600; color:#2563eb;">{{ $labelCorso }}</span>
                                                </div>
                                                <div style="display:flex; align-items:center; gap:10px; flex-shrink:0;">
                                                    <span style="font-size:12px; font-weight:700; color:#3b82f6; background:#dbeafe; border-radius:9999px; padding:1px 9px;">{{ count($personeDiCorsoArr) }}</span>
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                                                </div>
                                            </summary>
                                            <div style="background:#fafbff;">
                                                @foreach($personeDiCorsoArr as $pi => $persona)
                                                    <div style="display:flex; align-items:center; gap:12px; padding:7px 36px; {{ $pi < count($personeDiCorsoArr) - 1 ? 'border-bottom:1px solid #f0f4f8;' : '' }} {{ $pi % 2 === 1 ? 'background:#f5f7ff;' : '' }}">
                                                        <div style="flex:1; min-width:0;">
                                                            <span style="font-size:13px; font-weight:600; color:#111827;">{{ $persona['nome'] }}</span>
                                                        </div>
                                                        @if($persona['email'])
                                                            <span style="font-size:11px; color:#6b7280; flex-shrink:0;">{{ $persona['email'] }}</span>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </details>
                                    @endforeach
                                @endif
                                @if(!empty($staffNotte))
                                    <div style="padding:7px 24px; background:#fff7ed; font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.06em; color:#f97316; border-bottom:1px solid #fed7aa; {{ !empty($discentiNotte) ? 'border-top:1px solid #e5e7eb;' : '' }}">
                                        Staff ({{ count($staffNotte) }})
                                    </div>
                                    @foreach($staffNotte as $pi => $persona)
                                        <div style="display:flex; align-items:center; gap:12px; padding:8px 24px; {{ $pi < count($staffNotte) - 1 ? 'border-bottom:1px solid #f0f4f8;' : '' }}">
                                            <div style="flex:1; min-width:0;">
                                                <span style="font-size:13px; font-weight:600; color:#111827;">{{ $persona['nome'] }}</span>
                                                <span style="font-size:11px; color:#f97316; margin-left:8px;">Staff evento</span>
                                            </div>
                                            @if($persona['email'])
                                                <span style="font-size:11px; color:#6b7280; flex-shrink:0;">{{ $persona['email'] }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                        @else
                            <div style="padding:10px 24px; font-size:12px; color:#9ca3af; border-top:1px solid #f3f4f6; background:#f8faff;">
                                Nessuna presenza registrata per questa notte.
                            </div>
                        @endif
                    </details>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    {{-- Riepilogo per corso ─────────────────────────────────────────────────── --}}
    <x-filament::section>
        <x-slot name="heading">Riepilogo per corso</x-slot>
        <div style="display:flex; flex-direction:column; gap:0;">
            @forelse($corsi->sortBy('data_inizio') as $corso)
                @php
                    $cIscritti  = $corso->iscrizioni->count();
                    $cInviati   = $corso->iscrizioni->whereNotNull('form_inviato_at')->count();
                    $cCompilati = $corso->iscrizioni->filter(fn($i) => $i->rispostaPreregistrazione !== null)->count();
                @endphp
                <div style="display:flex; align-items:center; gap:12px; padding:10px 0; border-bottom:1px solid #f3f4f6; flex-wrap:wrap;">
                    <x-filament::icon-button
                        tag="a"
                        href="{{ \App\Filament\Resources\CorsoResource::getUrl('gestione', ['record' => $corso]) }}"
                        target="_blank"
                        icon="heroicon-o-arrow-top-right-on-square"
                        color="info"
                        size="sm"
                        tooltip="Vai a Gestione corso"
                    />
                    <div style="flex:1; min-width:0;">
                        <div style="font-size:13px; font-weight:600; color:#111827;">{{ $corso->nome }}</div>
                        <div style="font-size:11px; color:#9ca3af; margin-top:1px;">
                            {{ $corso->data_inizio->format('d/m/Y') }}
                            @if(!$corso->data_inizio->eq($corso->data_fine)) — {{ $corso->data_fine->format('d/m/Y') }} @endif
                            @if($corso->numero_protocollo) · <span style="font-family:monospace;">{{ $corso->numero_protocollo }}</span> @endif
                        </div>
                    </div>
                    <div style="display:flex; gap:8px; flex-shrink:0; flex-wrap:wrap;">
                        <x-filament::badge color="gray">{{ $cIscritti }} iscritti</x-filament::badge>
                        <x-filament::badge color="info">{{ $cInviati }} inviati</x-filament::badge>
                        <x-filament::badge color="success">{{ $cCompilati }} compilati</x-filament::badge>
                    </div>
                    <a href="{{ route('corsi.badge-corsisti', ['corso' => $corso]) }}"
                       target="_blank"
                       style="font-size:12px; color:#16a34a; text-decoration:none; flex-shrink:0;">Badge ↗</a>
                    <a href="{{ \App\Filament\Resources\CorsoResource::getUrl('gestione', ['record' => $corso]) }}"
                       style="font-size:12px; color:#2563eb; text-decoration:none; flex-shrink:0;">Gestione →</a>
                </div>
            @empty
                <div style="text-align:center; padding:24px; font-size:13px; color:#9ca3af;">Nessun corso associato.</div>
            @endforelse
        </div>
    </x-filament::section>

    {{-- Sezione Ospiti --}}
    <x-filament::section>
        <x-slot name="heading">Ospiti ({{ count($this->ospiti) }})</x-slot>

        <div style="display:flex; justify-content:flex-end; margin-bottom:12px;">
            <button
                wire:click="mountAction('aggiungiOspite')"
                type="button"
                style="display:inline-flex; align-items:center; gap:6px; padding:7px 14px; background:#2563eb; color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer;">
                + Aggiungi ospite
            </button>
        </div>

        @if(count($this->ospiti) > 0)
            <table style="width:100%; border-collapse:collapse; font-size:13px;">
                <thead>
                    <tr style="border-bottom:2px solid #e5e7eb;">
                        <th style="text-align:left; padding:8px 12px; color:#6b7280; font-weight:600;">Nominativo</th>
                        <th style="text-align:left; padding:8px 12px; color:#6b7280; font-weight:600;">Codice Fiscale</th>
                        <th style="text-align:left; padding:8px 12px; color:#6b7280; font-weight:600;">Stato</th>
                        <th style="padding:8px 12px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($this->ospiti as $osp)
                        <tr style="border-bottom:1px solid #f3f4f6;">
                            <td style="padding:10px 12px; font-weight:600; color:#111827;">
                                {{ $osp['cognome'] }} {{ $osp['nome'] }}
                            </td>
                            <td style="padding:10px 12px; font-family:monospace; color:#374151;">
                                {{ $osp['cf'] }}
                            </td>
                            <td style="padding:10px 12px;">
                                @if($osp['ha_accessi'])
                                    <span style="background:#dcfce7; color:#15803d; font-size:.75rem; font-weight:600; padding:2px 8px; border-radius:20px;">Accessi registrati</span>
                                @else
                                    <span style="background:#f3f4f6; color:#9ca3af; font-size:.75rem; font-weight:600; padding:2px 8px; border-radius:20px;">Nessun accesso</span>
                                @endif
                            </td>
                            <td style="padding:10px 12px; text-align:right; white-space:nowrap;">
                                <a href="{{ $osp['checkin_url'] }}" target="_blank"
                                    style="font-size:12px; color:#2563eb; text-decoration:none; margin-right:12px;">
                                    Scheda ↗
                                </a>
                                @if(!$osp['ha_accessi'])
                                    <button
                                        wire:click="eliminaOspite({{ $osp['id'] }})"
                                        wire:confirm="Eliminare l'ospite {{ $osp['cognome'] }} {{ $osp['nome'] }}?"
                                        type="button"
                                        style="font-size:12px; color:#dc2626; background:none; border:none; cursor:pointer; padding:0;">
                                        Elimina
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div style="text-align:center; padding:24px; font-size:13px; color:#9ca3af;">
                Nessun ospite registrato. Aggiungili prima dell'evento o registrali al momento del check-in.
            </div>
        @endif
    </x-filament::section>

</x-filament-panels::page>
