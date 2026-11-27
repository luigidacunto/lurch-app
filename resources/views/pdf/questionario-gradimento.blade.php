<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 20mm 16mm 18mm 16mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
    h1 { font-size: 16px; margin: 0 0 2px; }
    .sub { font-size: 11px; color: #444; margin-bottom: 10px; }
    .box { border: 1px solid #999; padding: 6px 8px; margin-bottom: 14px; }
</style>
</head>
<body>
    <h1>Questionario di gradimento – Discenti – {{ $corso->numero_protocollo ?: $corso->nome }}</h1>
    <div class="sub">{{ $corso->nome }} · {{ $sottotitolo }}</div>

    <div class="box">
        Risposte anonime: {{ $compilazioni }}. Questionario inviato il {{ $questionario->inviato_il->format('d/m/Y') }},
        {{ $questionario->eAperto() ? 'aperto fino al' : 'chiuso il' }} {{ $questionario->chiude_il->format('d/m/Y') }}.
        Commenti in ordine casuale e senza date.
    </div>

    @include('questionari.statistiche', ['sezioni' => $sezioni, 'modo' => 'pdf'])
</body>
</html>
