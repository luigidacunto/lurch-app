<x-filament-panels::page>
    @php
        $evento     = $this->record;
        $staff      = $evento->staff->sortBy('partecipante.cognome');
        $totale     = $staff->count();
        // Contati per disponibilità dichiarata (uno stesso membro può comparire in
        // entrambi, la duplicazione è voluta); prima del form compilato si usa il
        // ruolo assegnato in import/creazione come stima provvisoria.
        $haFormazione = fn ($i) => $i->rispostaPreregistrazione
            ? collect($i->rispostaPreregistrazione->risposte['disponibilita'] ?? [])->pluck('tipo')->contains('formazione')
            : $i->ruolo === 'docenza';
        $haLogistica  = fn ($i) => $i->rispostaPreregistrazione
            ? collect($i->rispostaPreregistrazione->risposte['disponibilita'] ?? [])->pluck('tipo')->contains('logistica')
            : $i->ruolo === 'logistica';
        $docenti    = $staff->filter($haFormazione)->count();
        $logistica  = $staff->filter($haLogistica)->count();
        $eventoConcluso = today()->gt($evento->data_fine);

        $formAttivo         = (bool) $evento->form_staff_aperto_at;
        $formInviati        = $staff->whereNotNull('form_inviato_at')->count();
        $formCompilati      = $staff->filter(fn($i) => $i->rispostaPreregistrazione !== null)->count();
        $mancanti           = $totale - $formCompilati;
        $senzaEmail         = $staff->filter(fn($i) => !$i->partecipante?->email)->count();
        $sollecitoAbilitato = $evento->invio_massivo_staff_at
            && $evento->invio_massivo_staff_at->diffInDays(now()) >= 3;

        $formPendentiCount = $staff->filter(fn ($i) => ! $i->form_inviato_at)->count();
        $invioEffettuato   = $formPendentiCount === 0;
        $invioLabel        = $evento->invio_massivo_staff_at
            ? 'Ultimo invio: ' . $evento->invio_massivo_staff_at->format('d/m/Y H:i')
            : 'Invio massivo non ancora effettuato';

        $sollecitoInviati       = $staff->whereNotNull('sollecito_inviato_at')->count();
        $sollecitoPendentiCount = $staff->filter(fn ($i) => $i->rispostaPreregistrazione === null && ! $i->sollecito_inviato_at)->count();
        $sollecitoEffettuato    = $sollecitoPendentiCount === 0;
        $sollecitoLabel         = $evento->invio_massivo_sollecito_staff_at
            ? 'Ultimo invio: ' . $evento->invio_massivo_sollecito_staff_at->format('d/m/Y H:i')
            : 'Invio massivo non ancora effettuato';

        $hasComunicazione = $evento->hasComunicazioneValida();
        $infoInviati       = $staff->whereNotNull('info_evento_inviato_at')->count();
        $infoPendentiCount = $staff->filter(fn ($i) => $i->rispostaPreregistrazione !== null && ! $i->info_evento_inviato_at)->count();
        $infoEffettuata    = $infoPendentiCount === 0;
        $infoLabel         = $evento->invio_massivo_info_staff_at
            ? 'Ultimo invio: ' . $evento->invio_massivo_info_staff_at->format('d/m/Y H:i')
            : 'Invio massivo non ancora effettuato';
    @endphp

    {{-- Intestazione --}}
    <x-filament::section>
        <div style="display:flex; flex-direction:column; gap:4px;">
            <span style="font-size:11px; font-weight:500; text-transform:uppercase; letter-spacing:.06em; color:#9ca3af;">
                {{ $evento->nome }}
            </span>
            <h2 style="font-size:18px; font-weight:700; color:#111827; margin:0;">Staff evento</h2>
            <span style="font-size:13px; color:#6b7280;">
                {{ $evento->data_inizio->format('d/m/Y') }}
                @if(!$evento->data_inizio->eq($evento->data_fine)) — {{ $evento->data_fine->format('d/m/Y') }} @endif
                @if($evento->sede) · {{ $evento->sede }} @endif
            </span>
        </div>
        <div style="margin-top:10px; display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            @if($formAttivo)
                <span style="display:inline-flex; align-items:center; gap:5px; font-size:11px; font-weight:600; color:#15803d; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:20px; padding:4px 10px;">
                    <svg width="10" height="10" viewBox="0 0 10 10" fill="#16a34a"><circle cx="5" cy="5" r="5"/></svg>
                    Form attivo dal {{ $evento->form_staff_aperto_at->format('d/m/Y H:i') }}
                </span>
            @else
                <span style="display:inline-flex; align-items:center; gap:5px; font-size:11px; font-weight:600; color:#92400e; background:#fef3c7; border:1px solid #fde68a; border-radius:20px; padding:4px 10px;">
                    <svg width="10" height="10" viewBox="0 0 10 10" fill="#d97706"><circle cx="5" cy="5" r="5"/></svg>
                    Form non ancora attivo
                </span>
            @endif
        </div>

        {{-- Statistiche --}}
        <div style="margin-top:16px; display:grid; grid-template-columns:repeat(3,1fr); gap:16px;">
            @foreach([
                [$totale,    'Staff totale', '#f1f5f9', '#e2e8f0', '#475569', '#64748b'],
                [$docenti,   'Docenza',      '#f5f3ff', '#ddd6fe', '#7c3aed', '#6d28d9'],
                [$logistica, 'Logistica',    '#f0fdfa', '#99f6e4', '#0f766e', '#0d9488'],
            ] as [$val, $label, $bg, $border, $numColor, $labelColor])
                <div style="text-align:center; padding:14px 16px; background:{{ $bg }}; border:1px solid {{ $border }}; border-radius:10px;">
                    <div style="font-size:1.6rem; font-weight:700; color:{{ $numColor }};">{{ $val }}</div>
                    <div style="font-size:0.78rem; color:{{ $labelColor }}; margin-top:2px;">{{ $label }}</div>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    {{-- Comunicazioni allo staff --}}
    <x-filament::section>
        <x-slot name="heading">Comunicazioni allo staff</x-slot>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:16px;">
            {{-- Form di preregistrazione --}}
            <div class="staff-comm-card">
                <div>
                    <div class="staff-comm-card-title">Form di preregistrazione</div>
                    <div class="staff-comm-card-status {{ $invioEffettuato ? 'is-done' : '' }}">{{ $invioLabel }}</div>
                </div>
                <div class="staff-comm-card-actions">
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
                        :disabled="$eventoConcluso || $invioEffettuato"
                        :tooltip="$eventoConcluso ? 'Evento concluso: invio non più disponibile' : ($invioEffettuato ? 'Tutto lo staff ha già ricevuto il form' : null)"
                    >{{ $invioEffettuato ? 'Nessuno da inviare' : "Invia a tutti ({$formPendentiCount})" }}</x-filament::button>
                </div>
                <div class="staff-comm-card-counts">
                    <span>Inviati: <strong>{{ $formInviati }}</strong></span>
                    <span>Compilati: <strong>{{ $formCompilati }}</strong></span>
                    <span>Mancanti: <strong>{{ $mancanti }}</strong></span>
                </div>
            </div>

            {{-- Sollecito --}}
            @if($sollecitoAbilitato)
                <div class="staff-comm-card">
                    <div>
                        <div class="staff-comm-card-title">Sollecito preregistrazione</div>
                        <div class="staff-comm-card-status {{ $sollecitoEffettuato ? 'is-done' : '' }}">{{ $sollecitoLabel }}</div>
                    </div>
                    <div class="staff-comm-card-actions">
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
                            :disabled="$eventoConcluso || $sollecitoEffettuato"
                            :tooltip="$eventoConcluso ? 'Evento concluso: invio non più disponibile' : ($sollecitoEffettuato ? 'Nessun membro staff da sollecitare al momento' : null)"
                        >{{ $sollecitoEffettuato ? 'Nessuno da inviare' : "Invia a tutti ({$sollecitoPendentiCount})" }}</x-filament::button>
                    </div>
                    <div class="staff-comm-card-counts">
                        <span>Inviati: <strong>{{ $sollecitoInviati }}</strong></span>
                        <span>Da sollecitare: <strong>{{ $sollecitoPendentiCount }}</strong></span>
                    </div>
                </div>
            @endif

            {{-- Comunicazione evento — card sempre visibile (non solo se $hasComunicazione):
                 "Invia a tutti" va disabilitato con un motivo quando manca il contenuto, non
                 far sparire l'intera card (stessa convenzione disabled+tooltip di GestioneEvento). --}}
            <div class="staff-comm-card">
                <div>
                    <div class="staff-comm-card-title">Comunicazione evento</div>
                    <div class="staff-comm-card-status {{ $infoEffettuata ? 'is-done' : '' }}">{{ $infoLabel }}</div>
                </div>
                <div class="staff-comm-card-actions">
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
                        wire:click="mountAction('inviaInfoStaffTutti')"
                        icon="{{ $infoEffettuata ? 'heroicon-o-check-circle' : 'heroicon-o-information-circle' }}"
                        color="{{ $infoEffettuata ? 'gray' : 'brand_pink' }}"
                        size="sm"
                        tag="button"
                        type="button"
                        :disabled="! $hasComunicazione || $eventoConcluso || $infoEffettuata"
                        :tooltip="! $hasComunicazione
                            ? 'Nessuna informazione definita da inviare: entra nella modifica evento per inserire testo o allegati'
                            : ($eventoConcluso ? 'Evento concluso: invio non più disponibile' : ($infoEffettuata ? 'Tutto lo staff ha già ricevuto la comunicazione' : null))"
                    >{{ $infoEffettuata ? 'Nessuno da inviare' : "Invia a tutti ({$infoPendentiCount})" }}</x-filament::button>
                </div>
                <div class="staff-comm-card-counts">
                    <span>Inviate: <strong>{{ $infoInviati }}</strong></span>
                    <span>Mancanti: <strong>{{ $infoPendentiCount }}</strong></span>
                </div>
            </div>
        </div>
    </x-filament::section>

    {{-- Resoconto disponibilità staff --}}
    @php
        $grigliaDisponibilita = $this->getGrigliaDisponibilita();
        $haRisposte = $staff->filter(fn($i) => $i->rispostaPreregistrazione !== null)->isNotEmpty();
    @endphp

    @if($haRisposte)
        <x-filament::section>
            <x-slot name="heading">Disponibilità staff per slot</x-slot>

            <div style="border:1px solid #e5e7eb; border-radius:8px; overflow:hidden;">

                {{-- Header colonne --}}
                <div style="display:grid; grid-template-columns:140px 1fr 1fr; background:#f9fafb; border-bottom:2px solid #e5e7eb; font-size:11px; font-weight:600; color:#6b7280; text-transform:uppercase; letter-spacing:.04em;">
                    <div style="padding:8px 12px;">Giorno</div>
                    <div style="padding:8px 12px; border-left:1px solid #e5e7eb;">Mattina</div>
                    <div style="padding:8px 12px; border-left:1px solid #e5e7eb;">Pomeriggio</div>
                </div>

                @foreach($grigliaDisponibilita as $data => $giorno)
                    @php
                        $mattDoc   = count($giorno['mattina']['formazione']);
                        $mattStaff = count($giorno['mattina']['logistica']);
                        $pomDoc    = count($giorno['pomeriggio']['formazione']);
                        $pomStaff  = count($giorno['pomeriggio']['logistica']);
                    @endphp

                    <details style="border-bottom:1px solid #f3f4f6;">

                        {{-- Riga riepilogo (sempre visibile) --}}
                        <summary style="list-style:none; -webkit-appearance:none; cursor:pointer; display:grid; grid-template-columns:140px 1fr 1fr; align-items:center; background:#ffffff; user-select:none;"
                                 onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='#ffffff'">

                            {{-- Giorno + chevron --}}
                            <div style="padding:10px 12px; display:flex; align-items:center; gap:7px;">
                                <svg style="width:12px;height:12px;color:#9ca3af;flex-shrink:0;transition:transform .15s;" class="details-chevron" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/>
                                </svg>
                                <span style="font-weight:600; color:#374151; font-size:13px; white-space:nowrap;">
                                    {{ \Carbon\Carbon::parse($data)->translatedFormat('D j/m') }}
                                </span>
                            </div>

                            {{-- Mattina conteggi --}}
                            <div style="padding:10px 12px; border-left:1px solid #f3f4f6; display:flex; align-items:center; gap:6px;">
                                @if($mattDoc)
                                    <span class="staff-tag-docenza">{{ $mattDoc }} docenza</span>
                                @endif
                                @if($mattStaff)
                                    <span class="staff-tag-logistica">{{ $mattStaff }} logistica</span>
                                @endif
                                @if(!$mattDoc && !$mattStaff)
                                    <span style="font-size:11px; color:#d1d5db;">—</span>
                                @endif
                            </div>

                            {{-- Pomeriggio conteggi --}}
                            <div style="padding:10px 12px; border-left:1px solid #f3f4f6; display:flex; align-items:center; gap:6px;">
                                @if($pomDoc)
                                    <span class="staff-tag-docenza">{{ $pomDoc }} docenza</span>
                                @endif
                                @if($pomStaff)
                                    <span class="staff-tag-logistica">{{ $pomStaff }} logistica</span>
                                @endif
                                @if(!$pomDoc && !$pomStaff)
                                    <span style="font-size:11px; color:#d1d5db;">—</span>
                                @endif
                            </div>

                        </summary>

                        {{-- Dettaglio nomi (espanso) --}}
                        <div style="display:grid; grid-template-columns:140px 1fr 1fr; background:#fafafa; border-top:1px solid #f3f4f6;">

                            <div style="padding:10px 12px; font-size:11px; color:#9ca3af; font-style:italic; display:flex; align-items:flex-start;">
                                dettaglio
                            </div>

                            {{-- Nomi mattina --}}
                            <div style="padding:10px 12px; border-left:1px solid #f3f4f6; display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                                <div>
                                    <div class="staff-text-docenza" style="font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; margin-bottom:4px;">Docenza</div>
                                    @forelse($giorno['mattina']['formazione'] as $nome)
                                        <div class="staff-text-docenza" style="font-size:12px; white-space:nowrap;">{{ $nome }}</div>
                                    @empty
                                        <span style="color:#d1d5db; font-size:11px;">—</span>
                                    @endforelse
                                </div>
                                <div>
                                    <div class="staff-text-logistica" style="font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; margin-bottom:4px;">Logistica</div>
                                    @forelse($giorno['mattina']['logistica'] as $nome)
                                        <div class="staff-text-logistica" style="font-size:12px; white-space:nowrap;">{{ $nome }}</div>
                                    @empty
                                        <span style="color:#d1d5db; font-size:11px;">—</span>
                                    @endforelse
                                </div>
                            </div>

                            {{-- Nomi pomeriggio --}}
                            <div style="padding:10px 12px; border-left:1px solid #f3f4f6; display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                                <div>
                                    <div class="staff-text-docenza" style="font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; margin-bottom:4px;">Docenza</div>
                                    @forelse($giorno['pomeriggio']['formazione'] as $nome)
                                        <div class="staff-text-docenza" style="font-size:12px; white-space:nowrap;">{{ $nome }}</div>
                                    @empty
                                        <span style="color:#d1d5db; font-size:11px;">—</span>
                                    @endforelse
                                </div>
                                <div>
                                    <div class="staff-text-logistica" style="font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; margin-bottom:4px;">Logistica</div>
                                    @forelse($giorno['pomeriggio']['logistica'] as $nome)
                                        <div class="staff-text-logistica" style="font-size:12px; white-space:nowrap;">{{ $nome }}</div>
                                    @empty
                                        <span style="color:#d1d5db; font-size:11px;">—</span>
                                    @endforelse
                                </div>
                            </div>

                        </div>

                    </details>
                @endforeach

            </div>
        </x-filament::section>

    @endif

    {{-- Lista staff --}}
    <x-filament::section>
        <x-slot name="heading">Membri dello staff</x-slot>

        @php
            // Filtro "docenza per corso": il legame staff↔corso passa dalla candidatura
            // (candidatura_staff_corso), non da iscrizioni_staff. Chi non ha candidatura
            // o non ha corsi collegati esce dal filtro quando si sceglie un protocollo.
            $filtriStaff = [
                ['key' => 'ruolo',     'placeholder' => 'Tutti i ruoli',    'options' => ['docenza' => 'Solo docenza', 'logistica' => 'Solo logistica']],
                ['key' => 'form',      'placeholder' => 'Form: tutti',       'options' => ['compilato' => 'Form compilato', 'mancante' => 'Form non compilato']],
                ['key' => 'nullaosta', 'placeholder' => 'Nullaosta: tutti',  'options' => ['mancante' => 'Nullaosta mancante', 'da-verificare' => 'Nullaosta da verificare', 'ok' => 'Nullaosta verificato', 'non-richiesto' => 'Nullaosta non richiesto']],
            ];
            if ($evento->corsi->isNotEmpty()) {
                $filtriStaff[] = [
                    'key'         => 'corso',
                    'placeholder' => 'Docenza per corso: tutti',
                    'options'     => $evento->corsi
                        ->sortBy(fn ($c) => $c->numero_protocollo ?? $c->nome)
                        ->mapWithKeys(fn ($c) => [(string) $c->id => $c->numero_protocollo ?: $c->nome])
                        ->all(),
                ];
            }
        @endphp

        <x-griglia-filtri :totale="$staff->count()" vuoto="Nessun membro dello staff corrisponde ai filtri." :selects="$filtriStaff">
            @forelse($staff as $iscrizione)
                @php
                    $p          = $iscrizione->partecipante;
                    $telefono   = trim(($p?->prefisso_telefono ?? '') . ' ' . ($p?->telefono ?? ''));
                    $inviato    = $iscrizione->form_inviato_at !== null;
                    $compilato  = $iscrizione->rispostaPreregistrazione !== null;
                    $rowRuolo = trim(($haFormazione($iscrizione) ? 'docenza ' : '') . ($haLogistica($iscrizione) ? 'logistica' : ''));
                    $rowCorsi = $iscrizione->candidaturaStaff?->corsi->pluck('id')->implode(' ') ?? '';
                    $rowSearch = mb_strtolower(trim(implode(' ', array_filter([
                        $p?->cognome, $p?->nome, $p?->codice_fiscale, $p?->email, $p?->comitatoLocale?->nome,
                    ]))));
                    $linkForm   = route('form-staff.show', $iscrizione->token);
                    $hasLogisticaStaff = collect($iscrizione->rispostaPreregistrazione?->risposte['disponibilita'] ?? [])
                        ->pluck('tipo')->contains('logistica');
                    $nullaostaPathStaff       = $iscrizione->rispostaPreregistrazione?->nullaosta_path;
                    $nullaostaVerificatoStaff = $iscrizione->rispostaPreregistrazione?->nullaosta_verificato ?? false;
                    $nullaostaVerificatoAtStaff = $iscrizione->rispostaPreregistrazione?->nullaosta_verificato_at;
                    $nullaostaVerificatoDaStaff = $iscrizione->rispostaPreregistrazione?->nullaostaVerificatoDa?->name;
                    $nomeStaffPerModale = trim(($p?->cognome ?? '') . ' ' . ($p?->nome ?? ''));
                @endphp

                @php
                    $sollecito        = $iscrizione->sollecito_inviato_at;
                    $infoInviato      = $iscrizione->info_evento_inviato_at;
                    $canSendForm      = $p?->email && !$compilato;
                    $canSendSollecito = $canSendForm && $sollecitoAbilitato && $inviato;
                    $rowNullaosta = match(true) {
                        $compilato && ! $hasLogisticaStaff => 'non-richiesto',
                        $nullaostaVerificatoStaff          => 'ok',
                        (bool) $nullaostaPathStaff         => 'da-verificare',
                        default                            => 'mancante',
                    };
                @endphp
                <div data-row
                     data-search="{{ $rowSearch }}"
                     data-ruolo="{{ $rowRuolo }}"
                     data-form="{{ $compilato ? 'compilato' : 'mancante' }}"
                     data-nullaosta="{{ $rowNullaosta }}"
                     data-corso="{{ $rowCorsi }}"
                     style="display:flex; align-items:center; gap:12px; padding:12px 0; border-bottom:1px solid #f3f4f6; flex-wrap:wrap;">

                    {{-- Sinistra: Modifica dati sempre visibile --}}
                    <div style="flex-shrink:0;">
                        @if($compilato)
                            <x-filament::button
                                color="info" size="sm" icon="heroicon-o-pencil-square"
                                wire:click="mountAction('modificaDatiStaff', {iscrizione_id: {{ $iscrizione->id }}})"
                                tag="button" type="button"
                            >Modifica dati</x-filament::button>
                        @else
                            <x-filament::button
                                color="{{ $inviato ? 'info' : 'gray' }}" size="sm"
                                icon="heroicon-o-pencil-square"
                                disabled tag="button" type="button"
                            >Modifica dati</x-filament::button>
                        @endif
                    </div>

                    {{-- Centro: nome e info --}}
                    <div style="flex:1; min-width:0;">
                        <div style="font-size:13px; font-weight:600; color:#111827;">{{ $p?->cognome }} {{ $p?->nome }}</div>
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

                    {{-- Destra: colonne per pulsante (con data sotto) + icone in fondo --}}
                    <div style="display:flex; flex-direction:column; align-items:flex-end; gap:6px; flex-shrink:0;">

                        {{-- Riga pulsanti: ciascuno in colonna con data sotto --}}
                        <div style="display:flex; align-items:flex-start; gap:8px;">

                            {{-- Nullaosta Presidente — sempre presente (anche prima della compilazione:
                                 "mancante" resta coerente, indica solo che non è ancora arrivato), stessa
                                 pillola "Nullaosta" in tutti gli stati per allineamento uniforme tra le
                                 righe — cambiano solo colore/icona/tooltip. Cliccabile (apre l'anteprima)
                                 solo quando il file esiste (verificato o da verificare). --}}
                            <div style="display:flex; align-items:center; gap:6px;">
                                {{-- Pulsanti a sinistra, contenitore a larghezza fissa allineato a destra
                                     contro la pillola: chi non ha pulsanti mantiene comunque lo stesso
                                     spazio, così le pillole restano allineate alla stessa X tra le righe.
                                     Fino a 2 pulsanti (verifica + sostituisci). --}}
                                <div style="width:64px; display:flex; justify-content:flex-end; align-items:center; gap:2px; flex-shrink:0;">
                                    @if($compilato && $hasLogisticaStaff && $nullaostaVerificatoStaff)
                                        <x-filament::icon-button
                                            color="warning" size="sm" icon="heroicon-o-x-mark"
                                            wire:click="mountAction('annullaVerificaNullaosta', { iscrizione_id: {{ $iscrizione->id }} })"
                                            tag="button" type="button" label="Annulla verifica nullaosta" tooltip="Annulla verifica"
                                        />
                                    @elseif($compilato && $hasLogisticaStaff && $nullaostaPathStaff)
                                        <x-filament::icon-button
                                            color="success" size="sm" icon="heroicon-o-check"
                                            wire:click="mountAction('verificaNullaosta', { iscrizione_id: {{ $iscrizione->id }} })"
                                            tag="button" type="button" label="Verifica nullaosta" tooltip="Segna come verificato"
                                        />
                                        <x-filament::icon-button
                                            color="gray" size="sm" icon="heroicon-o-arrow-path"
                                            wire:click="mountAction('sostituisciNullaosta', { iscrizione_id: {{ $iscrizione->id }}, nome: '{{ addslashes($nomeStaffPerModale) }}' })"
                                            tag="button" type="button" label="Sostituisci nullaosta" tooltip="Sostituisci il file"
                                        />
                                    @endif
                                </div>

                                @if($compilato && ! $hasLogisticaStaff)
                                    <span style="display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:500; color:#6b7280; background:#f9fafb; border:1px solid #e5e7eb; border-radius:6px; padding:5px 10px; white-space:nowrap;" title="Non necessario per i docenti">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                        Nullaosta
                                    </span>
                                @elseif($nullaostaVerificatoStaff)
                                    <button type="button"
                                            wire:click="mountAction('visualizzaNullaosta', { iscrizione_id: {{ $iscrizione->id }}, nome: '{{ addslashes($nomeStaffPerModale) }}' })"
                                            style="display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:500; color:#15803d; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:6px; padding:5px 10px; cursor:pointer; white-space:nowrap;"
                                            title="Verificato il {{ $nullaostaVerificatoAtStaff?->format('d/m/Y H:i') }}{{ $nullaostaVerificatoDaStaff ? ' da ' . $nullaostaVerificatoDaStaff : '' }} — clicca per visualizzare">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        Nullaosta
                                    </button>
                                @elseif($nullaostaPathStaff)
                                    <button type="button"
                                            wire:click="mountAction('visualizzaNullaosta', { iscrizione_id: {{ $iscrizione->id }}, nome: '{{ addslashes($nomeStaffPerModale) }}' })"
                                            style="display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:500; color:#b45309; background:#fffbeb; border:1px solid #fde68a; border-radius:6px; padding:5px 10px; cursor:pointer; white-space:nowrap;"
                                            title="Da verificare — clicca per visualizzare il nullaosta">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M12 17.25h.008v.008H12v-.008z"/></svg>
                                        Nullaosta
                                    </button>
                                @else
                                    <span style="display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:500; color:#b91c1c; background:#fef2f2; border:1px solid #fecaca; border-radius:6px; padding:5px 10px; white-space:nowrap;" title="{{ $compilato ? 'Nullaosta non ancora caricato' : 'In attesa che il form venga compilato' }}">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                        Nullaosta
                                    </span>
                                @endif
                            </div>

                            {{-- Compilato badge (se compilato, al posto del pagamento che non esiste per lo staff) --}}
                            @if($compilato)
                                @php $compilatoAt = $iscrizione->rispostaPreregistrazione?->created_at; @endphp
                                <div style="display:flex; flex-direction:column; align-items:center; gap:2px;">
                                    <span style="display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:600; color:#16a34a; background:transparent; border:1px solid #16a34a; border-radius:6px; padding:5px 10px; white-space:nowrap;">
                                        ✓ Compilato
                                    </span>
                                    @if($compilatoAt)
                                        <span style="font-size:10px;color:#9ca3af;white-space:nowrap;">{{ $compilatoAt->format('d/m H:i') }}</span>
                                    @endif
                                </div>
                            @endif

                            {{-- Copia link --}}
                            <x-filament::button
                                color="gray" size="sm" icon="heroicon-o-clipboard-document"
                                onclick="copiaLink('{{ $linkForm }}', this)"
                                tag="button" type="button"
                            ><span class="copy-label">Copia link</span></x-filament::button>

                            {{-- Form --}}
                            @if($canSendForm)
                                <div style="display:flex; flex-direction:column; align-items:center; gap:2px;">
                                    <x-filament::button
                                        color="brand_pink" size="sm"
                                        icon="heroicon-o-paper-airplane"
                                        wire:click="inviaFormSingolo({{ $iscrizione->id }})"
                                        wire:loading.attr="disabled" wire:target="inviaFormSingolo({{ $iscrizione->id }})"
                                        tag="button" type="button"
                                        :disabled="$eventoConcluso || $inviato"
                                        :tooltip="$inviato ? 'Form già inviato — usa il sollecito per un nuovo promemoria' : ($eventoConcluso ? 'Evento concluso: invio non più disponibile' : null)"
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
                                        wire:click="inviaSollecitoSingolo({{ $iscrizione->id }})"
                                        wire:loading.attr="disabled" wire:target="inviaSollecitoSingolo({{ $iscrizione->id }})"
                                        tag="button" type="button"
                                        :disabled="$eventoConcluso"
                                        :tooltip="$eventoConcluso ? 'Evento concluso: invio non più disponibile' : null"
                                    >Invia sollecito</x-filament::button>
                                    @if($sollecito)
                                        <span style="font-size:10px;color:#9ca3af;">{{ $sollecito->format('d/m H:i') }}</span>
                                    @endif
                                </div>
                            @endif

                            {{-- Info evento — sempre visibile, disabilitato con motivo se manca il
                                 contenuto (stessa convenzione disabled+tooltip di sopra). --}}
                            <div style="display:flex; flex-direction:column; align-items:center; gap:2px;">
                                <x-filament::button
                                    color="brand_pink" size="sm" icon="heroicon-o-information-circle"
                                    wire:click="inviaInfoStaffSingolo({{ $iscrizione->id }})"
                                    wire:loading.attr="disabled" wire:target="inviaInfoStaffSingolo({{ $iscrizione->id }})"
                                    tag="button" type="button"
                                    :disabled="! $hasComunicazione || $eventoConcluso"
                                    :tooltip="! $hasComunicazione ? 'Nessuna informazione definita da inviare: entra nella modifica evento per inserire testo o allegati' : ($eventoConcluso ? 'Evento concluso: invio non più disponibile' : null)"
                                >{{ $infoInviato ? 'Reinvia info' : 'Invia info' }}</x-filament::button>
                                @if($infoInviato)
                                    <span style="font-size:10px;color:#9ca3af;">{{ $infoInviato->format('d/m H:i') }}</span>
                                @endif
                            </div>

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
                                            wire:click="mountAction('resetFormStaff', { iscrizione_id: {{ $iscrizione->id }} })"
                                        >Reset form</x-filament::dropdown.list.item>
                                    @endif
                                    <x-filament::dropdown.list.item
                                        icon="heroicon-o-trash" color="danger" tag="button" type="button"
                                        wire:click="mountAction('rimuoviStaff', { iscrizione_id: {{ $iscrizione->id }} })"
                                    >Rimuovi dallo staff</x-filament::dropdown.list.item>
                                </x-filament::dropdown.list>
                            </x-filament::dropdown>

                        </div>

                    </div>
                </div>
            @empty
                <div style="text-align:center; padding:32px; font-size:13px; color:#9ca3af;">
                    Nessun membro dello staff. Usa "Importa Staff" per caricare la lista da Excel.
                </div>
            @endforelse
        </x-griglia-filtri>
    </x-filament::section>

    <style>
        details[open] .details-chevron { transform: rotate(90deg); }
        details summary::-webkit-details-marker { display:none; }

        /* Docenza/Logistica — coppia violet/teal standard per l'area staff,
           vedi resources/views/filament/candidatura-staff/dettagli.blade.php */
        .staff-tag-docenza, .staff-tag-logistica {
            font-size:12px; border-radius:5px; padding:2px 8px; font-weight:600; border:1px solid;
        }
        .staff-tag-docenza   { background:#f5f3ff; color:#7c3aed; border-color:#ddd6fe; }
        .staff-tag-logistica { background:#f0fdfa; color:#0f766e; border-color:#99f6e4; }
        .staff-text-docenza   { color:#7c3aed; }
        .staff-text-logistica { color:#0f766e; }

        /* Card "Comunicazioni allo staff": stessa struttura ripetuta 3 volte
           (Form/Sollecito/Comunicazione) — testo sempre sopra, azioni sempre
           in riga propria sotto, mai a wrap in base allo spazio disponibile. */
        .staff-comm-card {
            display:flex; flex-direction:column; align-items:center; gap:10px;
            padding:16px 12px; border:1px solid #f3f4f6; border-radius:8px; text-align:center;
        }
        .staff-comm-card-title { font-size:12px; font-weight:600; color:#374151; }
        .staff-comm-card-status { font-size:12px; color:#6b7280; margin-top:2px; }
        .staff-comm-card-status.is-done { color:#16a34a; }
        .staff-comm-card-actions { display:flex; justify-content:center; gap:8px; }
        .staff-comm-card-counts { display:flex; justify-content:center; gap:12px; font-size:11px; color:#9ca3af; }
        .staff-comm-card-counts strong { color:#374151; }
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
