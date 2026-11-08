<x-filament-panels::page>
    @php
        $tutteIscrizioni  = $this->record->iscrizioni;
        $iscrizioni       = $tutteIscrizioni->where('stato', '!=', 'ritirato');
        $iscrizioniRitirate = $tutteIscrizioni->where('stato', 'ritirato');
        $totale           = $tutteIscrizioni->count();
        $inviati          = $tutteIscrizioni->whereNotNull('form_inviato_at')->count();
        $compilati        = $tutteIscrizioni->filter(fn($i) => $i->rispostaPreregistrazione !== null)->count();
        $senzaEmail       = $iscrizioni->filter(fn($i) => !$i->partecipante?->email)->count();
        $nRitirati        = $iscrizioniRitirate->count();
        $corso            = $this->record;
        $evento           = $corso->evento;
        $corsoConcluso    = today()->gt($corso->data_fine);
        $corsoAnnullato   = $corso->isAnnullato();
        $formNonGenerato  = $this->formNonGenerato();
        $dataInvioMassivo   = $iscrizioni->whereNotNull('form_inviato_at')->min('form_inviato_at');
        $sollecitoAbilitato = $this->record->invio_massivo_iscritti_at
            && $this->record->invio_massivo_iscritti_at->diffInDays(now()) >= 3;

        $formPendenti = $iscrizioni->filter(fn ($i) => ! $i->form_inviato_at)->count();
        $invioEffettuato = $formPendenti === 0;
        $invioLabel = $corso->invio_massivo_iscritti_at
            ? 'Ultimo invio: ' . $corso->invio_massivo_iscritti_at->format('d/m/Y H:i')
            : 'Invio massivo non ancora effettuato';

        $sollecitoPendenti = $iscrizioni->filter(fn ($i) => $i->rispostaPreregistrazione === null && ! $i->sollecito_inviato_at)->count();
        $sollecitoEffettuato = $sollecitoPendenti === 0;
        $sollecitoLabel = $corso->invio_massivo_sollecito_iscritti_at
            ? 'Ultimo invio: ' . $corso->invio_massivo_sollecito_iscritti_at->format('d/m/Y H:i')
            : 'Invio massivo non ancora effettuato';

        $infoPendenti  = $iscrizioni->filter(fn ($i) => $i->rispostaPreregistrazione !== null && ! $i->info_evento_inviato_at)->count();
        $infoEffettuata = $infoPendenti === 0;
        $infoLabel = $corso->invio_massivo_info_at
            ? 'Ultimo invio: ' . $corso->invio_massivo_info_at->format('d/m/Y H:i')
            : 'Invio massivo non ancora effettuato';

        $hasComunicazione = $evento->hasComunicazioneValida();
    @endphp

    {{-- Preview import iscritti ─────────────────────────────────────────────── --}}
    @if($this->importPreview !== null)
    @php
        $prevNuovi    = collect($this->importPreview)->where('stato', 'nuovo');
        $prevIscritti = collect($this->importPreview)->where('stato', 'iscritto');
        $prevOk       = collect($this->importPreview)->where('stato', 'ok');
        $prevSkip     = collect($this->importPreview)->where('stato', 'skip');
    @endphp
    <x-filament::section>
        <x-slot name="heading">Anteprima import iscritti</x-slot>
        <x-slot name="description">Verifica i dati prima di confermare l'import.</x-slot>

        <div style="display:flex; flex-direction:column; gap:16px;">

            {{-- Riepilogo numeri --}}
            <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                @if($prevNuovi->count())
                <span style="font-size:13px; background:#dbeafe; color:#1e40af; padding:5px 12px; border-radius:6px; font-weight:600;">
                    + {{ $prevNuovi->count() }} nuovi soci (da creare e iscrivere)
                </span>
                @endif
                @if($prevIscritti->count())
                <span style="font-size:13px; background:#d1fae5; color:#065f46; padding:5px 12px; border-radius:6px; font-weight:600;">
                    + {{ $prevIscritti->count() }} nuovi iscritti al corso
                </span>
                @endif
                @if($prevOk->count())
                <span style="font-size:13px; background:#f0fdf4; color:#166534; padding:5px 12px; border-radius:6px; font-weight:600;">
                    ↻ {{ $prevOk->count() }} già iscritti (aggiornamento dati)
                </span>
                @endif
                @if($this->importSync && count($this->importRimossi) > 0)
                <span style="font-size:13px; background:#fee2e2; color:#991b1b; padding:5px 12px; border-radius:6px; font-weight:600; border:1px solid #fca5a5;">
                    − {{ count($this->importRimossi) }} verranno rimossi (sync)
                </span>
                @endif
                @if($prevSkip->count())
                <span style="font-size:13px; background:#fef2f2; color:#991b1b; padding:5px 12px; border-radius:6px; font-weight:600;">
                    ✗ {{ $prevSkip->count() }} saltati
                </span>
                @endif
                @if($prevNuovi->count() === 0 && $prevIscritti->count() === 0 && $prevOk->count() === 0 && $prevSkip->count() === 0)
                <span style="font-size:13px; color:#6b7280;">Nessuna riga valida nel file.</span>
                @endif
            </div>

            {{-- Griglia completa righe --}}
            @if($this->importPreview)
            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; font-size:12px;">
                    <thead>
                        <tr style="background:#f9fafb; border-bottom:2px solid #e5e7eb;">
                            <th style="padding:7px 10px; text-align:left; color:#374151; font-weight:600; white-space:nowrap;">#</th>
                            <th style="padding:7px 10px; text-align:left; color:#374151; font-weight:600;">Stato</th>
                            <th style="padding:7px 10px; text-align:left; color:#374151; font-weight:600;">Nome</th>
                            <th style="padding:7px 10px; text-align:left; color:#374151; font-weight:600; white-space:nowrap;">Cod. Fiscale</th>
                            <th style="padding:7px 10px; text-align:left; color:#374151; font-weight:600;">Comitato</th>
                            <th style="padding:7px 10px; text-align:left; color:#374151; font-weight:600;">Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($this->importPreview as $r)
                        @php
                            $rowBg = match($r['stato']) {
                                'nuovo'    => '#eff6ff',
                                'iscritto' => '#f0fdf4',
                                'skip'     => '#fef2f2',
                                default    => 'transparent',
                            };
                            $statoBadge = match($r['stato']) {
                                'nuovo'    => ['bg'=>'#dbeafe','color'=>'#1e40af','label'=>'Nuovo socio'],
                                'iscritto' => ['bg'=>'#d1fae5','color'=>'#065f46','label'=>'Nuovo iscritto'],
                                'ok'       => ['bg'=>'#f0fdf4','color'=>'#166534','label'=>'Aggiorna dati'],
                                'skip'     => ['bg'=>'#fee2e2','color'=>'#991b1b','label'=>'Saltato'],
                                default    => ['bg'=>'#f3f4f6','color'=>'#374151','label'=>$r['stato']],
                            };
                        @endphp
                        <tr style="background:{{ $rowBg }}; border-top:1px solid #e5e7eb;">
                            <td style="padding:5px 10px; color:#6b7280; white-space:nowrap;">{{ $r['riga'] }}</td>
                            <td style="padding:5px 10px; white-space:nowrap;">
                                <span style="font-size:11px; background:{{ $statoBadge['bg'] }}; color:{{ $statoBadge['color'] }}; padding:2px 7px; border-radius:4px; font-weight:600;">{{ $statoBadge['label'] }}</span>
                            </td>
                            <td style="padding:5px 10px; font-weight:500;">{{ $r['nome'] }}</td>
                            <td style="padding:5px 10px; font-family:monospace; font-size:11px; white-space:nowrap;">{{ $r['cf'] }}</td>
                            <td style="padding:5px 10px; color:#4b5563;">{{ $r['comitato'] }}</td>
                            <td style="padding:5px 10px; color:#dc2626; font-size:11px;">{{ $r['errore'] }}</td>
                        </tr>
                        @endforeach

                        {{-- Separatore + righe rimossi (solo se sync attivo) --}}
                        @if($this->importSync && count($this->importRimossi) > 0)
                        <tr>
                            <td colspan="6" style="padding:0; border-top:2px solid #fca5a5;">
                                <div style="background:#fee2e2; padding:6px 10px; display:flex; align-items:center; gap:6px;">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="width:13px;height:13px;color:#991b1b;flex-shrink:0;">
                                        <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd"/>
                                    </svg>
                                    <span style="font-size:11px; font-weight:700; color:#991b1b; text-transform:uppercase; letter-spacing:0.04em;">
                                        {{ count($this->importRimossi) }} iscritti verranno rimossi dal corso (sync attivo)
                                    </span>
                                </div>
                            </td>
                        </tr>
                        @foreach($this->importRimossi as $rimosso)
                        <tr style="background:#fef2f2; border-top:1px solid #fee2e2;">
                            <td style="padding:5px 10px; color:#fca5a5; white-space:nowrap; font-size:11px;">—</td>
                            <td style="padding:5px 10px; white-space:nowrap;">
                                <span style="font-size:11px; background:#fee2e2; color:#991b1b; padding:2px 7px; border-radius:4px; font-weight:700; border:1px solid #fca5a5;">Rimosso</span>
                            </td>
                            <td style="padding:5px 10px; font-weight:500; color:#7f1d1d;">{{ $rimosso['nome'] }}</td>
                            <td style="padding:5px 10px; font-family:monospace; font-size:11px; white-space:nowrap; color:#991b1b;">{{ $rimosso['cf'] }}</td>
                            <td style="padding:5px 10px; color:#fca5a5; font-size:11px;" colspan="2">non presente nel file</td>
                        </tr>
                        @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
            @endif

            {{-- Bottoni conferma/annulla --}}
            <div style="display:flex; gap:10px; align-items:center; padding-top:4px;">
                @php $totConfermabili = $prevNuovi->count() + $prevIscritti->count() + $prevOk->count(); @endphp
                @if($totConfermabili)
                <x-filament::button
                    wire:click="confermaImport"
                    wire:loading.attr="disabled"
                    icon="heroicon-o-check"
                    color="success"
                    size="sm"
                    tag="button"
                    type="button"
                >Conferma import ({{ $totConfermabili }} righe)</x-filament::button>
                @endif
                <x-filament::button
                    wire:click="annullaImport"
                    icon="heroicon-o-x-mark"
                    color="gray"
                    size="sm"
                    tag="button"
                    type="button"
                >Annulla</x-filament::button>
            </div>

        </div>
    </x-filament::section>
    @endif

    {{-- Intestazione corso --}}
    <x-filament::section>
        <div style="display:flex; flex-direction:column; gap:4px;">
            <div style="display:flex; align-items:center; gap:8px;">
                <span style="font-size:11px; font-weight:500; text-transform:uppercase; letter-spacing:.06em; color:#9ca3af;">
                    {{ $evento->nome }}
                </span>
                <a href="{{ route('filament.admin.resources.eventi.gestione', $evento) }}"
                   style="display:inline-flex; align-items:center; gap:3px; font-size:10px; font-weight:600; color:#6b7280; background:#f3f4f6; border:1px solid #e5e7eb; border-radius:5px; padding:2px 7px; text-decoration:none; transition:background .15s;"
                   onmouseover="this.style.background='#e5e7eb'" onmouseout="this.style.background='#f3f4f6'"
                >
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                    Gestione evento
                </a>
            </div>
            <div style="display:flex; align-items:center; gap:8px;">
                <h2 style="font-size:18px; font-weight:700; color:#111827; margin:0;">{{ $corso->nome }}</h2>
                @if($corso->link_gaia)
                <a href="{{ $corso->link_gaia }}" target="_blank"
                   style="display:inline-flex; align-items:center; gap:3px; font-size:10px; font-weight:600; color:#0369a1; background:#e0f2fe; border:1px solid #bae6fd; border-radius:5px; padding:2px 7px; text-decoration:none; white-space:nowrap; transition:background .15s;"
                   onmouseover="this.style.background='#bae6fd'" onmouseout="this.style.background='#e0f2fe'"
                >
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                    Link a GAIA
                </a>
                @endif
            </div>
            @if($corso->numero_protocollo)
                <span style="font-family:monospace; font-size:11px; color:#9ca3af;">{{ $corso->numero_protocollo }}</span>
            @endif
            <span style="font-size:13px; color:#6b7280;">
                {{ $corso->data_inizio->format('d/m/Y') }}
                @if(!$corso->data_inizio->eq($corso->data_fine)) — {{ $corso->data_fine->format('d/m/Y') }} @endif
                @if($corso->sede ?? $evento->sede) · {{ $corso->sede ?? $evento->sede }} @endif
            </span>
            @if($dataInvioMassivo)
                <span style="display:inline-flex; align-items:center; gap:5px; margin-top:4px; font-size:11px; font-weight:500; color:#16a34a;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    Invio massivo form effettuato il {{ \Carbon\Carbon::parse($dataInvioMassivo)->format('d/m/Y \a\l\l\e H:i') }} — i nuovi iscritti ricevono il form con l'invio singolo
                </span>
            @else
                <span style="margin-top:4px; font-size:11px; color:#9ca3af;">Invio massivo form non ancora effettuato</span>
            @endif
        </div>
    </x-filament::section>

    {{-- Statistiche --}}
    <div style="display:grid; grid-template-columns:repeat(5,1fr); gap:16px;">
        @foreach([
            [$totale,     'Iscritti totali',    '#111827',                                ''],
            [$inviati,    'Form inviati',        '#2563eb',                                ''],
            [$compilati,  'Form compilati',      '#16a34a',                                ''],
            [$senzaEmail, 'Senza email',         $senzaEmail > 0 ? '#d97706' : '#9ca3af', ''],
            [$nRitirati,  'Ritirati',            $nRitirati > 0  ? '#b91c1c' : '#9ca3af', ''],
        ] as [$val, $label, $color, $_])
            <x-filament::section>
                <div style="text-align:center; padding:4px 0;">
                    <div style="font-size:26px; font-weight:700; color:{{ $color }};">{{ $val }}</div>
                    <div style="font-size:11px; color:#6b7280; margin-top:2px;">{{ $label }}</div>
                </div>
            </x-filament::section>
        @endforeach
    </div>

    {{-- Comunicazioni agli iscritti --}}
    <x-filament::section>
        <x-slot name="heading">Comunicazioni agli iscritti</x-slot>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:16px;">
            {{-- Form di preregistrazione --}}
            <div class="corso-comm-card">
                <div>
                    <div class="corso-comm-card-title">Form di preregistrazione</div>
                    <div class="corso-comm-card-status {{ $invioEffettuato ? 'is-done' : '' }}">{{ $invioLabel }}</div>
                </div>
                <div class="corso-comm-card-actions">
                    <x-filament::button
                        wire:click="mountAction('anteprimaEmail')"
                        icon="heroicon-o-envelope"
                        color="info"
                        size="sm"
                        tag="button"
                        type="button"
                    >Anteprima</x-filament::button>

                    <x-filament::button
                        wire:click="mountAction('inviaTutti')"
                        icon="{{ $invioEffettuato ? 'heroicon-o-check-circle' : 'heroicon-o-paper-airplane' }}"
                        color="{{ $invioEffettuato ? 'gray' : 'brand_pink' }}"
                        size="sm"
                        tag="button"
                        type="button"
                        :disabled="$formNonGenerato || $corsoConcluso || $corsoAnnullato || $invioEffettuato"
                        :tooltip="$formNonGenerato
                            ? 'Nessun form generato per questo corso: modifica il corso per configurarlo prima di inviarlo'
                            : ($corsoAnnullato ? 'Corso annullato: invio non disponibile' : ($corsoConcluso ? 'Corso concluso: invio non più disponibile' : ($invioEffettuato ? 'Tutti gli iscritti hanno già ricevuto il form' : null)))"
                    >{{ $invioEffettuato ? 'Nessuno da inviare' : "Invia a tutti ({$formPendenti})" }}</x-filament::button>
                </div>
            </div>

            {{-- Sollecito --}}
            @if($sollecitoAbilitato)
                <div class="corso-comm-card">
                    <div>
                        <div class="corso-comm-card-title">Sollecito preregistrazione</div>
                        <div class="corso-comm-card-status {{ $sollecitoEffettuato ? 'is-done' : '' }}">{{ $sollecitoLabel }}</div>
                    </div>
                    <div class="corso-comm-card-actions">
                        <x-filament::button
                            wire:click="mountAction('anteprimaSollecito')"
                            icon="heroicon-o-bell-alert"
                            color="info"
                            size="sm"
                            tag="button"
                            type="button"
                        >Anteprima</x-filament::button>

                        <x-filament::button
                            wire:click="mountAction('inviaSollecitoTutti')"
                            icon="{{ $sollecitoEffettuato ? 'heroicon-o-check-circle' : 'heroicon-o-bell-alert' }}"
                            color="{{ $sollecitoEffettuato ? 'gray' : 'brand_pink' }}"
                            size="sm"
                            tag="button"
                            type="button"
                            :disabled="$formNonGenerato || $corsoConcluso || $corsoAnnullato || $sollecitoEffettuato"
                            :tooltip="$formNonGenerato
                                ? 'Nessun form generato per questo corso: modifica il corso per configurarlo prima di inviarlo'
                                : ($corsoAnnullato ? 'Corso annullato: invio non disponibile' : ($corsoConcluso ? 'Corso concluso: invio non più disponibile' : ($sollecitoEffettuato ? 'Nessun iscritto da sollecitare al momento' : null)))"
                        >{{ $sollecitoEffettuato ? 'Nessuno da inviare' : "Invia a tutti ({$sollecitoPendenti})" }}</x-filament::button>
                    </div>
                </div>
            @endif

            {{-- Comunicazione evento — card sempre visibile: "Invia a tutti" va disabilitato
                 con un motivo quando manca il contenuto, non far sparire l'intera card. --}}
            <div class="corso-comm-card">
                <div>
                    <div class="corso-comm-card-title">Comunicazione evento</div>
                    <div class="corso-comm-card-status {{ $infoEffettuata ? 'is-done' : '' }}">{{ $infoLabel }}</div>
                </div>
                <div class="corso-comm-card-actions">
                    @if($hasComunicazione)
                        <x-filament::button
                            wire:click="mountAction('anteprimaInfoEvento')"
                            icon="heroicon-o-eye"
                            color="info"
                            size="sm"
                            tag="button"
                            type="button"
                        >Anteprima</x-filament::button>
                    @endif

                    <x-filament::button
                        wire:click="mountAction('inviaInfoTutti')"
                        icon="{{ $infoEffettuata ? 'heroicon-o-check-circle' : 'heroicon-o-information-circle' }}"
                        color="{{ $infoEffettuata ? 'gray' : 'brand_pink' }}"
                        size="sm"
                        tag="button"
                        type="button"
                        :disabled="! $hasComunicazione || $corsoConcluso || $corsoAnnullato || $infoEffettuata"
                        :tooltip="! $hasComunicazione
                            ? 'Nessuna informazione definita da inviare: entra nella modifica evento per inserire testo o allegati'
                            : ($corsoAnnullato ? 'Corso annullato: invio non disponibile' : ($corsoConcluso ? 'Corso concluso: invio non più disponibile' : ($infoEffettuata ? 'Tutti gli iscritti hanno già ricevuto la comunicazione' : null)))"
                    >{{ $infoEffettuata ? 'Nessuno da inviare' : "Invia a tutti ({$infoPendenti})" }}</x-filament::button>
                </div>
            </div>
        </div>
    </x-filament::section>

    {{-- Riepilogo pasti (solo se ci sono sezioni attive nel form) --}}
    @php
        $sezioniAttive = collect($corso->form_sections ?? [])->where('attivo', true);
        $haSezioni = $sezioniAttive->isNotEmpty();

        if ($haSezioni) {
            // Conta da presenze_evento (fonte autoritativa, include sia form che admin),
            // ristretto ai soli iscritti attivi di QUESTO corso (partecipante_id unici) —
            // presenze_evento è a livello di evento, condivisa con eventuali altri corsi
            // dello stesso evento e con lo staff, altrimenti il conteggio li includerebbe.
            $partecipantiCorsoIds = $corso->iscrizioni->where('stato', '!=', 'ritirato')->pluck('partecipante_id');
            $presenzeCount = \App\Models\Presenza::where('evento_id', $corso->evento_id)
                ->whereIn('partecipante_id', $partecipantiCorsoIds)
                ->get()
                ->groupBy(fn($p) => $p->tipo . '_' . $p->data->format('Y-m-d'))
                ->map(fn($g) => $g->pluck('partecipante_id')->unique()->count());

            $conteggioSezioni = $sezioniAttive
                ->filter(fn($s) => $s['tipo'] !== 'pernotto')
                ->map(function ($sezione) use ($presenzeCount) {
                    $chiave = $sezione['tipo'] . '_' . $sezione['data'];
                    return [
                        'label' => $sezione['label'],
                        'tipo'  => $sezione['tipo'],
                        'count' => $presenzeCount->get($chiave, 0),
                    ];
                })->values();

            // Pernotti per notte
            $pernotti = $sezioniAttive
                ->where('tipo', 'pernotto')
                ->map(function ($sezione) use ($presenzeCount) {
                    $chiave = 'pernotto_' . $sezione['data'];
                    return [
                        'label' => $sezione['label'],
                        'tipo'  => 'pernotto',
                        'count' => $presenzeCount->get($chiave, 0),
                    ];
                })->values();

            // Colazioni = pernotti della notte precedente (stesso count, label adattata)
            $colazioni = $sezioniAttive
                ->where('tipo', 'pernotto')
                ->map(function ($sezione) use ($presenzeCount) {
                    $chiave     = 'pernotto_' . $sezione['data'];
                    $dataGiorno = \Carbon\Carbon::parse($sezione['data'])->addDay()->translatedFormat('l j F');
                    return [
                        'label' => "Colazione — {$dataGiorno}",
                        'tipo'  => 'colazione',
                        'count' => $presenzeCount->get($chiave, 0),
                    ];
                })->values();

            $tutteLeRighe = $conteggioSezioni->merge($pernotti)->merge($colazioni);
        }
    @endphp

    @if($haSezioni)
        <x-filament::section>
            <x-slot name="heading">Riepilogo pasti e presenze</x-slot>
            <div style="display:flex; flex-direction:column; gap:0;">
                @foreach($tutteLeRighe as $riga)
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid #f3f4f6;">
                        <span style="font-size:13px; color:#374151;">
                            @if($riga['tipo'] === 'colazione')
                                <span style="color:#9ca3af; font-size:11px; margin-right:4px;">↳</span>
                            @endif
                            {{ $riga['label'] }}
                            @if($riga['tipo'] === 'colazione')
                                <span style="font-size:11px; color:#9ca3af;">(da pernotto)</span>
                            @endif
                        </span>
                        <x-filament::badge color="{{ $riga['count'] > 0 ? 'primary' : 'gray' }}">
                            {{ $riga['count'] }}
                        </x-filament::badge>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    {{-- Tabella iscritti --}}
    <x-filament::section>
        <x-slot name="heading">
            Iscritti al corso ({{ $tutteIscrizioni->count() }})
            @if($senzaEmail > 0)
                <x-filament::badge color="warning" style="margin-left:8px;">{{ $senzaEmail }} senza email</x-filament::badge>
            @endif
        </x-slot>

        @php
            $filtriSelects = [
                ['key' => 'form', 'placeholder' => 'Form: tutti', 'options' => ['compilato' => 'Form compilato', 'mancante' => 'Form non compilato']],
            ];
            if ($corso->pagamento_previsto) {
                $filtriSelects[] = ['key' => 'pagamento', 'placeholder' => 'Pagamento: tutti', 'options' => ['ok' => 'Ricevuta allegata', 'mancante' => 'Manca ricevuta']];
            }
            $filtriSelects[] = ['key' => 'nullaosta', 'placeholder' => 'Nullaosta: tutti', 'options' => ['mancante' => 'Nullaosta mancante', 'da-verificare' => 'Nullaosta da verificare', 'ok' => 'Nullaosta verificato', 'non-richiesto' => 'Nullaosta non richiesto']];
            if ($corso->richiede_documento_aggiuntivo) {
                $filtriSelects[] = ['key' => 'docagg', 'placeholder' => 'Documento aggiuntivo: tutti', 'options' => ['mancante' => 'Documento mancante', 'da-verificare' => 'Documento da verificare', 'ok' => 'Documento verificato']];
            }
            if ($this->eventoIniziato) {
                $filtriSelects[] = ['key' => 'checkin', 'placeholder' => 'Check-in: tutti', 'options' => ['si' => 'Arrivati', 'no' => 'Non arrivati']];
            }
        @endphp

        <x-griglia-filtri :totale="$iscrizioni->count()" vuoto="Nessun iscritto corrisponde ai filtri." :selects="$filtriSelects">
            @forelse($iscrizioni->sortBy('partecipante.cognome') as $iscrizione)
                @php
                    $p                 = $iscrizione->partecipante;
                    $hasEmail          = (bool) $p?->email;
                    $telefono          = trim(($p?->prefisso_telefono ?? '') . ' ' . ($p?->telefono ?? ''));
                    $inviato           = $iscrizione->form_inviato_at;
                    $compilato         = $iscrizione->rispostaPreregistrazione !== null;
                    $formUrl           = route('form.show', $iscrizione->token);
                    $ricevutaPath      = $iscrizione->rispostaPreregistrazione?->ricevuta_path;
                    $nullaostaPath     = $iscrizione->rispostaPreregistrazione?->nullaosta_path;
                    $nullaostaVerif    = $iscrizione->rispostaPreregistrazione?->nullaosta_verificato ?? false;
                    $nullaostaVerifAt  = $iscrizione->rispostaPreregistrazione?->nullaosta_verificato_at;
                    $nullaostaVerifDa  = $iscrizione->rispostaPreregistrazione?->nullaostaVerificatoDa?->name;
                    $docAggPath        = $iscrizione->rispostaPreregistrazione?->documento_aggiuntivo_path;
                    $docAggVerif       = $iscrizione->rispostaPreregistrazione?->documento_aggiuntivo_verificato ?? false;
                    $docAggVerifAt     = $iscrizione->rispostaPreregistrazione?->documento_aggiuntivo_verificato_at;
                    $docAggVerifDa     = $iscrizione->rispostaPreregistrazione?->documentoAggiuntivoVerificatoDa?->name;
                    $participanteRegId = $p?->comitato_regionale_id;
                    $richiedeNullaosta = $participanteRegId === null || $participanteRegId !== $evento->comitato_regionale_id;
                    $conflittiIscritto = $conflittiPerIscrizione[$iscrizione->id] ?? [];

                    $rowSearch = mb_strtolower(trim(implode(' ', array_filter([
                        $p?->cognome, $p?->nome, $p?->codice_fiscale, $p?->email, $p?->comitatoLocale?->nome,
                    ]))));
                    $rowPagamento = ! $corso->pagamento_previsto ? 'non-previsto' : ($ricevutaPath ? 'ok' : 'mancante');
                    $rowNullaosta = match(true) {
                        ! $richiedeNullaosta        => 'non-richiesto',
                        $nullaostaVerif             => 'ok',
                        (bool) $nullaostaPath       => 'da-verificare',
                        default                     => 'mancante',
                    };
                    $rowDocAgg = match(true) {
                        ! $corso->richiede_documento_aggiuntivo => 'non-richiesto',
                        $docAggVerif                            => 'ok',
                        (bool) $docAggPath                      => 'da-verificare',
                        default                                 => 'mancante',
                    };
                    $rowCheckin = ! $this->eventoIniziato ? '' : (($p && isset($this->checkinIds[$p->id])) ? 'si' : 'no');
                @endphp

                <div data-row
                     data-search="{{ $rowSearch }}"
                     data-form="{{ $compilato ? 'compilato' : 'mancante' }}"
                     data-pagamento="{{ $rowPagamento }}"
                     data-nullaosta="{{ $rowNullaosta }}"
                     data-docagg="{{ $rowDocAgg }}"
                     data-checkin="{{ $rowCheckin }}"
                     style="display:flex; align-items:center; gap:16px; padding:12px 0; border-bottom:1px solid #f3f4f6;">

                    {{-- Presenze (estrema sinistra) --}}
                    <div style="flex-shrink:0;">
                        @if($compilato || $inviato)
                            <x-filament::button
                                color="info" size="sm" icon="heroicon-o-calendar-days"
                                wire:click="mountAction('gestisciPresenze', {iscrizione_id: {{ $iscrizione->id }}})"
                                tag="button" type="button"
                            >Presenze</x-filament::button>
                        @else
                            <x-filament::button
                                color="gray" size="sm" icon="heroicon-o-calendar-days"
                                disabled tag="button" type="button"
                            >Presenze</x-filament::button>
                        @endif
                    </div>

                    {{-- Nome e info --}}
                    <div style="flex:1; min-width:0;">
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                            <span style="font-size:13px; font-weight:600; color:#111827;">{{ $p?->cognome }} {{ $p?->nome }}</span>
                            @if($this->eventoIniziato && $p)
                                @if(isset($this->checkinIds[$p->id]))
                                    <span style="font-size:11px;font-weight:600;padding:2px 8px;border-radius:20px;background:#dcfce7;color:#15803d;border:1px solid #86efac;white-space:nowrap;">✓ Check-in</span>
                                @else
                                    <span style="font-size:11px;font-weight:600;padding:2px 8px;border-radius:20px;background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5;white-space:nowrap;">✗ Non arrivato</span>
                                @endif
                            @endif
                            @if(!empty($conflittiIscritto))
                                <span title="{{ collect($conflittiIscritto)->map(fn($c) => $c['corso'] . ' (' . $c['date'] . ')')->join(', ') }}"
                                      style="display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:600; color:#92400e; background:#fef3c7; border:1px solid #fde68a; border-radius:4px; padding:2px 7px; cursor:help;">
                                    ⚠ Sovrapposizione ({{ count($conflittiIscritto) }})
                                </span>
                            @endif
                        </div>
                        <div style="font-size:11px; color:#9ca3af; margin-top:2px;">
                            @if($p?->codice_fiscale)
                                <span onclick="copiaLink('{{ $p->codice_fiscale }}', this)" title="Clicca per copiare" style="cursor:pointer; font-family:monospace; color:#6b7280;"><span class="copy-label">{{ $p->codice_fiscale }}</span></span>
                            @endif
                            @if($p?->email)
                                · <span onclick="copiaLink('{{ $p->email }}', this)" title="Clicca per copiare" style="cursor:pointer;">✉️ <span class="copy-label">{{ $p->email }}</span></span>
                            @else
                                · — nessuna email —
                            @endif
                            @if($telefono)
                                · <span onclick="copiaLink('{{ $telefono }}', this)" title="Clicca per copiare" style="cursor:pointer; color:#6b7280;">📞 <span class="copy-label">{{ $telefono }}</span></span>
                            @endif
                        </div>
                        @if($p?->comitatoLocale)
                        <div style="font-size:11px; color:#9ca3af;">{{ $p->comitatoLocale->nome }}</div>
                        @endif
                    </div>

                    {{-- Azioni --}}
                    @php
                        $sollecito    = $iscrizione->sollecito_inviato_at;
                        $infoInviato  = $iscrizione->info_evento_inviato_at;
                        $canSendForm  = $hasEmail && !$compilato;
                        $canSendSollecito = $canSendForm && $sollecitoAbilitato && $inviato;
                        $compilatoAt  = $iscrizione->rispostaPreregistrazione?->created_at;
                        $nomeIscrittoPerModale = trim(($p?->cognome ?? '') . ' ' . ($p?->nome ?? ''));
                    @endphp

                    <div style="display:flex; flex-direction:column; align-items:flex-end; gap:6px; flex-shrink:0;">
                        <div style="display:flex; align-items:flex-start; gap:8px;">

                            {{-- Prima colonna: Pagamento (se previsto) o Compilato badge (se non previsto e compilato) --}}
                            @if($corso->pagamento_previsto || $compilato)
                                <div style="display:flex; flex-direction:column; gap:4px;">

                                    {{-- Riga Pagamento / Compilato: nessun pulsante d'azione in questa
                                         pagina, ma lo spaziatore a sinistra (stessa larghezza della colonna
                                         pulsanti del nullaosta) tiene la pillola allineata con quella sotto. --}}
                                    <div style="display:flex; align-items:flex-start; gap:6px;">
                                        <div style="width:64px; flex-shrink:0;"></div>
                                        <div style="display:flex; flex-direction:column; align-items:flex-start; gap:2px;">
                                            @if($corso->pagamento_previsto)
                                                @if($ricevutaPath)
                                                    <button type="button"
                                                            wire:click="mountAction('visualizzaRicevuta', { iscrizione_id: {{ $iscrizione->id }}, nome: '{{ addslashes($nomeIscrittoPerModale) }}' })"
                                                            style="display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:500; color:#7c3aed; background:#f5f3ff; border:1px solid #ddd6fe; border-radius:6px; padding:5px 10px; cursor:pointer; white-space:nowrap;"
                                                            title="Clicca per visualizzare il pagamento">
                                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                                                        Pagamento
                                                    </button>
                                                @else
                                                    <span style="display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:500; color:#b45309; background:#fef3c7; border:1px solid #fde68a; border-radius:6px; padding:5px 8px; white-space:nowrap;" title="Nessuna ricevuta allegata">
                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                                        Manca ricevuta
                                                    </span>
                                                @endif
                                                @if($compilatoAt)
                                                    <span style="font-size:10px;color:#9ca3af;white-space:nowrap;">Compilato {{ $compilatoAt->format('d/m H:i') }}</span>
                                                @elseif($inviato)
                                                    <span style="font-size:10px;color:#9ca3af;white-space:nowrap;">Inviato {{ $iscrizione->form_inviato_at->format('d/m H:i') }}</span>
                                                @endif
                                            @else
                                                <span style="display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:600; color:#16a34a; background:transparent; border:1px solid #16a34a; border-radius:6px; padding:5px 10px; white-space:nowrap;">
                                                    ✓ Compilato
                                                </span>
                                                @if($compilatoAt)
                                                    <span style="font-size:10px;color:#9ca3af;white-space:nowrap;">{{ $compilatoAt->format('d/m H:i') }}</span>
                                                @endif
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Riga Nullaosta regionale: pulsanti in un contenitore a larghezza
                                         fissa allineato a destra contro la pillola (verifica / annulla /
                                         sostituisci finché non verificato). Verificato → sola lettura. --}}
                                    @if($richiedeNullaosta)
                                        <div style="display:flex; align-items:center; gap:6px;">
                                            <div style="width:64px; display:flex; justify-content:flex-end; align-items:center; gap:2px; flex-shrink:0;">
                                                @if($nullaostaPath && $nullaostaVerif)
                                                    <x-filament::icon-button
                                                        color="warning" size="sm" icon="heroicon-o-x-mark"
                                                        wire:click="mountAction('annullaVerificaNullaosta', { iscrizione_id: {{ $iscrizione->id }} })"
                                                        tag="button" type="button" label="Annulla verifica nullaosta" tooltip="Annulla verifica"
                                                    />
                                                @elseif($nullaostaPath)
                                                    <x-filament::icon-button
                                                        color="success" size="sm" icon="heroicon-o-check"
                                                        wire:click="mountAction('verificaNullaosta', { iscrizione_id: {{ $iscrizione->id }} })"
                                                        tag="button" type="button" label="Verifica nullaosta" tooltip="Segna come verificato"
                                                    />
                                                    <x-filament::icon-button
                                                        color="gray" size="sm" icon="heroicon-o-arrow-path"
                                                        wire:click="mountAction('sostituisciNullaosta', { iscrizione_id: {{ $iscrizione->id }}, nome: '{{ addslashes($nomeIscrittoPerModale) }}' })"
                                                        tag="button" type="button" label="Sostituisci nullaosta" tooltip="Sostituisci il file"
                                                    />
                                                @endif
                                            </div>
                                            @if($nullaostaPath && $nullaostaVerif)
                                                <button type="button"
                                                        wire:click="mountAction('visualizzaNullaosta', { iscrizione_id: {{ $iscrizione->id }}, nome: '{{ addslashes($nomeIscrittoPerModale) }}' })"
                                                        style="display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:500; color:#15803d; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:5px 10px; cursor:pointer; white-space:nowrap;"
                                                        title="Verificato il {{ $nullaostaVerifAt?->format('d/m/Y H:i') }}{{ $nullaostaVerifDa ? ' da ' . $nullaostaVerifDa : '' }} — clicca per visualizzare">
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                    Nullaosta
                                                </button>
                                            @elseif($nullaostaPath)
                                                <button type="button"
                                                        wire:click="mountAction('visualizzaNullaosta', { iscrizione_id: {{ $iscrizione->id }}, nome: '{{ addslashes($nomeIscrittoPerModale) }}' })"
                                                        style="display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:500; color:#b45309; background:#fffbeb; border:1px solid #fde68a; border-radius:6px; padding:5px 10px; cursor:pointer; white-space:nowrap;"
                                                        title="Da verificare — clicca per visualizzare il nullaosta">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/></svg>
                                                    Nullaosta
                                                </button>
                                            @else
                                                <span style="display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:500; color:#b45309; background:#fef3c7; border:1px solid #fde68a; border-radius:6px; padding:5px 8px; white-space:nowrap;" title="Nullaosta non ancora caricato">
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                                    Manca nullaosta
                                                </span>
                                            @endif
                                        </div>
                                    @endif

                                    {{-- Riga Documento aggiuntivo (dicitura libera dell'admin nel tooltip e nel
                                         modale di anteprima): stessa logica del nullaosta. Solo per chi ha già
                                         compilato — l'upload tardivo parte dal link di conferma dell'iscritto. --}}
                                    @if($corso->richiede_documento_aggiuntivo && $compilato)
                                        <div style="display:flex; align-items:center; gap:6px;">
                                            <div style="width:64px; display:flex; justify-content:flex-end; align-items:center; gap:2px; flex-shrink:0;">
                                                @if($docAggPath && $docAggVerif)
                                                    <x-filament::icon-button
                                                        color="warning" size="sm" icon="heroicon-o-x-mark"
                                                        wire:click="mountAction('annullaVerificaDocumentoAggiuntivo', { iscrizione_id: {{ $iscrizione->id }} })"
                                                        tag="button" type="button" label="Annulla verifica documento aggiuntivo" tooltip="Annulla verifica"
                                                    />
                                                @else
                                                    @if($docAggPath)
                                                        <x-filament::icon-button
                                                            color="success" size="sm" icon="heroicon-o-check"
                                                            wire:click="mountAction('verificaDocumentoAggiuntivo', { iscrizione_id: {{ $iscrizione->id }} })"
                                                            tag="button" type="button" label="Verifica documento aggiuntivo" tooltip="Segna come verificato"
                                                        />
                                                    @endif
                                                    <x-filament::icon-button
                                                        color="gray" size="sm" :icon="$docAggPath ? 'heroicon-o-arrow-path' : 'heroicon-o-arrow-up-tray'"
                                                        wire:click="mountAction('sostituisciDocumentoAggiuntivo', { iscrizione_id: {{ $iscrizione->id }}, presente: {{ $docAggPath ? 'true' : 'false' }}, nome: '{{ addslashes($nomeIscrittoPerModale) }}' })"
                                                        tag="button" type="button" :label="$docAggPath ? 'Sostituisci documento aggiuntivo' : 'Carica documento aggiuntivo'" :tooltip="$docAggPath ? 'Sostituisci il file' : 'Carica il documento al posto dell\'iscritto'"
                                                    />
                                                @endif
                                            </div>
                                            @if($docAggPath && $docAggVerif)
                                                <button type="button"
                                                        wire:click="mountAction('visualizzaDocumentoAggiuntivo', { iscrizione_id: {{ $iscrizione->id }}, nome: '{{ addslashes($nomeIscrittoPerModale) }}' })"
                                                        style="display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:500; color:#15803d; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:5px 10px; cursor:pointer; white-space:nowrap;"
                                                        title="Verificato il {{ $docAggVerifAt?->format('d/m/Y H:i') }}{{ $docAggVerifDa ? ' da ' . $docAggVerifDa : '' }} — clicca per visualizzare">
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                    Documento aggiuntivo
                                                </button>
                                            @elseif($docAggPath)
                                                <button type="button"
                                                        wire:click="mountAction('visualizzaDocumentoAggiuntivo', { iscrizione_id: {{ $iscrizione->id }}, nome: '{{ addslashes($nomeIscrittoPerModale) }}' })"
                                                        style="display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:500; color:#b45309; background:#fffbeb; border:1px solid #fde68a; border-radius:6px; padding:5px 10px; cursor:pointer; white-space:nowrap;"
                                                        title="Da verificare — clicca per visualizzare il documento">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/></svg>
                                                    Documento aggiuntivo
                                                </button>
                                            @else
                                                <span style="display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:500; color:#b45309; background:#fef3c7; border:1px solid #fde68a; border-radius:6px; padding:5px 8px; white-space:nowrap;" title="Documento aggiuntivo non ancora caricato">
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                                    Manca documento aggiuntivo
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @endif

                            {{-- Copia link --}}
                            <x-filament::button
                                color="gray" size="sm" icon="heroicon-o-clipboard-document"
                                onclick="copiaLink('{{ $formUrl }}', this)"
                                tag="button" type="button"
                            ><span class="copy-label">Copia link</span></x-filament::button>

                            {{-- Form --}}
                            @if($canSendForm)
                                <div style="display:flex; flex-direction:column; align-items:center; gap:2px;">
                                    <x-filament::button
                                        color="brand_pink" size="sm"
                                        icon="heroicon-o-paper-airplane"
                                        wire:click="mountAction('inviaFormSingolo', { iscrizione_id: {{ $iscrizione->id }} })"
                                        tag="button" type="button"
                                        :disabled="(bool) $inviato"
                                        :tooltip="$inviato ? 'Form già inviato — usa il sollecito per un nuovo promemoria' : null"
                                    >Invia form</x-filament::button>
                                    @if($inviato)
                                        <span style="font-size:10px;color:#9ca3af;">{{ $iscrizione->form_inviato_at->format('d/m H:i') }}</span>
                                    @endif
                                </div>
                            @endif

                            {{-- Sollecito --}}
                            @if($canSendSollecito)
                                <div style="display:flex; flex-direction:column; align-items:center; gap:2px;">
                                    <x-filament::button
                                        color="brand_pink" size="sm"
                                        icon="heroicon-o-bell-alert"
                                        wire:click="mountAction('inviaSollecitoSingolo', { iscrizione_id: {{ $iscrizione->id }} })"
                                        tag="button" type="button"
                                    >Invia sollecito</x-filament::button>
                                    @if($sollecito)
                                        <span style="font-size:10px;color:#9ca3af;">{{ $sollecito->format('d/m H:i') }}</span>
                                    @endif
                                </div>
                            @endif

                            {{-- Info evento --}}
                            @if($hasComunicazione)
                                <div style="display:flex; flex-direction:column; align-items:center; gap:2px;">
                                    <x-filament::button
                                        color="brand_pink" size="sm" icon="heroicon-o-information-circle"
                                        wire:click="mountAction('inviaInfoSingolo', { iscrizione_id: {{ $iscrizione->id }} })"
                                        tag="button" type="button"
                                    >{{ $infoInviato ? 'Reinvia info' : 'Invia info' }}</x-filament::button>
                                    @if($infoInviato)
                                        <span style="font-size:10px;color:#9ca3af;">{{ $infoInviato->format('d/m H:i') }}</span>
                                    @endif
                                </div>
                            @endif

                            {{-- Azioni pericolose raggruppate in un menu a tendina --}}
                            <x-filament::dropdown placement="bottom-end">
                                <x-slot name="trigger">
                                    <x-filament::icon-button
                                        color="danger" size="sm" icon="heroicon-o-ellipsis-vertical"
                                        tag="button" type="button" label="Altre azioni"
                                    />
                                </x-slot>
                                <x-filament::dropdown.list>
                                    @if($compilato && (auth()->user()?->isSuperadmin() || auth()->user()?->isAdmin()))
                                        <x-filament::dropdown.list.item
                                            icon="heroicon-o-arrow-path" color="danger" tag="button" type="button"
                                            :disabled="(bool) $ricevutaPath"
                                            :tooltip="$ricevutaPath ? 'Reset non consentito: è presente una ricevuta di pagamento' : null"
                                            wire:click="mountAction('resetFormIscritto', { iscrizione_id: {{ $iscrizione->id }} })"
                                        >Reset form</x-filament::dropdown.list.item>
                                    @endif
                                    <x-filament::dropdown.list.item
                                        icon="heroicon-o-trash" color="danger" tag="button" type="button"
                                        wire:click="mountAction('rimuoviIscritto', { iscrizione_id: {{ $iscrizione->id }} })"
                                    >{{ $compilato ? 'Ritira iscritto' : 'Rimuovi iscritto' }}</x-filament::dropdown.list.item>
                                </x-filament::dropdown.list>
                            </x-filament::dropdown>

                        </div>

                    </div>
                </div>
            @empty
                <div style="text-align:center; padding:32px; font-size:13px; color:#9ca3af;">
                    Nessun iscritto trovato.
                </div>
            @endforelse
        </x-griglia-filtri>
    </x-filament::section>

    {{-- Sezione Ritirati --}}
    @if($iscrizioniRitirate->isNotEmpty())
        <details>
            <summary style="cursor:pointer; list-style:none; display:flex; align-items:center; gap:8px; padding:12px 16px; background:#fef2f2; border:1px solid #fecaca; border-radius:10px; font-size:13px; font-weight:600; color:#991b1b; user-select:none;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                Ritirati dopo invio form ({{ $iscrizioniRitirate->count() }})
                <span style="font-size:11px; font-weight:400; color:#b91c1c; margin-left:4px;">— espandi per vedere i dettagli</span>
            </summary>

            <div style="margin-top:8px; border:1px solid #fecaca; border-radius:10px; overflow:hidden;">
                @foreach($iscrizioniRitirate->sortBy('partecipante.cognome') as $iscrizione)
                    @php
                        $p        = $iscrizione->partecipante;
                        $risposta = $iscrizione->rispostaPreregistrazione;
                        $ricevutaPath = $risposta?->ricevuta_path;
                        $verificato   = $risposta?->pagamento_verificato ?? false;
                        $dataPagamento = $risposta?->data_pagamento;
                    @endphp
                    <div style="display:flex; align-items:center; gap:16px; padding:10px 16px; border-bottom:1px solid #fee2e2; background:#fff;">
                        <div style="flex:1; min-width:0;">
                            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                                <span style="font-size:13px; font-weight:600; color:#6b7280; text-decoration:line-through;">{{ $p?->cognome }} {{ $p?->nome }}</span>
                                <x-filament::badge color="danger">Ritirato</x-filament::badge>
                                @if($this->eventoIniziato && $p)
                                    @if(isset($this->checkinIds[$p->id]))
                                        <span style="font-size:11px;font-weight:600;padding:2px 8px;border-radius:20px;background:#dcfce7;color:#15803d;border:1px solid #86efac;white-space:nowrap;">✓ Check-in</span>
                                    @else
                                        <span style="font-size:11px;font-weight:600;padding:2px 8px;border-radius:20px;background:#fee2e2;color:#b91c1c;border:1px solid #fca5a5;white-space:nowrap;">✗ Non arrivato</span>
                                    @endif
                                @endif
                            </div>
                            <div style="font-size:11px; color:#9ca3af; margin-top:2px;">
                                <span style="font-family:monospace; color:#6b7280;">{{ $p?->codice_fiscale }}</span>
                                · {{ $p?->email ?? '— nessuna email —' }}
                            </div>
                            @if($p?->comitatoLocale)
                            <div style="font-size:11px; color:#9ca3af;">{{ $p->comitatoLocale->nome }}</div>
                            @endif
                        </div>

                        @if($corso->pagamento_previsto)
                            <div style="font-size:11px; color:#6b7280; flex-shrink:0;">
                                @if($verificato)
                                    <span style="color:#15803d; font-weight:600;">✓ Pagamento verificato</span>
                                    @if($dataPagamento) · {{ $dataPagamento->format('d/m/Y') }} @endif
                                @elseif($ricevutaPath)
                                    <span style="color:#7c3aed;">Ricevuta allegata</span>
                                    @if($dataPagamento) · {{ $dataPagamento->format('d/m/Y') }} @endif
                                @else
                                    <span style="color:#b45309;">Nessuna ricevuta</span>
                                @endif
                            </div>
                        @endif

                        {{-- Riattiva (ripristina a pending) --}}
                        @if(auth()->user()?->isSuperadmin() || auth()->user()?->isAdmin() || auth()->user()?->isAdminLocale())
                            <x-filament::button
                                color="success"
                                size="sm"
                                icon="heroicon-o-arrow-uturn-left"
                                wire:click="mountAction('ripristinaIscritto', { iscrizione_id: {{ $iscrizione->id }} })"
                                tag="button"
                                type="button"
                            >Ripristina</x-filament::button>
                        @endif
                    </div>
                @endforeach
            </div>
        </details>
    @endif

    <style>
        /* Card "Comunicazioni agli iscritti" — stessa struttura di gestione-staff.blade.php
           (.staff-comm-card): testo sempre sopra, azioni sempre in riga propria sotto. */
        .corso-comm-card {
            display:flex; flex-direction:column; align-items:center; gap:10px;
            padding:16px 12px; border:1px solid #f3f4f6; border-radius:8px; text-align:center;
        }
        .corso-comm-card-title { font-size:12px; font-weight:600; color:#374151; }
        .corso-comm-card-status { font-size:12px; color:#6b7280; margin-top:2px; }
        .corso-comm-card-status.is-done { color:#16a34a; }
        .corso-comm-card-actions { display:flex; justify-content:center; gap:8px; }
    </style>

    <script>
        function copiaLink(url, btn) {
            navigator.clipboard.writeText(url).then(() => {
                const label = btn.querySelector('.copy-label');
                if (!label) return;
                const orig = label.textContent;
                label.textContent = 'Copiato!';
                setTimeout(() => { label.textContent = orig; }, 2000);
            });
        }
    </script>

</x-filament-panels::page>
