<x-filament-panels::page>
    @php
        $esame  = $this->record;
        $d      = $this->dati();
        $bordo  = 'rgba(128,128,128,.35)';
        $grigio = 'rgba(128,128,128,.10)';
        $verde  = 'rgba(34,197,94,.25)';
        $ambra  = 'rgba(234,179,8,.20)';
        $stat   = $d['statistica'];
        $annullate = collect($d['domande'])->whereNotNull('annullata')->keyBy('id');
        $lettere = ['A', 'B', 'C', 'D'];
        $etichettaStato = ['bozza' => 'In bozza', 'avviato' => 'Avviato', 'con_consegne' => 'Con test inviati'][$d['stato']];
        $nAttivi = collect($d['righe'])->whereIn('stato', ['avviato', 'in_compilazione', 'inviato'])->count();
        $nInviati = collect($d['righe'])->where('stato', 'inviato')->count();
        $nPubblicati = collect($d['righe'])->filter(fn ($r) => $r['pubblicato'] !== null)->count();
        $statoRiga = ['non_avviato' => 'Non avviato', 'disattivato' => 'Disattivato', 'avviato' => 'Avviato', 'in_compilazione' => 'In compilazione', 'inviato' => 'Inviato'];
    @endphp

    {{-- Stato --}}
    <div style="border:1px solid {{ $bordo }}; background:{{ $grigio }}; border-radius:8px; padding:10px 14px; display:flex; flex-wrap:wrap; gap:8px 20px; align-items:center; justify-content:space-between;">
        <div>
            <strong>Test {{ strtolower($etichettaStato) }}</strong>
            <div style="font-size:13px;">
                @if($d['stato'] === 'bozza')
                    Domande e punteggio si modificano liberamente. I discenti non vedono nulla finché non avvii il test per loro.
                @elseif($d['stato'] === 'avviato')
                    Avviato per {{ $nAttivi }} discenti. Domande e punteggio sono congelati: si possono correggere solo i refusi nei testi. Puoi ancora riportare il test in bozza finché nessuno ha inviato.
                @else
                    {{ $nInviati }} test inviati. Domande e punteggio sono congelati: si possono correggere solo i refusi nei testi.
                @endif
            </div>
        </div>
        @if($d['eDirettore'] && $d['stato'] !== 'bozza')
            <x-filament::button size="sm" color="brand_pink" type="button"
                :disabled="$d['motivoBozza'] !== null" :tooltip="$d['motivoBozza']"
                wire:click="mountAction('riportaInBozza')">Riporta il test in bozza</x-filament::button>
        @endif
    </div>

    {{-- Domande e punteggio: configurazione, in una pagina a parte --}}
    <div style="display:flex; flex-wrap:wrap; gap:8px 16px; align-items:center; justify-content:space-between;">
        <span style="font-size:14px;">{{ count($d['domande']) }} domande @if($annullate->isNotEmpty()) ({{ $annullate->count() }} {{ $annullate->count() === 1 ? 'annullata' : 'annullate' }})@endif · punteggio massimo <strong>{{ \App\Esami\Formato::unDecimale($d['massimo']) }}</strong></span>
        <x-filament::button tag="a" size="sm" color="info" :href="\App\Filament\Resources\EsameResource::getUrl('domande-test', ['record' => $esame])">Domande e punteggio</x-filament::button>
    </div>

    {{-- Statistica delle risposte: chiusa di default (ricaricando la pagina il correttore non compare) --}}
    @if(count($d['domande']) > 0)
        <x-filament::section heading="Statistica delle risposte" collapsible collapsed
            description="Per ogni domanda, nell'ordine del test, quante volte è stata scelta ogni opzione. Contano solo i test inviati ({{ $stat['inviati'] }}). Sfondo ambra: la metà o più non ha dato la risposta corretta.">
            @if($d['eDirettore'])
                <p style="margin:0 0 10px; font-size:13px;">
                    @if($d['motivoAnnulla'] === null) Puoi annullare una domanda scritta poco chiara o sbagliata: resta con le risposte di tutti ma non conta più nel punteggio.
                    @else Annullare una domanda: {{ $d['motivoAnnulla'] }} @endif
                </p>
            @endif
            @foreach($stat['domande'] as $i => $q)
                @php
                    $difficile = $q['annullata'] === null && $q['errore'] !== null && $q['errore'] >= 50;
                    $argomenti = \Illuminate\Support\Js::from(['domanda_id' => $q['id'], 'numero' => $i + 1]);
                @endphp
                <div style="border-top:1px solid {{ $bordo }}; padding:8px 6px; {{ $difficile ? 'background:' . $ambra . ';' : '' }}">
                    <div style="display:flex; flex-wrap:wrap; gap:6px 14px; align-items:flex-start; justify-content:space-between;">
                        <div style="flex:1 1 260px; font-weight:600; overflow-wrap:anywhere; white-space:pre-wrap; {{ $q['annullata'] ? 'text-decoration:line-through;' : '' }}">{{ $i + 1 }}. {{ $q['testo'] }}</div>
                        <div style="font-size:13px; text-align:right;">
                            @if($q['annullata'])
                                <strong>Domanda annullata</strong>
                            @elseif($q['errore'] !== null)
                                Errori <strong>{{ $q['errore'] }}%</strong>
                            @endif
                            · giuste {{ $q['giuste'] }} · sbagliate {{ $q['sbagliate'] }} · non date {{ $q['vuote'] }}
                        </div>
                    </div>
                    <div style="margin-top:4px; font-size:13px;">
                        @foreach($q['opzioni'] as $k => $testo)
                            @php $n = $q['scelte'][$k] ?? 0; @endphp
                            <div style="display:flex; gap:10px; justify-content:space-between; padding:2px 6px; border-radius:6px; {{ $k === $q['corretta'] ? 'background:' . $verde . '; font-weight:600;' : '' }}">
                                <span style="overflow-wrap:anywhere;">{{ $lettere[$k] }}) {{ $testo }}@if($k === $q['corretta']) ✓@endif</span>
                                <span style="white-space:nowrap;">{{ $n }}@if($stat['inviati'] > 0) · {{ (int) round(100 * $n / $stat['inviati']) }}%@endif</span>
                            </div>
                        @endforeach
                    </div>
                    @if($q['annullata'])
                        <div style="font-size:12px; margin-top:4px;">Annullata da {{ $q['annullata']['da'] ?? 'il direttore' }} il {{ $q['annullata']['il']->format('d/m/Y H:i') }} · Motivo: <span style="white-space:pre-line; overflow-wrap:anywhere;">{{ $q['annullata']['motivo'] }}</span></div>
                    @endif
                    @if($d['eDirettore'])
                        <div style="margin-top:6px;">
                            @if($q['annullata'])
                                <x-filament::button size="xs" color="success" type="button" :disabled="$d['motivoAnnulla'] !== null" :tooltip="$d['motivoAnnulla']" wire:click="mountAction('ripristinaDomanda', {{ $argomenti }})">Ripristina la domanda</x-filament::button>
                            @else
                                <x-filament::button size="xs" color="danger" type="button" :disabled="$d['motivoAnnulla'] !== null" :tooltip="$d['motivoAnnulla']" wire:click="mountAction('annullaDomanda', {{ $argomenti }})">Annulla domanda</x-filament::button>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </x-filament::section>
    @endif

    {{-- Svolgimento --}}
    <x-filament::section heading="Svolgimento" description="Solo i discenti ammessi (i ritirati e i non ammessi non compaiono). L'avvio è per discente.">
        @if($d['eDirettore'])
            <div style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:10px;">
                <x-filament::button size="sm" color="brand_pink" type="button" :disabled="$d['motivoAvvio'] !== null" :tooltip="$d['motivoAvvio']" wire:click="mountAction('avviaTutti')">Avvia per tutti</x-filament::button>
                <x-filament::button size="sm" color="brand_pink" type="button" :disabled="$d['motivoAvvio'] !== null || count($selezionati) === 0"
                    :tooltip="$d['motivoAvvio'] ?? (count($selezionati) === 0 ? 'Spunta almeno un discente.' : null)" wire:click="mountAction('avviaSelezionati')">
                    Avvia per i selezionati ({{ count($selezionati) }})
                </x-filament::button>
            </div>
        @else
            <p style="margin:0 0 10px; font-size:13px;">Solo il direttore del corso avvia il test.</p>
        @endif
        @if($d['motivoPubblica'] === null && $nInviati > 0)
                {{-- Pubblicazione dei risultati: per discente, come l'avvio; anche a esame chiuso --}}
                <div style="border:1px solid {{ $bordo }}; border-radius:8px; padding:8px 12px; margin-bottom:10px; font-size:13px;">
                    <strong>Risultati ai discenti</strong> · pubblicati a {{ $nPubblicati }} su {{ $nInviati }} che hanno inviato.
                    Chi riceve la pubblicazione vede nella app punteggio, domande sbagliate e risposte corrette; chi le vede può passarle a chi deve ancora svolgere il test.
                    <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:8px;">
                        <x-filament::button size="sm" color="success" type="button" :disabled="$d['motivoPubblica'] !== null || $nPubblicati === $nInviati"
                            :tooltip="$d['motivoPubblica'] ?? ($nPubblicati === $nInviati ? 'Già pubblicati a tutti quelli che hanno inviato.' : null)" wire:click="mountAction('pubblicaTutti')">Pubblica per tutti</x-filament::button>
                        <x-filament::button size="sm" color="success" type="button" :disabled="$d['motivoPubblica'] !== null || count($selezionatiPubblica) === 0"
                            :tooltip="$d['motivoPubblica'] ?? (count($selezionatiPubblica) === 0 ? 'Spunta almeno un discente con il test inviato.' : null)" wire:click="mountAction('pubblicaSelezionati')">
                            Pubblica per i selezionati ({{ count($selezionatiPubblica) }})
                        </x-filament::button>
                        @if($nPubblicati > 0)
                            <x-filament::button size="sm" color="danger" type="button" :disabled="$d['motivoPubblica'] !== null" :tooltip="$d['motivoPubblica']" wire:click="mountAction('ritiraTutti')">Ritira per tutti</x-filament::button>
                        @endif
                    </div>
                </div>
        @endif

        @forelse($d['righe'] as $r)
            @php
                $nome = $r['socio']->cognome . ' ' . $r['socio']->nome;
                $st = $r['stato'];
                $avviabile = in_array($st, ['non_avviato', 'disattivato'], true);
                $argomenti = \Illuminate\Support\Js::from(['discente_id' => $r['socio']->id, 'discente_nome' => $nome]);
            @endphp
            <div style="border-top:1px solid {{ $bordo }}; padding:8px 0;">
                <div style="display:flex; flex-wrap:wrap; gap:6px 14px; align-items:center; justify-content:space-between;">
                    <div style="display:flex; gap:8px; align-items:center; flex:1 1 220px;">
                        @if($d['eDirettore'] && $avviabile)
                            <input type="checkbox" value="{{ $r['socio']->id }}" wire:model.live="selezionati" aria-label="Seleziona {{ $nome }}">
                        @elseif($st === 'inviato' && $r['pubblicato'] === null && $d['motivoPubblica'] === null)
                            <input type="checkbox" value="{{ $r['socio']->id }}" wire:model.live="selezionatiPubblica" aria-label="Seleziona {{ $nome }} per la pubblicazione">
                        @endif
                        <span style="font-weight:600; overflow-wrap:anywhere;">{{ $nome }}</span>
                    </div>
                    <div style="font-size:13px; text-align:right;">
                        {{ $statoRiga[$st] }}
                        @if($r['modalita'] === 'cartaceo') · <strong>cartaceo</strong>@endif
                        @if($st === 'in_compilazione' || ($st === 'disattivato' && $r['date'] > 0)) · {{ $r['date'] }} su {{ $r['totale'] }} risposte salvate @endif
                        @if($r['punteggio']) · <strong style="font-size:15px;">{{ $r['punteggio'] }}</strong> @endif
                        @if($r['pubblicato']) · <span title="Pubblicati il {{ $r['pubblicato']->format('d/m/Y H:i') }}">risultati pubblicati</span> @endif
                    </div>
                </div>
                @if($r['modalita'] === 'cartaceo' && $r['motivo'])
                    <div style="font-size:12px; margin-top:2px;">Motivo: <span style="white-space:pre-line; overflow-wrap:anywhere;">{{ $r['motivo'] }}</span>
                        @if($r['consegna']?->eInviata()) — inserito da {{ $r['consegna']->inseritoDa?->nome_completo ?? 'il direttore' }} il {{ $r['consegna']->consegnato_il->format('d/m/Y H:i') }} @endif
                    </div>
                @endif

                <div style="display:flex; flex-wrap:wrap; gap:6px; margin-top:6px;">
                    @if($d['eDirettore'] && $st !== 'inviato')
                        @if($avviabile)
                            <x-filament::button size="xs" color="brand_pink" type="button" :disabled="$d['motivoAvvio'] !== null" :tooltip="$d['motivoAvvio']" wire:click="mountAction('avviaUno', {{ $argomenti }})">Avvia</x-filament::button>
                            <x-filament::button size="xs" color="info" type="button" :disabled="$d['motivoAvvio'] !== null" :tooltip="$d['motivoAvvio'] ?? 'Il discente farà il test su carta: lo inserisci tu.'" wire:click="mountAction('cartaceo', {{ $argomenti }})">Fa il test su carta…</x-filament::button>
                        @else
                            @if($r['modalita'] === 'cartaceo')
                                <x-filament::button size="xs" color="info" tag="a" :href="\App\Filament\Resources\EsameResource::getUrl('test-cartaceo', ['record' => $esame, 'discente' => $r['socio']->id])">Inserisci il test cartaceo</x-filament::button>
                            @endif
                            <x-filament::button size="xs" color="brand_pink" type="button" :disabled="$d['motivoGestione'] !== null" :tooltip="$d['motivoGestione']" wire:click="mountAction('disattiva', {{ $argomenti }})">Disattiva</x-filament::button>
                        @endif
                    @endif
                    @if($d['puoScaricare'] && count($d['domande']) > 0)
                        <x-filament::button size="xs" color="gray" tag="a" target="_blank" :href="route('esami.test-cartaceo-pdf', [$esame, $r['socio']])">Scarica il PDF da stampare</x-filament::button>
                    @endif
                    @if($st === 'inviato')
                        <x-filament::button size="xs" color="info" type="button" wire:click="apriDettaglio({{ $r['socio']->id }})">{{ isset($dettagli[$r['socio']->id]) ? 'Nascondi risposte' : 'Vedi risposte' }}</x-filament::button>
                        @if($d['motivoPubblica'] === null)
                            @if($r['pubblicato'] === null)
                                <x-filament::button size="xs" color="success" type="button" :disabled="$d['motivoPubblica'] !== null" :tooltip="$d['motivoPubblica']" wire:click="mountAction('pubblicaUno', {{ $argomenti }})">Pubblica i risultati</x-filament::button>
                            @else
                                <x-filament::button size="xs" color="danger" type="button" :disabled="$d['motivoPubblica'] !== null" :tooltip="$d['motivoPubblica']" wire:click="mountAction('ritiraUno', {{ $argomenti }})">Ritira i risultati</x-filament::button>
                            @endif
                        @endif
                    @endif
                </div>

                @if(isset($dettagli[$r['socio']->id]))
                    <div style="margin-top:8px; font-size:13px;">
                        @foreach($d['domande'] as $i => $q)
                            @php($data = $dettagli[$r['socio']->id][$q['id']] ?? null)
                            @if($q['annullata'])
                                <div style="padding:2px 0;">{{ $i + 1 }}. <em>domanda annullata</em></div>
                                @continue
                            @endif
                            <div style="padding:2px 0; {{ $data !== null && $data === $q['corretta'] ? 'background:' . $verde . ';' : '' }}">
                                {{ $i + 1 }}. @if($data === null) <em>non data</em> @else {{ $lettere[$data] ?? $data + 1 }}) {{ $q['opzioni'][$data] ?? '' }} @if($data === $q['corretta']) ✓ @else ✗ (corretta: {{ $lettere[$q['corretta']] }}) @endif @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <p style="margin:0;">Nessun discente ammesso.</p>
        @endforelse
    </x-filament::section>
</x-filament-panels::page>
