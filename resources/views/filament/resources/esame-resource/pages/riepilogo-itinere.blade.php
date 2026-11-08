<x-filament-panels::page>
    @php
        $esame      = $this->record;
        $r          = $this->riepilogo();
        $isDirettore = $esame->isDirettore(auth()->user());
        $motivoOff  = $this->motivoNonDecidibile();
        $bordo      = 'rgba(128,128,128,.35)';
        $grigio     = 'rgba(128,128,128,.10)';
        $ambra      = 'rgba(234,179,8,.20)';
        $rossoTenue = 'rgba(239,68,68,.20)';
        $pillola    = 'display:inline-block; padding:1px 7px; border-radius:6px; color:#111827;';
        $lettere    = ['rosso' => ['R', 'rgba(239,68,68,.30)'], 'giallo' => ['G', 'rgba(234,179,8,.30)'], 'verde' => ['V', 'rgba(34,197,94,.30)']];
    @endphp

    @if(! $esame->modello_snapshot)
        <x-filament::section>
            <p style="margin:0;">L'esame è ancora in bozza: il riepilogo è disponibile dopo «Attiva esame».</p>
        </x-filament::section>
    @else
        {{-- Le due famiglie di schede, sempre distinte --}}
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:12px;">
            <div style="border:1px solid {{ $bordo }}; background:{{ $grigio }}; border-radius:8px; padding:10px 14px;">
                <strong>Schede personali dei docenti</strong>
                <div style="font-size:13px;">Appunti di ciascun docente durante il percorso: servono al confronto, <strong>non entrano nel calcolo</strong>.</div>
            </div>
            <div style="border:1px solid {{ $bordo }}; border-left:5px solid rgb(var(--primary-500, 59 130 246)); border-radius:8px; padding:10px 14px;">
                <strong>Scheda finale d'area</strong>
                <div style="font-size:13px;">Punteggio deciso dal direttore con la commissione: <strong>è l'unico che entra nel calcolo</strong>.
                    @unless($isDirettore) Tu la leggi in sola lettura. @endunless
                </div>
            </div>
        </div>

        <div x-data="{ q: '' }" style="display:flex; flex-direction:column; gap:10px;">
            <input type="search" x-model="q" placeholder="Cerca per nome o cognome…"
                   style="max-width:360px; padding:7px 10px; border:1px solid {{ $bordo }}; border-radius:8px; background:transparent; color:inherit;">

            @forelse($r['discenti'] as $d)
                @php $nome = $d['socio']->cognome . ' ' . $d['socio']->nome; @endphp
                <div x-data="{ aperto: false }"
                     x-show="q.trim() === '' || q.toLowerCase().split(/\s+/).every(p => @js(mb_strtolower($nome)).includes(p))"
                     style="border:1px solid {{ $bordo }}; border-radius:10px;">
                    <button type="button" @click="aperto = !aperto"
                            style="width:100%; display:flex; flex-wrap:wrap; gap:8px 16px; align-items:center; justify-content:space-between; padding:10px 14px; background:transparent; color:inherit; border:0; cursor:pointer; text-align:left;">
                        <span style="font-weight:600;">
                            <span x-text="aperto ? '▾' : '▸'"></span> {{ $nome }}
                            @if($d['non_ammesso'])
                                <span title="{{ $d['motivo_non_ammesso'] }}" style="margin-left:6px; font-size:11px; padding:2px 7px; border-radius:6px; background:rgba(239,68,68,.20);">Non ammesso</span>
                            @endif
                            {{-- Sintesi dei laboratori già svolti: schede inviate / docenti attesi, Δ in attenzione --}}
                            @foreach($d['stato_lab'] as $sl)
                                <span title="Laboratorio {{ $sl['numero'] }}: schede inviate su docenti che valutano il discente"
                                      style="margin-left:6px; font-size:11px; font-weight:600; padding:2px 7px; border-radius:6px; background:{{ $sl['attesi'] > 0 && $sl['inviate'] >= $sl['attesi'] ? 'rgba(34,197,94,.20)' : $grigio }};">Lab {{ $sl['numero'] }} {{ $sl['inviate'] }}/{{ $sl['attesi'] }}</span>
                                @if($sl['scarto_alto'])
                                    <span title="Scarto tra docenti ≥ {{ \App\Esami\RiepilogoItinere::SCARTO_ALTO_PERC }}% del massimo"
                                          style="margin-left:4px; font-size:11px; font-weight:800; padding:2px 7px; border-radius:6px; background:rgb(185,28,28); color:#fff;">Δ Lab {{ $sl['numero'] }}: {{ $sl['scarto'] }}</span>
                                @endif
                            @endforeach
                        </span>
                        <span style="font-size:13px;">
                            Aree decise: <strong>{{ $d['decise'] }}/{{ $d['n_aree'] }}</strong> ·
                            @if($d['totale'] !== null)
                                Totale itinere finale: <strong>{{ $d['totale'] }} su {{ $d['massimo'] }}</strong>
                            @elseif($d['non_ammesso'])
                                Nessuna scheda finale (non ammesso)
                            @else
                                Totale itinere finale: incompleto
                            @endif
                        </span>
                    </button>

                    <div x-show="aperto" x-cloak style="padding:4px 14px 14px; display:flex; flex-direction:column; gap:18px;">
                        @if($d['annotazioni'])
                            <div style="display:flex; flex-direction:column; gap:2px; font-size:13px;">
                                @foreach($d['annotazioni'] as $n)
                                    <div style="display:flex; align-items:center; gap:4px;">
                                        {{ $n['docente'] }} ha inserito una nota sulla scheda itinere
                                        @include('filament.resources.esame-resource.partials.nota-docente', ['docente' => $n['docente'], 'testo' => $n['testo'], 'titolo' => 'Scheda itinere · ' . $nome])
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        @foreach($r['aree'] as $area)
                            @php $a = $d['aree'][$area['codice']]; @endphp
                            <section style="border-top:1px solid {{ $bordo }}; padding-top:12px;">
                                <h3 style="margin:0 0 8px; font-size:16px; font-weight:700;">{{ $area['nome'] }}</h3>

                                {{-- A. Schede personali (non entrano nel calcolo) --}}
                                <div style="border:1px solid {{ $bordo }}; background:{{ $grigio }}; border-radius:8px; padding:10px;">
                                    <div style="font-size:12px; font-weight:600; margin-bottom:6px;">Schede personali dei docenti — appunti, non entrano nel calcolo</div>
                                    @if($d['docenti'] === [])
                                        <p style="margin:0; font-size:13px;">Nessun docente ha compilato la scheda di questo discente.</p>
                                    @else
                                        <div style="overflow-x:auto;">
                                            <table style="border-collapse:collapse; font-size:13px; width:100%;">
                                                <thead>
                                                    <tr style="text-align:left;">
                                                        <th style="padding:4px 8px;">Voce</th>
                                                        @foreach($d['docenti'] as $docNome)
                                                            <th style="padding:4px 8px; text-align:center; white-space:nowrap;">{{ $docNome }}</th>
                                                        @endforeach
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($area['voci'] as $voce)
                                                        <tr style="border-top:1px solid {{ $bordo }};">
                                                            <td style="padding:4px 8px;">{{ $voce['testo'] }}</td>
                                                            @foreach($d['docenti'] as $docId => $docNome)
                                                                <td style="padding:4px 8px; text-align:center;">{{ $a['voti'][$docId][$voce['codice']] ?? '—' }}</td>
                                                            @endforeach
                                                        </tr>
                                                    @endforeach
                                                    <tr style="border-top:2px solid {{ $bordo }}; font-weight:600;">
                                                        <td style="padding:4px 8px;">Media del docente</td>
                                                        @foreach($d['docenti'] as $docId => $docNome)
                                                            <td style="padding:4px 8px; text-align:center;">{{ $a['medie_docenti'][$docId]['media'] ?? '—' }}</td>
                                                        @endforeach
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif

                                    @if($a['forzato'])
                                        <p style="margin:8px 0 0; padding:6px 10px; border-radius:6px; background:{{ $ambra }}; font-size:13px;">
                                            ⚠ Nessun docente ha dato voti in quest'area. <strong>Valore forzato (LAP × {{ $esame->modello_snapshot['itinere']['scala']['max'] }}): {{ $a['proposta'] }}</strong>.
                                            Entra nel calcolo solo se il direttore lo conferma come punteggio finale.
                                        </p>
                                    @else
                                        <p style="margin:8px 0 0; font-size:13px;">
                                            Media delle medie dei docenti: <strong>{{ $a['media_delle_medie'] }}</strong> ·
                                            Media di tutti i voti: <strong>{{ $a['media_tutti_i_voti'] }}</strong>
                                        </p>
                                    @endif
                                </div>

                                {{-- Crescita: andamento dei laboratori --}}
                                @if($area['specchio_laboratori'])
                                    <div style="margin-top:10px; border:1px solid {{ $bordo }}; background:{{ $grigio }}; border-radius:8px; padding:10px;">
                                        <div style="font-size:12px; font-weight:600; margin-bottom:6px;">Andamento nei laboratori — schede semaforo inviate, media tra i docenti (appunti, non entrano nel calcolo)</div>
                                        @if($d['laboratori'] === [])
                                            <p style="margin:0; font-size:13px;">Nessun laboratorio.</p>
                                        @else
                                            <table style="border-collapse:collapse; font-size:13px; width:100%;">
                                                <thead>
                                                    <tr style="text-align:left;">
                                                        <th style="padding:4px 8px;">Laboratorio</th>
                                                        <th style="padding:4px 8px;">Media tra docenti</th>
                                                        <th style="padding:4px 8px;">Scarto (min–max)</th>
                                                        <th style="padding:4px 8px;">Per docente</th>
                                                        <th></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($d['laboratori'] as $lab)
                                                        @php $chiave = $d['socio']->id . '-' . $lab['id']; @endphp
                                                        <tr style="border-top:1px solid {{ $bordo }};">
                                                            <td style="padding:4px 8px;">Lab {{ $lab['numero'] }}{{ $lab['data'] ? ' · ' . $lab['data']->format('d/m/Y') : '' }}</td>
                                                            @if($lab['media'] === null)
                                                                <td colspan="3" style="padding:4px 8px;">Nessuna scheda inviata</td>
                                                            @else
                                                                <td style="padding:4px 8px;"><span style="{{ $pillola }} background:{{ $lab['colore_media'] }};"><strong>{{ $lab['media'] }}</strong></span> su {{ $lab['massimo_scheda'] }}</td>
                                                                <td style="padding:4px 8px;">{{ $lab['minimo'] }}–{{ $lab['massimo'] }}
                                                                    <span @if($lab['scarto_alto']) style="display:inline-block; padding:1px 7px; border-radius:6px; background:rgb(185,28,28); color:#fff; font-weight:800;" title="Scarto ≥ {{ \App\Esami\RiepilogoItinere::SCARTO_ALTO_PERC }}% del massimo" @endif>(Δ {{ $lab['scarto'] }})</span>
                                                                </td>
                                                                <td style="padding:4px 8px;">
                                                                    <div style="display:flex; flex-wrap:wrap; gap:4px 8px; align-items:center;">
                                                                        @foreach($lab['docenti'] as $ld)
                                                                            <span style="display:inline-flex; align-items:center; gap:2px;">
                                                                                {{ $ld['docente'] }} <span style="{{ $pillola }} background:{{ $ld['colore'] }};">{{ $ld['totale'] }}</span>
                                                                                @if($ld['nota'])
                                                                                    @include('filament.resources.esame-resource.partials.nota-docente', ['docente' => $ld['docente'], 'testo' => $ld['nota'], 'titolo' => 'Laboratorio ' . $lab['numero'] . ' · ' . $nome])
                                                                                @endif
                                                                            </span>
                                                                        @endforeach
                                                                    </div>
                                                                </td>
                                                            @endif
                                                            <td style="padding:4px 8px; text-align:right; white-space:nowrap;">
                                                                @if($lab['media'] !== null)
                                                                    <x-filament::button size="xs" color="info" type="button"
                                                                        wire:click="apriDettaglioLaboratorio({{ $d['socio']->id }}, {{ $lab['id'] }})">
                                                                        {{ isset($dettagli[$chiave]) ? 'Nascondi voci' : 'Vedi voci' }}
                                                                    </x-filament::button>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                        @if(isset($dettagli[$chiave]))
                                                            @php $m = $dettagli[$chiave]; @endphp
                                                            <tr>
                                                                <td colspan="5" style="padding:6px 8px 12px;">
                                                                    <div style="overflow-x:auto;">
                                                                        <table style="border-collapse:collapse; font-size:12px; width:100%;">
                                                                            <thead>
                                                                                <tr style="text-align:left;">
                                                                                    <th style="padding:3px 6px;">Voce</th>
                                                                                    @foreach($m['docenti'] as $i => $docNome)
                                                                                        <th style="padding:3px 6px; text-align:center; white-space:nowrap;">
                                                                                            {{ $docNome }}
                                                                                            @if($m['note'][$i] ?? null)
                                                                                                @include('filament.resources.esame-resource.partials.nota-docente', ['docente' => $docNome, 'testo' => $m['note'][$i], 'titolo' => 'Laboratorio ' . $lab['numero'] . ' · ' . $nome])
                                                                                            @endif
                                                                                        </th>
                                                                                    @endforeach
                                                                                </tr>
                                                                            </thead>
                                                                            <tbody>
                                                                                @foreach($m['competenze'] as $comp)
                                                                                    <tr><td colspan="{{ count($m['docenti']) + 1 }}" style="padding:6px 6px 2px; font-weight:700;">{{ $comp['nome'] }}</td></tr>
                                                                                    @foreach($comp['voci'] as $voce)
                                                                                        <tr style="border-top:1px solid {{ $bordo }}; {{ $voce['salto'] ? 'background:' . $rossoTenue . ';' : ($voce['discordante'] ? 'background:' . $ambra . ';' : '') }}">
                                                                                            <td style="padding:3px 6px;">{{ $voce['discordante'] ? '≠ ' : '' }}{{ $voce['testo'] }}</td>
                                                                                            @foreach($voce['valori'] as $val)
                                                                                                <td style="padding:3px 6px; text-align:center; font-weight:700; background:{{ $lettere[$val][1] ?? 'transparent' }};">{{ $lettere[$val][0] ?? '—' }}</td>
                                                                                            @endforeach
                                                                                        </tr>
                                                                                    @endforeach
                                                                                @endforeach
                                                                                <tr style="border-top:2px solid {{ $bordo }}; font-weight:700;">
                                                                                    <td style="padding:4px 6px;">Totale (su {{ $m['massimo'] }})</td>
                                                                                    @foreach($m['totali'] as $t)
                                                                                        <td style="padding:4px 6px; text-align:center;">@if($t)<span style="{{ $pillola }} background:{{ $t['colore'] }};">{{ $t['totale'] }}</span>@else — @endif</td>
                                                                                    @endforeach
                                                                                </tr>
                                                                            </tbody>
                                                                        </table>
                                                                    </div>
                                                                    <div style="font-size:11px; margin-top:4px;">R = rosso · G = giallo · V = verde · «≠» e sfondo ambra = voce su cui i docenti non concordano · sfondo rosso = salto da rosso a verde tra due docenti.</div>
                                                                </td>
                                                            </tr>
                                                        @endif
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        @endif
                                    </div>
                                @endif

                                {{-- B. Scheda finale d'area (entra nel calcolo) --}}
                                <div style="margin-top:10px; border:1px solid {{ $bordo }}; border-left:5px solid rgb(var(--primary-500, 59 130 246)); border-radius:8px; padding:10px;">
                                    <div style="font-size:12px; font-weight:600; margin-bottom:6px;">Scheda finale d'area — entra nel calcolo</div>
                                    @if($area['specchio_laboratori'])
                                        <div style="font-size:12px; font-style:italic; margin-bottom:6px;">{{ \App\Esami\RiepilogoItinere::NOTA_AREA_SPECCHIO }}</div>
                                    @endif
                                    <div style="display:flex; flex-wrap:wrap; gap:10px 20px; align-items:center; justify-content:space-between;">
                                        <div style="font-size:14px;">
                                            @if($a['finale'])
                                                Punteggio finale: <strong style="font-size:18px;">{{ $a['finale']['punteggio'] }}</strong>
                                                <div style="font-size:12px;">
                                                    Deciso da {{ $a['finale']['autore'] ?? 'direttore' }} il {{ $a['finale']['data']?->format('d/m/Y H:i') }}
                                                    @if($a['forzato']) · <span style="padding:1px 6px; border-radius:5px; background:{{ $ambra }};">valore forzato (nessun voto dei docenti)</span> @endif
                                                </div>
                                                @if($a['finale']['motivazione'])
                                                    <div style="font-size:13px; margin-top:4px; white-space:pre-line;">Motivazione: {{ $a['finale']['motivazione'] }}</div>
                                                @endif
                                            @else
                                                <span>Non ancora deciso.</span>
                                                <span style="font-size:12px;">Proposta: {{ $a['proposta'] }}</span>
                                            @endif
                                        </div>
                                        @if($isDirettore)
                                            @php
                                                $off = $motivoOff ?? ($d['non_ammesso'] ? 'Il discente è non ammesso: non ha scheda finale.' : null);
                                            @endphp
                                            <x-filament::button size="sm" color="info" type="button"
                                                :disabled="$off !== null"
                                                :tooltip="$off"
                                                wire:click="mountAction('decidi', {{ \Illuminate\Support\Js::from([
                                                    'discente_id'   => $d['socio']->id,
                                                    'discente_nome' => $nome,
                                                    'area'          => $area['codice'],
                                                    'area_nome'     => $area['nome'],
                                                    'proposta'      => $a['proposta_input'],
                                                    'punteggio'     => $a['finale']['punteggio_input'] ?? null,
                                                    'motivazione'   => $a['finale']['motivazione'] ?? null,
                                                ]) }})">
                                                {{ $a['finale'] ? 'Modifica punteggio' : 'Decidi punteggio' }}
                                            </x-filament::button>
                                        @endif
                                    </div>
                                </div>
                            </section>
                        @endforeach
                    </div>
                </div>
            @empty
                <x-filament::section><p style="margin:0;">Nessun discente iscritto al corso.</p></x-filament::section>
            @endforelse
        </div>
    @endif
</x-filament-panels::page>
