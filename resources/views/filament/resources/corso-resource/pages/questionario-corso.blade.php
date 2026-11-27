<x-filament-panels::page>
    @php
        $corso        = $this->record;
        $questionario = $this->questionario();
        $inviato      = $questionario?->eInviato();
        $blocco       = $this->motivoBlocco();
        $invitati     = $this->invitati();
        $iscrizioni   = $this->iscrizioni();
        $compilazioni = $inviato ? \App\Questionari\StatisticheQuestionario::compilazioni($questionario) : 0;
        $visibili     = $inviato && $compilazioni >= \App\Questionari\StatisticheQuestionario::SOGLIA;
        $avvisoEsame  = $this->avvisoEsame();
        $etichetta    = 'font-size:11px; color:#9ca3af; text-transform:uppercase; letter-spacing:.05em; margin-bottom:2px;';
    @endphp

    {{-- Stato e impostazioni --}}
    <x-filament::section>
        <x-slot name="heading">Impostazioni e stato</x-slot>
        <div style="display:flex; gap:32px; flex-wrap:wrap; align-items:flex-start;">
            <div>
                <p style="{{ $etichetta }}">Stato</p>
                <p style="font-size:14px; font-weight:700; color:#111827;">
                    @if(! $questionario) Non impostato
                    @elseif(! $inviato) Impostato, non ancora inviato
                    @elseif($questionario->eAperto()) Aperto fino al {{ $questionario->chiude_il->format('d/m/Y') }}
                    @else Chiuso il {{ $questionario->chiude_il->format('d/m/Y') }}
                    @endif
                </p>
                @if($inviato)
                    <p style="font-size:12px; color:#6b7280;">Primo invio il {{ $questionario->inviato_il->format('d/m/Y H:i') }}@if($questionario->inviatoDa) da {{ $questionario->inviatoDa->name }}@endif</p>
                @endif
            </div>
            @if($questionario)
                <div>
                    <p style="{{ $etichetta }}">Giorni di apertura</p>
                    <p style="font-size:14px; color:#111827;">{{ $questionario->giorni_apertura }}</p>
                </div>
                <div>
                    <p style="{{ $etichetta }}">Sezioni facoltative</p>
                    <p style="font-size:14px; color:#111827;">
                        {{ collect($questionario->sezioni)->map(fn ($k) => \App\Questionari\Domande::FLAG[$k] ?? $k)->join(', ') ?: 'Nessuna' }}
                    </p>
                </div>
            @endif
            @if($inviato)
                @php $conteggi = \App\Questionari\CompilazioneQuestionario::conteggi($questionario); @endphp
                <div>
                    <p style="{{ $etichetta }}">Discenti invitati</p>
                    <div style="display:flex; gap:18px; align-items:baseline;">
                        @foreach(['invitati' => ['Invitati', '#111827'], 'mai_aperto' => ['Mai aperto', '#6b7280'], 'iniziati' => ['Iniziati', '#b45309'], 'completati' => ['Completati', '#15803d']] as $k => [$nome, $colore])
                            <div>
                                <p style="font-size:20px; font-weight:700; color:{{ $colore }}; line-height:1.1;">{{ $conteggi[$k] }}</p>
                                <p style="font-size:11px; color:#6b7280;">{{ $nome }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
                <div>
                    <p style="{{ $etichetta }}">Valutati nella sezione docenti</p>
                    <p style="font-size:14px; color:#111827;">{{ collect($questionario->valutati)->map(fn ($v) => \App\Questionari\InvioQuestionario::titoloValutato($v))->join(' · ') }}</p>
                </div>
            @endif
        </div>
        <div style="margin-top:14px;">{{ $this->configuraAction }}</div>
    </x-filament::section>

    {{-- Destinatari e invio --}}
    <x-filament::section>
        <x-slot name="heading">Destinatari</x-slot>
        <x-slot name="description">
            Si invia una sola email per iscritto, senza solleciti: chi l'ha già ricevuta non è più selezionabile. Il pannello mostra solo quanti hanno aperto, iniziato o completato il questionario, mai chi.
        </x-slot>

        @if($blocco)
            <div style="background:#f3f4f6; color:#374151; border-radius:8px; padding:10px 12px; font-size:13px; margin-bottom:12px;">{{ $blocco }}</div>
        @endif

        @if($avvisoEsame > 0 && ! $blocco)
            <div role="alert" style="border:2px solid #dc2626; background:#fef2f2; color:#b91c1c; border-radius:8px; padding:12px 14px; font-size:14px; font-weight:700; margin-bottom:12px;">
                Il corso ha un esame e {{ $avvisoEsame }} {{ $avvisoEsame === 1 ? 'discente selezionato non ha' : 'discenti selezionati non hanno' }} ancora pubblicati né il test scritto né l'esito finale.
                L'email non citerà i risultati dell'esame: se è una svista, pubblica prima i risultati e poi invia un'unica email.
            </div>
        @endif

        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:13px;">
                <thead>
                    <tr style="text-align:left; color:#6b7280; font-size:11px; text-transform:uppercase; letter-spacing:.05em;">
                        <th style="padding:6px 8px; width:36px;"></th>
                        <th style="padding:6px 8px;">Iscritto</th>
                        <th style="padding:6px 8px;">Stato</th>
                        <th style="padding:6px 8px;">Email del questionario</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($iscrizioni as $i)
                        @php
                            $p         = $i->partecipante;
                            $giaInviato = $invitati[$i->id] ?? null;
                            $senzaEmail = ! $p?->email;
                            $bloccata  = $giaInviato || $senzaEmail || $blocco;
                        @endphp
                        <tr style="border-top:1px solid #f3f4f6;">
                            <td style="padding:6px 8px;">
                                @if($giaInviato)
                                    <input type="checkbox" checked disabled aria-label="Già inviato a {{ $p?->cognome }} {{ $p?->nome }}">
                                @else
                                    <input type="checkbox" value="{{ $i->id }}" wire:model.live="selezionati" @disabled($bloccata)
                                           aria-label="Seleziona {{ $p?->cognome }} {{ $p?->nome }}">
                                @endif
                            </td>
                            <td style="padding:6px 8px; color:#111827;">{{ $p?->cognome }} {{ $p?->nome }}</td>
                            <td style="padding:6px 8px; color:#6b7280;">
                                @if($i->isRitirato()) Ritirato @elseif($i->isNonAmmesso()) Non ammesso all'esame @else Iscritto @endif
                            </td>
                            <td style="padding:6px 8px; color:#6b7280;">
                                @if($giaInviato) Inviata il {{ \Illuminate\Support\Carbon::parse($giaInviato)->format('d/m/Y') }}
                                @elseif($senzaEmail) Senza email: non si può inviare
                                @else —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" style="padding:10px 8px; color:#9ca3af;">Nessun iscritto al corso.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:14px; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
            {{ $this->anteprimaAction }}
            {{ $this->inviaAction }}
            <span style="font-size:12px; color:#6b7280;">Selezionati: {{ count($selezionati) }}</span>
        </div>
    </x-filament::section>

    {{-- Risultati --}}
    @if($inviato)
        <x-filament::section>
            <x-slot name="heading">Risultati</x-slot>
            @if(! $visibili)
                <p style="font-size:13px; color:#6b7280;">
                    I risultati compaiono da {{ \App\Questionari\StatisticheQuestionario::SOGLIA }} compilazioni in su (oggi {{ $compilazioni }}), anche con il questionario ancora aperto: così le prime risposte non sono riconoscibili.
                </p>
            @else
                <p style="font-size:12px; color:#6b7280; margin-bottom:12px;">Risposte anonime: punteggi aggregati, commenti in ordine casuale e senza date. Tocca una sezione della panoramica o il suo titolo per aprirla.</p>
                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px;">
                    @foreach($questionario->valutati as $indice => $v)
                        <x-filament::button tag="a" size="sm" color="info" icon="heroicon-o-document-text" target="_blank"
                            :href="route('corsi.questionario-pdf-docente', ['corso' => $corso, 'indice' => $indice])">
                            PDF – {{ $v['nome'] }}
                        </x-filament::button>
                    @endforeach
                </div>
                @include('questionari.statistiche', ['sezioni' => \App\Questionari\StatisticheQuestionario::per($questionario), 'modo' => 'pannello'])
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
