<x-filament-panels::page>
    @php
        $esame  = $this->record;
        $d      = $this->dati();
        $bordo  = 'rgba(128,128,128,.35)';
        $grigio = 'rgba(128,128,128,.10)';
        $num    = fn (?string $nd) => $nd === null ? '—' : \App\Esami\Formato::decimale(\App\Esami\Calcolo\Frazione::daFrazione($nd));
        $cent   = fn (?string $c) => $c === null ? '—' : str_replace('.', ',', $c);
        $nomiProve = \App\Esami\RisultatiEsame::ETICHETTE_PROVE;
        $badge = function (array $r) {
            return match (true) {
                $r['stato'] === 'non_ammesso' => ['Non ammesso', 'rgba(128,128,128,.25)'],
                $r['stato'] === 'incompleto'  => ['Incompleto', 'rgba(234,179,8,.30)'],
                $r['idoneo'] === true         => ['Idoneo', 'rgba(34,197,94,.30)'],
                default                       => ['Non idoneo', 'rgba(239,68,68,.30)'],
            };
        };
    @endphp

    {{-- Stato dell'esito --}}
    <div style="border:1px solid {{ $bordo }}; background:{{ $grigio }}; border-radius:8px; padding:10px 14px;">
        @if($d['chiuso'])
            <strong>Esame chiuso</strong> da {{ $esame->chiusoDa?->nome_completo ?? '—' }} il {{ $esame->chiuso_il?->format('d/m/Y H:i') }}.
            <div style="font-size:13px;">
                Esiti definitivi (snapshot): non si ricalcolano e nessun dato dell'esame si modifica.
                @if($esame->riapribileFinoAl() && now()->lessThanOrEqualTo($esame->riapribileFinoAl()))
                    Riapribile (solo superadmin, con motivo) fino al <strong>{{ $esame->riapribileFinoAl()->format('d/m/Y') }}</strong>.
                @else
                    <strong>Non più riapribile</strong> (sono passati {{ \App\Models\Esame::RIAPERTURA_GIORNI }} giorni dalla chiusura).
                @endif
            </div>
        @else
            <strong>PROVVISORIO</strong> — calcolato dal vivo dalle schede finali, non ancora definitivo.
            <div style="font-size:13px;">L'esame si chiude solo quando ogni discente ammesso ha tutti i dati (4 punteggi d'area, colloquio, prova pratica, test inviato). Chi non può essere completato si segna «non ammesso» (pagina Discenti) o si ritira dal corso.</div>
        @endif
        @if($esame->riaperto_il)
            <div style="font-size:13px; margin-top:4px;">Ultima riapertura: {{ $esame->riapertoDa?->name ?? '—' }} il {{ $esame->riaperto_il->format('d/m/Y H:i') }} — «{{ $esame->riaperto_motivo }}»</div>
        @endif
        <div style="font-size:13px; margin-top:6px;">
            <strong>{{ $d['idonei'] }}</strong> idonei · <strong>{{ $d['nonIdonei'] }}</strong> non idonei
            @if($d['incompleti']) · <strong>{{ $d['incompleti'] }}</strong> incompleti @endif
            @if($d['nonAmmessi']) · <strong>{{ $d['nonAmmessi'] }}</strong> non ammessi @endif
            — LAP {{ \App\Esami\Formato::decimale(\App\Esami\Calcolo\Frazione::da((string) $esame->lap)) }}
        </div>
    </div>

    {{-- Un discente per riga: i 4 contributi in centesimi (e i punti grezzi), finale, esito --}}
    <x-filament::section heading="Risultati" description="Per ogni prova: contributo in centesimi (quanto pesa sul 100, troncato a 2 decimali) e punti grezzi tra parentesi. Il finale è calcolato sui valori esatti ed è troncato (mai arrotondato) a 1 decimale.">
        @forelse($d['righe'] as $r)
            @php [$etichetta, $colore] = $badge($r); @endphp
            <div x-data="{ aperto: false }" style="border:1px solid {{ $bordo }}; border-radius:8px; margin-bottom:8px;">
                <div style="display:flex; flex-wrap:wrap; gap:8px 16px; align-items:center; padding:8px 12px; cursor:pointer;" x-on:click="aperto = ! aperto">
                    <strong style="min-width:180px;">{{ $r['socio']->cognome }} {{ $r['socio']->nome }}</strong>
                    <span style="background:{{ $colore }}; border-radius:999px; padding:2px 10px; font-size:12px; font-weight:600;">{{ $etichetta }}</span>
                    @if($r['stato'] === 'calcolato')
                        @foreach(['itinere', 'colloquio', 'prova_pratica', 'test_scritto'] as $p)
                            <span style="font-size:13px;">{{ $nomiProve[$p] }}: <strong>{{ $cent($r['prove'][$p]['contributo'] ?? null) }}</strong></span>
                        @endforeach
                        <span style="margin-left:auto; font-size:15px;">Finale <strong>{{ $cent($r['finale']) }}</strong></span>
                    @elseif($r['stato'] === 'incompleto')
                        <span style="font-size:13px;">Manca: {{ implode(', ', $r['mancanti']) }}</span>
                    @else
                        <span style="font-size:13px;">Motivo: {{ $r['motivo_non_ammesso'] }}</span>
                    @endif
                </div>

                <div x-show="aperto" x-cloak style="border-top:1px solid {{ $bordo }}; padding:10px 12px; font-size:13px;">
                    @if($r['stato'] === 'non_ammesso')
                        Non ammesso alla prova finale: nessuna scheda finale e nessun punteggio. Motivo: {{ $r['motivo_non_ammesso'] }}
                    @else
                        <table style="border-collapse:collapse; width:100%; max-width:640px;">
                            <thead>
                                <tr style="text-align:left;">
                                    <th style="padding:3px 8px;">Prova</th><th style="padding:3px 8px;">Peso</th>
                                    <th style="padding:3px 8px;">Punti</th><th style="padding:3px 8px;">Contributo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(['itinere', 'colloquio', 'prova_pratica', 'test_scritto'] as $p)
                                    <tr style="border-top:1px solid {{ $bordo }};">
                                        <td style="padding:3px 8px;">{{ $nomiProve[$p] }}</td>
                                        <td style="padding:3px 8px;">{{ $d['pesi'][$p] ?? '—' }}%</td>
                                        <td style="padding:3px 8px;">{{ isset($r['prove'][$p]) ? $num($r['prove'][$p]['punti']) . ' su ' . $num($r['prove'][$p]['massimo']) : '—' }}</td>
                                        <td style="padding:3px 8px;">{{ $cent($r['prove'][$p]['contributo'] ?? null) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if(! empty($r['ingresso']['itinere']))
                            <div style="margin-top:6px;">Punteggi finali d'area:
                                @foreach($r['ingresso']['itinere'] as $codice => $v)
                                    {{ $d['aree'][$codice] ?? $codice }} <strong>{{ str_replace('.', ',', $v) }}</strong>@if(! $loop->last) · @endif
                                @endforeach
                                @if(isset($r['ingresso']['colloquio'])) · Colloquio <strong>{{ str_replace('.', ',', $r['ingresso']['colloquio']) }}</strong> @endif
                            </div>
                        @endif
                        @if($r['stato'] === 'calcolato')
                            <div style="margin-top:6px;">Finale esatto {{ $r['finale_esatto'] }} → <strong>{{ $cent($r['finale']) }}</strong> (troncato) · LAP {{ str_replace('.', ',', rtrim(rtrim($r['lap'], '0'), '.')) }} → <strong>{{ $r['idoneo'] ? 'idoneo' : 'non idoneo' }}</strong>.
                                <em>Il finale è calcolato sui valori esatti; i parziali sono mostrati troncati a 2 decimali.</em></div>
                        @endif
                        <div style="margin-top:8px;">
                            <x-filament::button size="sm" color="info" tag="a" target="_blank" :href="route('esami.esito-pdf', [$esame, $r['socio']])" icon="heroicon-o-document-arrow-down">PDF del discente</x-filament::button>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <p style="margin:0;">Nessun discente iscritto al corso.</p>
        @endforelse
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-panels::page>
