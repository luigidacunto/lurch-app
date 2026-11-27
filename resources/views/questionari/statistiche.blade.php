{{--
    Risultati aggregati del questionario di gradimento, condivisi da pannello, app e PDF (stili in linea, niente
    classi Tailwind né Filament). $sezioni = StatisticheQuestionario::per(...). $modo: 'pannello' (schede affiancate,
    sezioni richiudibili), 'app' (una colonna), 'pdf' (dompdf: niente grid né details, barre fatte con tabelle).
--}}
@php
    $modo   = $modo ?? 'app';
    $pdf    = $modo === 'pdf';
    $colori = \App\Questionari\StatisticheQuestionario::COLORI_SCALA;
    $scala  = \App\Questionari\Domande::SCALA;
    $num    = fn ($v) => $v === null ? '–' : number_format($v, 2, ',', '');
    $griglia = $modo === 'pannello' ? 'display:grid; grid-template-columns:repeat(auto-fill, minmax(340px, 1fr)); gap:12px;' : '';
    $scheda = 'border:1px solid #e5e7eb; border-radius:10px; padding:12px 14px; background:#fff;' . ($pdf ? ' margin-bottom:10px; page-break-inside:avoid;' : ($modo === 'app' ? ' margin-bottom:10px;' : ''));
@endphp

{{-- Barra 1–5: segmenti proporzionali con il conteggio scritto dentro (tabella: funziona anche in dompdf). --}}
@php
    $barra = function (array $conteggi, int $n) use ($colori, $scala) {
        if ($n === 0) {
            return '<div style="font-size:12px; color:#9ca3af;">Nessuna risposta.</div>';
        }
        $celle = '';
        $primo = true;
        foreach ($conteggi as $v => $c) {
            if ($c === 0) {
                continue;
            }
            $perc = round($c / $n * 100, 1);
            [$sfondo, $testo] = $colori[$v];
            $celle .= '<td title="' . e($v . ' – ' . $scala[$v] . ': ' . $c . ' su ' . $n) . '" style="width:' . $perc . '%; background:' . $sfondo . '; color:' . $testo
                . '; text-align:center; font-size:11px; font-weight:bold; height:20px; padding:0;' . ($primo ? '' : ' border-left:2px solid #fff;') . '">'
                . ($perc >= 8 ? $c : '') . '</td>';
            $primo = false;
        }

        return '<table style="width:100%; border-collapse:collapse; table-layout:fixed; border-radius:4px; overflow:hidden;"><tr>' . $celle . '</tr></table>';
    };
@endphp

<div style="display:flex; flex-wrap:wrap; gap:6px 14px; align-items:center; font-size:12px; color:#374151; margin-bottom:14px;">
    @foreach($scala as $v => $e)
        <span style="white-space:nowrap;"><span style="display:inline-block; width:12px; height:12px; border-radius:3px; vertical-align:-2px; background:{{ $colori[$v][0] }};"></span> {{ $v }} {{ $e }}</span>
    @endforeach
</div>

{{-- Panoramica: una riga per sezione, con la media e la distribuzione di tutte le sue risposte a scala. --}}
@if(count($sezioni) > 1)
    <div style="{{ $scheda }} margin-bottom:16px;">
        <div style="font-size:14px; font-weight:bold; color:#111827; margin-bottom:8px;">Panoramica</div>
        <table style="width:100%; border-collapse:collapse; font-size:12px; color:#374151;">
            @foreach($sezioni as $k => $sezione)
                <tr>
                    <td style="padding:4px 10px 4px 0; width:{{ $pdf ? '38%' : '30%' }}; vertical-align:middle;">
                        @if($pdf){{ $sezione['titolo'] }}@else<a href="#sez-{{ $k }}" style="color:#111827; text-decoration:none;" onclick="document.getElementById('sez-{{ $k }}').open = true">{{ $sezione['titolo'] }}</a>@endif
                    </td>
                    @if($sezione['riepilogo'])
                        <td style="padding:4px 10px 4px 0; vertical-align:middle;">{!! $barra($sezione['riepilogo']['conteggi'], $sezione['riepilogo']['n']) !!}</td>
                        <td style="padding:4px 0; width:52px; text-align:right; font-weight:bold; color:#111827; font-size:13px;">{{ $num($sezione['riepilogo']['media']) }}</td>
                    @else
                        <td colspan="2" style="padding:4px 0; color:#9ca3af;">Solo commenti</td>
                    @endif
                </tr>
            @endforeach
        </table>
    </div>
@endif

@foreach($sezioni as $k => $sezione)
    @if($pdf)
        <div style="margin:0 0 16px;">
            <div style="font-size:15px; font-weight:bold; color:#111827; margin:0 0 8px; page-break-after:avoid; border-bottom:2px solid #C8102E; padding-bottom:4px;">{{ $sezione['titolo'] }}@if($sezione['riepilogo']) <span style="font-weight:normal; font-size:12px; color:#6b7280;">· media {{ $num($sezione['riepilogo']['media']) }}</span>@endif</div>
    @else
        <details id="sez-{{ $k }}" @if($modo === 'app' || count($sezioni) === 1) open @endif style="margin:0 0 10px; border:1px solid #e5e7eb; border-radius:12px; background:#f9fafb;">
            <summary style="cursor:pointer; padding:10px 14px; font-size:15px; font-weight:bold; color:#111827; list-style-position:inside;">
                {{ $sezione['titolo'] }}@if($sezione['riepilogo']) <span style="font-weight:normal; font-size:13px; color:#6b7280;">· media {{ $num($sezione['riepilogo']['media']) }}</span>@endif
            </summary>
            <div style="padding:0 14px 14px;">
    @endif

    @foreach($sezione['parti'] as $parte)
        @if($parte['sottotitolo'])
            <div style="font-size:13px; font-weight:bold; color:#374151; margin:12px 0 8px; page-break-after:avoid;">{{ $parte['sottotitolo'] }}</div>
        @endif
        <div style="{{ $griglia }}">
            @foreach($parte['domande'] as $d)
                <div style="{{ $scheda }}{{ $d['tipo'] === 'testo' && ! $pdf && $modo === 'pannello' ? ' grid-column:1 / -1;' : '' }}">
                    <div style="font-size:13px; font-weight:bold; color:#111827; margin-bottom:6px;">{{ $d['label'] }}</div>
                    @if($d['tipo'] === 'scala')
                        <div style="margin-bottom:6px; color:#374151; font-size:12px;">
                            <span style="font-size:20px; font-weight:bold; color:#111827;">{{ $num($d['media']) }}</span> su 5 · {{ $d['n'] }} risposte
                        </div>
                        {!! $barra($d['conteggi'], $d['n']) !!}
                    @elseif($d['tipo'] === 'scelta')
                        <table style="width:100%; border-collapse:collapse; font-size:12px; color:#374151;">
                            @foreach($d['conteggi'] as $o => $c)
                                @php $perc = $d['n'] ? round($c / $d['n'] * 100) : 0; @endphp
                                <tr>
                                    <td style="padding:3px 8px 3px 0; width:45%;">{{ $o }}</td>
                                    <td style="padding:3px 8px 3px 0;">
                                        <table style="width:100%; border-collapse:collapse; table-layout:fixed;"><tr>
                                            @if($perc > 0)<td style="width:{{ $perc }}%; height:12px; background:#1b64be; padding:0;"></td>@endif
                                            @if($perc < 100)<td style="height:12px; background:#eef2f7; padding:0;"></td>@endif
                                        </tr></table>
                                    </td>
                                    <td style="padding:3px 0; width:62px; text-align:right; white-space:nowrap;"><strong>{{ $c }}</strong> · {{ $perc }}%</td>
                                </tr>
                            @endforeach
                        </table>
                        @if($d['altri'])
                            <ul style="margin:6px 0 0 18px; padding:0; list-style:disc; font-size:12px; color:#374151;">
                                @foreach($d['altri'] as $t)<li style="margin-bottom:2px;">Altro: {{ $t }}</li>@endforeach
                            </ul>
                        @endif
                    @else
                        @if($d['testi'])
                            <ul style="margin:0 0 0 18px; padding:0; list-style:disc; font-size:12px; color:#374151; {{ $modo === 'pannello' ? 'columns:2 360px; column-gap:28px;' : '' }}">
                                @foreach($d['testi'] as $t)<li style="margin-bottom:5px; white-space:pre-line; overflow-wrap:anywhere; break-inside:avoid;">{{ $t }}</li>@endforeach
                            </ul>
                        @else
                            <div style="font-size:12px; color:#9ca3af;">Nessun testo.</div>
                        @endif
                    @endif
                </div>
            @endforeach
        </div>
    @endforeach

    @if($pdf)
        </div>
    @else
            </div>
        </details>
    @endif
@endforeach
