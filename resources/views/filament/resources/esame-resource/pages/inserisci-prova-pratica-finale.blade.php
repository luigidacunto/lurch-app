<x-filament-panels::page>
    @php
        $d       = $this->dati();
        $s       = $d['struttura'];
        $conf    = $d['confronto'];
        $bordo   = 'rgba(128,128,128,.35)';
        $grigio  = 'rgba(128,128,128,.10)';
        $ambra   = 'rgba(234,179,8,.20)';
        $colori  = ['rosso' => 'rgba(239,68,68,.35)', 'giallo' => 'rgba(234,179,8,.40)', 'verde' => 'rgba(34,197,94,.35)'];
        $lettere = ['rosso' => 'R', 'giallo' => 'G', 'verde' => 'V'];
        $nVoci   = count($s['codici']);
    @endphp

    <div style="border:1px solid {{ $bordo }}; border-left:5px solid rgb(var(--primary-500, 59 130 246)); border-radius:8px; padding:10px 14px;">
        <strong>Scheda finale della prova pratica — entra nel calcolo</strong>
        <div style="font-size:13px;">
            Tutte le {{ $nVoci }} voci sono obbligatorie.
            @if($d['esiste']) Stai modificando la scheda finale già inserita.
            @elseif(count($conf['commissari']) > 0) Le voci sono <strong>precompilate</strong> dalle {{ count($conf['commissari']) }} schede personali inviate (valore più frequente; in pareggio il più basso): controllale e correggile.
            @else Nessuna scheda personale inviata: scegli tu tutte le voci.
            @endif
        </div>
    </div>

    <div x-data="{
            v: @js($d['iniziali']),
            proposta: @js($d['proposta']),
            tot: {{ $nVoci }},
            fatte() { return Object.values(this.v).filter(x => x).length },
            usaProposta() { Object.keys(this.proposta).forEach(k => { if (this.proposta[k]) this.v[k] = this.proposta[k] }) },
        }" style="display:flex; flex-direction:column; gap:12px;">

        <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:space-between;">
            <span style="font-size:14px;">Voci compilate: <strong x-text="fatte() + '/' + tot"></strong></span>
            @if(count($conf['commissari']) > 0)
                <x-filament::button size="sm" color="gray" type="button" @click="usaProposta()">Ripristina la proposta</x-filament::button>
            @endif
        </div>

        @if(count($conf['commissari']) > 0)
            <div style="font-size:12px;">
                Totali delle schede personali inviate:
                @foreach($conf['commissari'] as $cm){{ $cm['membro'] }} <strong>{{ $cm['totale'] ?? '—' }}</strong>@if(! $loop->last) · @endif @endforeach
            </div>
        @endif

        @foreach($s['competenze'] as $comp)
            <section style="border:1px solid {{ $bordo }}; border-radius:10px; padding:10px 14px;">
                <h3 style="margin:0 0 8px; font-size:15px; font-weight:700;">{{ $comp['nome'] }}</h3>
                <div style="overflow-x:auto;">
                    <table style="border-collapse:collapse; font-size:13px; width:100%;">
                        @if(count($conf['commissari']) > 0)
                            <thead>
                                <tr style="text-align:left; font-size:12px;">
                                    <th style="padding:3px 6px;">Voce</th>
                                    @foreach($conf['commissari'] as $cm)
                                        <th style="padding:3px 6px; text-align:center; white-space:nowrap;">{{ $cm['membro'] }}</th>
                                    @endforeach
                                    <th style="padding:3px 6px; text-align:center;">Finale</th>
                                </tr>
                            </thead>
                        @endif
                        <tbody>
                            @foreach($comp['voci'] as $voce)
                                @php $cv = collect($conf['competenze'])->flatMap(fn ($c) => $c['voci'])->firstWhere('codice', $voce['codice']); @endphp
                                <tr style="border-top:1px solid {{ $bordo }}; {{ ($cv['discordante'] ?? false) ? 'background:' . $ambra . ';' : '' }}">
                                    <td style="padding:6px; overflow-wrap:anywhere;">{{ ($cv['discordante'] ?? false) ? '≠ ' : '' }}{{ $voce['testo'] }}</td>
                                    @foreach(($cv['valori'] ?? []) as $val)
                                        <td style="padding:3px 6px; text-align:center; font-weight:700; background:{{ $colori[$val] ?? 'transparent' }};">{{ $lettere[$val] ?? '—' }}</td>
                                    @endforeach
                                    <td style="padding:3px 6px; text-align:center; white-space:nowrap;">
                                        @foreach($s['scala'] as $chiave => $livello)
                                            <button type="button" title="{{ $livello['nome'] }}"
                                                    @click="v['{{ $voce['codice'] }}'] = '{{ $chiave }}'"
                                                    :style="v['{{ $voce['codice'] }}'] === '{{ $chiave }}' ? 'background:{{ $colori[$chiave] }}; border-color:rgba(128,128,128,.8);' : ''"
                                                    style="min-width:34px; padding:5px 8px; border:2px solid {{ $bordo }}; border-radius:8px; font-weight:800; cursor:pointer; background:transparent; color:inherit;">{{ $lettere[$chiave] ?? strtoupper(substr($chiave, 0, 1)) }}</button>
                                        @endforeach
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach

        <div style="font-size:11px;">R = rosso · G = giallo · V = verde @if(count($conf['commissari']) > 0)· «≠» e sfondo ambra = voce su cui i commissari non concordano @endif</div>

        <div style="display:flex; flex-wrap:wrap; gap:10px; justify-content:flex-end;">
            <x-filament::button color="gray" tag="a" :href="\App\Filament\Resources\EsameResource::getUrl('prova-pratica', ['record' => $this->record])">Annulla</x-filament::button>
            <x-filament::button color="success" type="button" x-bind:disabled="fatte() < tot" @click="$wire.salva(v)">Salva scheda finale</x-filament::button>
        </div>
    </div>
</x-filament-panels::page>
