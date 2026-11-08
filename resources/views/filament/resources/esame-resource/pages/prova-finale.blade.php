<x-filament-panels::page>
    @php
        $esame      = $this->record;
        $r          = $this->riepilogo();
        $puoInserire = $esame->isDirettore(auth()->user());
        $motivoOff  = $this->motivoNonInseribile();
        $bordo      = 'rgba(128,128,128,.35)';
        $grigio     = 'rgba(128,128,128,.10)';
        $ambra      = 'rgba(234,179,8,.20)';
        $nCompleti  = collect($r['completezza'])->where('completa', true)->count();
        $nTotale    = count($r['completezza']);
        $sez        = $this->sezione(); // 'colloquio' (autovalutazione) | 'pratica'
        $nomeProva  = $sez === 'colloquio' ? 'autovalutazione (colloquio)' : 'prova pratica';
    @endphp

    @if(! $esame->modello_snapshot)
        <x-filament::section>
            <p style="margin:0;">L'esame è ancora in bozza: questa prova è disponibile dopo «Attiva esame».</p>
        </x-filament::section>
    @else
        {{-- Le due famiglie di schede, sempre distinte --}}
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:12px;">
            <div style="border:1px solid {{ $bordo }}; background:{{ $grigio }}; border-radius:8px; padding:10px 14px;">
                <strong>Schede personali dei commissari</strong>
                <div style="font-size:13px;">Appunti di direttore e docenti di commissione su{{ $sez === 'colloquio' ? "ll'" : 'lla ' }}{{ $nomeProva }}: servono al confronto, <strong>non entrano nel calcolo</strong>.
                    @if($r['aperta']) La prova finale è <strong>aperta</strong>: i commissari possono compilare e modificare dalla PWA.
                    @else La prova finale <strong>non è aperta</strong>: le schede si leggono ma non si compilano né si modificano (Gestione → Azioni → «Apri la prova finale»).
                    @endif
                </div>
            </div>
            <div style="border:1px solid {{ $bordo }}; border-left:5px solid rgb(var(--primary-500, 59 130 246)); border-radius:8px; padding:10px 14px;">
                <strong>Schede finali</strong>
                <div style="font-size:13px;">Scheda finale ({{ $nomeProva }}) inserita dal direttore con la commissione: <strong>sono gli unici che entrano nel calcolo</strong>.
                    @unless($puoInserire) Tu le leggi in sola lettura. @endunless
                </div>
            </div>
        </div>

        <div style="font-size:14px;">
            Discenti con tutte le schede finali (itinere, colloquio, prova pratica): <strong>{{ $nCompleti }} su {{ $nTotale }}</strong>
            <span style="font-size:12px;">— controllo di completezza, nessun blocco: la chiusura dell'esame è un passo successivo.</span>
        </div>

        <div x-data="{ q: '' }" style="display:flex; flex-direction:column; gap:10px;">
            <input type="search" x-model="q" placeholder="Cerca per nome o cognome…"
                   style="max-width:360px; padding:7px 10px; border:1px solid {{ $bordo }}; border-radius:8px; background:transparent; color:inherit;">

            @forelse($r['discenti'] as $d)
                @php
                    $nome = $d['socio']->cognome . ' ' . $d['socio']->nome;
                    $c = $d['completezza'];
                    $mm = $c['personali_mancanti'] ?? null;
                @endphp
                <div x-data="{ aperto: false }"
                     x-show="q.trim() === '' || q.toLowerCase().split(/\s+/).every(p => @js(mb_strtolower($nome)).includes(p))"
                     style="border:1px solid {{ $bordo }}; border-radius:10px;">
                    <button type="button" @click="aperto = !aperto"
                            style="width:100%; display:flex; flex-wrap:wrap; gap:8px 16px; align-items:center; justify-content:space-between; padding:10px 14px; background:transparent; color:inherit; border:0; cursor:pointer; text-align:left;">
                        <span style="font-weight:600;"><span x-text="aperto ? '▾' : '▸'"></span> {{ $nome }}</span>
                        <span style="font-size:13px;">
                            @if($sez === 'colloquio')
                                Autovalutazione <strong>{{ $d['colloquio']['finale']['punteggio'] ?? 'manca' }}</strong>
                            @else
                                Prova pratica <strong>{{ ($d['prova_pratica']['finale']['totale'] ?? null) ? $d['prova_pratica']['finale']['totale'] . '/' . $r['massimo_pratica'] : 'manca' }}</strong>
                            @endif
                            @if($c['completa'])<span style="margin-left:6px; padding:1px 7px; border-radius:6px; background:rgba(34,197,94,.25);">Complete</span>@endif
                        </span>
                    </button>

                    <div x-show="aperto" x-cloak style="padding:4px 14px 14px; display:flex; flex-direction:column; gap:18px;">

                        {{-- ===== COLLOQUIO (autovalutazione) ===== --}}
                        @if($sez === 'colloquio')
                        <section style="border-top:1px solid {{ $bordo }}; padding-top:12px;">
                            <h3 style="margin:0 0 8px; font-size:16px; font-weight:700;">Autovalutazione (colloquio)</h3>

                            <div style="border:1px solid {{ $bordo }}; background:{{ $grigio }}; border-radius:8px; padding:10px;">
                                <div style="font-size:12px; font-weight:600; margin-bottom:6px;">Schede personali dei commissari — appunti, non entrano nel calcolo</div>
                                @forelse($d['colloquio']['personali'] as $p)
                                    <div style="padding:3px 0; font-size:13px;">
                                        <strong>{{ $p['membro'] }}</strong>: voto <strong>{{ $p['voto'] }}</strong>
                                        @if($p['impressione'])<div style="white-space:pre-line; overflow-wrap:anywhere; font-style:italic;">{{ $p['impressione'] }}</div>@endif
                                    </div>
                                @empty
                                    <p style="margin:0; font-size:13px;">Nessun commissario ha compilato la scheda di questo discente.</p>
                                @endforelse
                                @if($mm && $mm['colloquio'] > 0)
                                    <p style="margin:6px 0 0; font-size:12px;">⚠ Schede personali mancanti: {{ $mm['colloquio'] }} su {{ $mm['attese'] }} (solo segnalazione).</p>
                                @endif
                                @if($d['colloquio']['proposta'] !== null)
                                    <p style="margin:6px 0 0; font-size:13px;">Media dei voti (troncata a 1 decimale): <strong>{{ $d['colloquio']['proposta'] }}</strong> — proposta per la scheda finale.</p>
                                @endif
                            </div>

                            <div style="margin-top:10px; border:1px solid {{ $bordo }}; border-left:5px solid rgb(var(--primary-500, 59 130 246)); border-radius:8px; padding:10px;">
                                <div style="font-size:12px; font-weight:600; margin-bottom:6px;">Scheda finale del colloquio — entra nel calcolo</div>
                                <div style="display:flex; flex-wrap:wrap; gap:10px 20px; align-items:center; justify-content:space-between;">
                                    <div style="font-size:14px;">
                                        @if($d['colloquio']['finale'])
                                            Voto finale: <strong style="font-size:18px;">{{ $d['colloquio']['finale']['punteggio'] }}</strong> su {{ $esame->modello_snapshot['colloquio']['scala']['max'] }}
                                            <div style="font-size:12px;">Inserito da {{ $d['colloquio']['finale']['autore'] ?? 'direttore' }} il {{ $d['colloquio']['finale']['data']?->format('d/m/Y H:i') }}</div>
                                            @if($d['colloquio']['finale']['motivazione'])
                                                <div style="font-size:13px; margin-top:4px; white-space:pre-line;">Motivazione: {{ $d['colloquio']['finale']['motivazione'] }}</div>
                                            @endif
                                        @else
                                            <span>Non ancora inserito.</span>
                                        @endif
                                    </div>
                                    @if($puoInserire)
                                        <x-filament::button size="sm" color="info" type="button"
                                            :disabled="$motivoOff !== null"
                                            :tooltip="$motivoOff"
                                            wire:click="mountAction('colloquioFinale', {{ \Illuminate\Support\Js::from([
                                                'discente_id'   => $d['socio']->id,
                                                'discente_nome' => $nome,
                                                'proposta'      => $d['colloquio']['proposta_input'],
                                                'punteggio'     => $d['colloquio']['finale']['punteggio_input'] ?? null,
                                                'motivazione'   => $d['colloquio']['finale']['motivazione'] ?? null,
                                            ]) }})">
                                            {{ $d['colloquio']['finale'] ? 'Modifica colloquio finale' : 'Inserisci colloquio finale' }}
                                        </x-filament::button>
                                    @endif
                                </div>
                            </div>
                        </section>
                        @endif

                        {{-- ===== PROVA PRATICA ===== --}}
                        @if($sez === 'pratica')
                        <section style="border-top:1px solid {{ $bordo }}; padding-top:12px;">
                            <h3 style="margin:0 0 8px; font-size:16px; font-weight:700;">Prova pratica</h3>

                            <div style="border:1px solid {{ $bordo }}; background:{{ $grigio }}; border-radius:8px; padding:10px;">
                                <div style="font-size:12px; font-weight:600; margin-bottom:6px;">Schede personali dei commissari — appunti, non entrano nel calcolo</div>
                                @forelse($d['prova_pratica']['personali'] as $p)
                                    <div style="padding:3px 0; font-size:13px;">
                                        <strong>{{ $p['membro'] }}</strong>:
                                        @if($p['inviata']) inviata{{ $p['totale'] !== null ? ' · ' . $p['totale'] . ' su ' . $r['massimo_pratica'] : '' }}
                                        @else bozza ({{ $p['voci'] }} voci), non conta nella proposta @endif
                                    </div>
                                @empty
                                    <p style="margin:0; font-size:13px;">Nessun commissario ha compilato la scheda di questo discente.</p>
                                @endforelse
                                @if($mm && $mm['prova_pratica'] > 0)
                                    <p style="margin:6px 0 0; font-size:12px;">⚠ Schede personali inviate mancanti: {{ $mm['prova_pratica'] }} su {{ $mm['attese'] }} (solo segnalazione).</p>
                                @endif
                                @if($d['prova_pratica']['proposta_totale'] !== null)
                                    <p style="margin:6px 0 0; font-size:13px;">Proposta voce per voce: <strong>{{ $d['prova_pratica']['proposta_totale'] }} su {{ $r['massimo_pratica'] }}</strong></p>
                                @endif
                                @if($d['prova_pratica']['n_inviate'] > 0)
                                    <div style="margin-top:6px;">
                                        <x-filament::button size="xs" color="info" type="button" wire:click="apriDettaglioProvaPratica({{ $d['socio']->id }})">
                                            {{ isset($dettagli[$d['socio']->id]) ? 'Nascondi voci' : 'Vedi voci' }}
                                        </x-filament::button>
                                    </div>
                                @endif
                                @if(isset($dettagli[$d['socio']->id]))
                                    @php
                                        $m = $dettagli[$d['socio']->id];
                                        $lettere = ['rosso' => ['R', 'rgba(239,68,68,.30)'], 'giallo' => ['G', 'rgba(234,179,8,.30)'], 'verde' => ['V', 'rgba(34,197,94,.30)']];
                                    @endphp
                                    <div style="overflow-x:auto; margin-top:8px;">
                                        <table style="border-collapse:collapse; font-size:12px; width:100%;">
                                            <thead>
                                                <tr style="text-align:left;">
                                                    <th style="padding:3px 6px;">Voce</th>
                                                    @foreach($m['commissari'] as $cm)
                                                        <th style="padding:3px 6px; text-align:center; white-space:nowrap;">{{ $cm['membro'] }}</th>
                                                    @endforeach
                                                    <th style="padding:3px 6px; text-align:center;">Proposta</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($m['competenze'] as $comp)
                                                    <tr><td colspan="{{ count($m['commissari']) + 2 }}" style="padding:6px 6px 2px; font-weight:700;">{{ $comp['nome'] }}</td></tr>
                                                    @foreach($comp['voci'] as $voce)
                                                        <tr style="border-top:1px solid {{ $bordo }}; {{ $voce['discordante'] ? 'background:' . $ambra . ';' : '' }}">
                                                            <td style="padding:3px 6px;">{{ $voce['discordante'] ? '≠ ' : '' }}{{ $voce['testo'] }}</td>
                                                            @foreach($voce['valori'] as $val)
                                                                <td style="padding:3px 6px; text-align:center; font-weight:700; background:{{ $lettere[$val][1] ?? 'transparent' }};">{{ $lettere[$val][0] ?? '—' }}</td>
                                                            @endforeach
                                                            <td style="padding:3px 6px; text-align:center; font-weight:700; background:{{ $lettere[$voce['proposta']][1] ?? 'transparent' }};">{{ $lettere[$voce['proposta']][0] ?? '—' }}</td>
                                                        </tr>
                                                    @endforeach
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <div style="font-size:11px; margin-top:4px;">R = rosso · G = giallo · V = verde · «≠» e sfondo ambra = voce su cui i commissari non concordano. In pareggio la proposta è il valore più basso.</div>
                                @endif
                            </div>

                            <div style="margin-top:10px; border:1px solid {{ $bordo }}; border-left:5px solid rgb(var(--primary-500, 59 130 246)); border-radius:8px; padding:10px;">
                                <div style="font-size:12px; font-weight:600; margin-bottom:6px;">Scheda finale della prova pratica — entra nel calcolo</div>
                                <div style="display:flex; flex-wrap:wrap; gap:10px 20px; align-items:center; justify-content:space-between;">
                                    <div style="font-size:14px;">
                                        @if($d['prova_pratica']['finale'])
                                            Totale: <strong style="font-size:18px;">{{ $d['prova_pratica']['finale']['totale'] ?? '—' }}</strong> su {{ $d['prova_pratica']['finale']['massimo'] }}
                                            <div style="font-size:12px;">Inserita da {{ $d['prova_pratica']['finale']['autore'] ?? 'direttore' }} il {{ $d['prova_pratica']['finale']['data']?->format('d/m/Y H:i') }}</div>
                                        @else
                                            <span>Non ancora inserita.</span>
                                        @endif
                                    </div>
                                    @if($puoInserire)
                                        <x-filament::button size="sm" color="info" tag="a"
                                            :href="$motivoOff === null ? \App\Filament\Resources\EsameResource::getUrl('prova-pratica-finale', ['record' => $esame, 'discente' => $d['socio']->id]) : null"
                                            :disabled="$motivoOff !== null"
                                            :tooltip="$motivoOff">
                                            {{ $d['prova_pratica']['finale'] ? 'Modifica prova pratica finale' : 'Inserisci prova pratica finale' }}
                                        </x-filament::button>
                                    @endif
                                </div>
                            </div>
                        </section>
                        @endif
                    </div>
                </div>
            @empty
                <x-filament::section><p style="margin:0;">Nessun discente ammesso (i ritirati e i non ammessi non compaiono).</p></x-filament::section>
            @endforelse
        </div>
    @endif
</x-filament-panels::page>
