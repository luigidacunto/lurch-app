<x-filament-panels::page>
    @php
        $esame  = $this->record;
        $d      = $this->dati();
        $bordo  = 'rgba(128,128,128,.35)';
        $grigio = 'rgba(128,128,128,.10)';
        $verde  = 'rgba(34,197,94,.25)';
        $lettere = ['A', 'B', 'C', 'D'];
        $etichettaStato = ['bozza' => 'In bozza', 'avviato' => 'Avviato', 'con_consegne' => 'Con test inviati'][$d['stato']];
        $nAttivi = collect($d['righe'])->whereIn('stato', ['avviato', 'in_compilazione', 'inviato'])->count();
        $nInviati = collect($d['righe'])->where('stato', 'inviato')->count();
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
        <span style="font-size:14px;">{{ count($d['domande']) }} domande · punteggio massimo <strong>{{ \App\Esami\Formato::unDecimale($d['massimo']) }}</strong></span>
        <x-filament::button tag="a" size="sm" color="info" :href="\App\Filament\Resources\EsameResource::getUrl('domande-test', ['record' => $esame])">Domande e punteggio</x-filament::button>
    </div>

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
            <p style="margin:0 0 10px; font-size:13px;">Solo il direttore del corso avvia il test; tu lo leggi in sola lettura.</p>
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
                        @endif
                        <span style="font-weight:600; overflow-wrap:anywhere;">{{ $nome }}</span>
                    </div>
                    <div style="font-size:13px; text-align:right;">
                        {{ $statoRiga[$st] }}
                        @if($r['modalita'] === 'cartaceo') · <strong>cartaceo</strong>@endif
                        @if($st === 'in_compilazione' || ($st === 'disattivato' && $r['date'] > 0)) · {{ $r['date'] }} su {{ $r['totale'] }} risposte salvate @endif
                        @if($r['punteggio']) · <strong style="font-size:15px;">{{ $r['punteggio'] }}</strong> @endif
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
                    @endif
                </div>

                @if(isset($dettagli[$r['socio']->id]))
                    <div style="margin-top:8px; font-size:13px;">
                        @foreach($d['domande'] as $i => $q)
                            @php($data = $dettagli[$r['socio']->id][$q['id']] ?? null)
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
