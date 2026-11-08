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
    .bozza { border: 2px solid #b91c1c; color: #b91c1c; font-weight: bold; padding: 6px 8px; margin-bottom: 12px; text-align: center; }
    .domanda { margin-bottom: 12px; page-break-inside: avoid; }
    .testo { font-weight: bold; margin-bottom: 4px; }
    .opzione { margin: 3px 0 3px 12px; }
    .cerchio { display: inline-block; width: 11px; height: 11px; border: 1px solid #000; border-radius: 6px; margin-right: 6px; vertical-align: middle; }
    .riga-dati td { padding: 3px 6px 3px 0; }
</style>
</head>
<body>
    <h1>{{ $esame->titolo }}</h1>
    <div class="sub">{{ $esame->corso?->nome }} · Test scritto</div>

    @if($bozza)
        <div class="bozza">BOZZA — non valida per lo svolgimento: il test non è ancora stato avviato e le domande potrebbero cambiare.</div>
    @endif

    <table class="box" style="width:100%">
        <tr class="riga-dati"><td><strong>Discente:</strong> {{ $discente->cognome }} {{ $discente->nome }}</td><td><strong>Data:</strong> ____ / ____ / ________</td></tr>
        <tr class="riga-dati"><td colspan="2"><strong>Firma:</strong> ________________________________________</td></tr>
    </table>

    <div class="box">{{ $regola }} Segna una sola opzione per ogni domanda.</div>

    @foreach($domande as $i => $d)
        <div class="domanda">
            <div class="testo">{{ $i + 1 }}. {{ $d['testo'] }}</div>
            @foreach($d['opzioni'] as $k => $testo)
                <div class="opzione"><span class="cerchio"></span><strong>{{ ['A', 'B', 'C', 'D'][$k] ?? $k + 1 }})</strong> {{ $testo }}</div>
            @endforeach
        </div>
    @endforeach
</body>
</html>
