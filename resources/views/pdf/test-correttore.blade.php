<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 22mm 18mm 20mm 18mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
    h1 { font-size: 16px; margin: 0 0 2px; }
    .sub { font-size: 11px; color: #444; margin-bottom: 10px; }
    .box { border: 1px solid #999; padding: 6px 8px; margin-bottom: 12px; }
    .riservato { border: 2px solid #b91c1c; color: #b91c1c; font-weight: bold; padding: 6px 8px; margin-bottom: 12px; text-align: center; }
    .domanda { margin-bottom: 12px; page-break-inside: avoid; }
    .testo { font-weight: bold; margin-bottom: 4px; }
    .opzione { margin: 3px 0 3px 12px; padding: 2px 4px; }
    .giusta { background: #d1fae5; font-weight: bold; }
</style>
</head>
<body>
    <h1>{{ $esame->titolo }}</h1>
    <div class="sub">{{ $esame->corso?->nome }} · Test scritto · Correttore</div>

    <div class="riservato">RISERVATO — contiene le risposte corrette: non consegnare ai discenti.</div>

    <div class="box">{{ count($domande) }} domande · punteggio massimo {{ $massimo }}. {{ $regola }}</div>

    @foreach($domande as $i => $d)
        <div class="domanda">
            <div class="testo">{{ $i + 1 }}. {{ $d['testo'] }}</div>
            @foreach($d['opzioni'] as $k => $testo)
                <div class="opzione {{ $k === $d['corretta'] ? 'giusta' : '' }}">{{ $k === $d['corretta'] ? '✓' : '' }} <strong>{{ ['A', 'B', 'C', 'D'][$k] ?? $k + 1 }})</strong> {{ $testo }}</div>
            @endforeach
        </div>
    @endforeach
</body>
</html>
