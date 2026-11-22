<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 20mm 16mm 18mm 16mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #111; }
    h1 { font-size: 16px; margin: 0 0 2px; }
    .sub { font-size: 11px; color: #444; margin-bottom: 10px; }
    .box { border: 1px solid #999; padding: 6px 8px; margin-bottom: 10px; }
    .riservato { border: 2px solid #b91c1c; color: #b91c1c; font-weight: bold; padding: 6px 8px; margin-bottom: 10px; text-align: center; }
    .legenda span { display: inline-block; padding: 1px 6px; margin-right: 8px; border-left: 4px solid #999; }
    .domanda { margin-bottom: 10px; padding: 5px 6px; border-top: 1px solid #ccc; page-break-inside: avoid; }
    .testo { font-weight: bold; margin-bottom: 2px; }
    .annullata .testo { text-decoration: line-through; color: #555; }
    .dati { font-size: 10px; color: #333; margin-bottom: 4px; }
    table { width: 100%; border-collapse: collapse; }
    td { padding: 2px 4px; vertical-align: top; }
    td.n { width: 70px; text-align: right; white-space: nowrap; }
    .giusta td { background: #d1fae5; font-weight: bold; }
    .nota-annullata { font-size: 10px; margin-top: 3px; }
</style>
</head>
<body>
    <h1>{{ $esame->titolo }}</h1>
    <div class="sub">{{ $esame->corso?->nome }} · Test scritto · Statistica delle risposte · generata il {{ $generata->format('d/m/Y H:i') }}</div>

    <div class="riservato">RISERVATO — contiene le risposte corrette: non consegnare ai discenti.</div>

    <div class="box">
        {{ $stat['inviati'] }} {{ $stat['inviati'] === 1 ? 'test inviato' : 'test inviati' }} (online e su carta) ·
        {{ count($stat['domande']) }} domande @if($nAnnullate > 0)({{ $nAnnullate }} {{ $nAnnullate === 1 ? 'annullata' : 'annullate' }})@endif ·
        punteggio massimo {{ $massimo }}. {{ $regola }}
    </div>

    <div class="box legenda">
        @foreach($evidenze as $ev)
            <span style="background: {{ $ev['stampa'] }}; border-left-color: {{ $ev['bordo'] }};">{{ $ev['etichetta'] }}</span>
        @endforeach
        <br>Errori = chi non ha dato la risposta corretta (sbagliata o non data), sui test inviati.
    </div>

    @foreach($stat['domande'] as $i => $q)
        @php $ev = $q['evidenza'] ? $evidenze[$q['evidenza']] : null; @endphp
        <div class="domanda {{ $q['annullata'] ? 'annullata' : '' }}" @if($ev) style="background: {{ $ev['stampa'] }}; border-left: 4px solid {{ $ev['bordo'] }};" @endif>
            <div class="testo">{{ $i + 1 }}. {{ $q['testo'] }}</div>
            <div class="dati">
                @if($ev)<strong style="color: {{ $ev['bordo'] }};">{{ $ev['etichetta'] }}</strong> · @endif
                @if($q['annullata'])<strong>Domanda annullata</strong> ·
                @elseif($q['errore'] !== null)Errori <strong>{{ $q['errore'] }}%</strong> · @endif
                giuste {{ $q['giuste'] }} · sbagliate {{ $q['sbagliate'] }} · non date {{ $q['vuote'] }}
            </div>
            <table>
                @foreach($q['opzioni'] as $k => $testo)
                    @php $n = $q['scelte'][$k] ?? 0; @endphp
                    <tr class="{{ $k === $q['corretta'] ? 'giusta' : '' }}">
                        <td><strong>{{ ['A', 'B', 'C', 'D'][$k] ?? $k + 1 }})</strong> {{ $testo }}{{ $k === $q['corretta'] ? ' ✓' : '' }}</td>
                        <td class="n">{{ $n }}@if($stat['inviati'] > 0) · {{ (int) round(100 * $n / $stat['inviati']) }}%@endif</td>
                    </tr>
                @endforeach
            </table>
            @if($q['annullata'])
                <div class="nota-annullata">Annullata da {{ $q['annullata']['da'] ?? 'il direttore' }} il {{ $q['annullata']['il']->format('d/m/Y H:i') }} · Motivo: {{ $q['annullata']['motivo'] }}</div>
            @endif
        </div>
    @endforeach
</body>
</html>
