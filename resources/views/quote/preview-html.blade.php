<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Anteprima quote — {{ $corso->nome }}</title>
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: system-ui, -apple-system, sans-serif; font-size: 13px; color: #111827; background: #f8fafc; }

    .page { max-width: 1100px; margin: 0 auto; padding: 24px 20px 48px; }

    /* Header */
    .header { background: #1e3a5f; color: #fff; border-radius: 10px; padding: 18px 24px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: flex-start; gap: 20px; }
    .header h1 { font-size: 15px; font-weight: 700; margin-bottom: 5px; letter-spacing: .02em; }
    .header .meta { font-size: 11px; color: #93c5fd; line-height: 1.8; }
    .header .meta strong { color: #fff; }
    .stamp { font-size: 10px; color: #7cb9f7; white-space: nowrap; text-align: right; padding-top: 2px; }

    /* Stats */
    .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 16px; }
    .stat-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 14px 16px; text-align: center; }
    .stat-card .val { font-size: 22px; font-weight: 700; line-height: 1.1; }
    .stat-card .lbl { font-size: 10px; color: #9ca3af; text-transform: uppercase; letter-spacing: .06em; margin-top: 3px; }
    .stat-card.mancanti { border-color: #fde68a; }
    .stat-card.mancanti .val { color: #b45309; }
    .stat-card.versate .val { color: #15803d; }
    .stat-card.totale .val { color: #1e3a5f; font-size: 18px; }

    /* Toolbar */
    .toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
    .toolbar h2 { font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: .06em; }
    .btn-download { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 500; color: #fff; background: #15803d; border: none; border-radius: 6px; padding: 7px 14px; text-decoration: none; cursor: pointer; }
    .btn-download:hover { background: #166534; }

    /* Table */
    .wrap { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; }
    table { width: 100%; border-collapse: collapse; font-size: 12px; }
    thead tr { background: #1e3a5f; }
    thead th { padding: 9px 12px; color: #fff; font-weight: 600; font-size: 10px; text-transform: uppercase; letter-spacing: .05em; text-align: left; }
    thead th.center { text-align: center; }
    tbody tr { border-bottom: 1px solid #f3f4f6; }
    tbody tr:nth-child(even) { background: #f8fafc; }
    tbody tr:hover { background: #f0f9ff; }
    tbody td { padding: 8px 12px; vertical-align: middle; }
    td.nr { width: 36px; text-align: center; color: #9ca3af; font-size: 11px; }
    td.quota { text-align: center; font-weight: 600; color: #1e3a5f; }
    td.data { text-align: center; }
    td.data-no { text-align: center; color: #d1d5db; font-size: 11px; }
    td.stato { text-align: center; }
    .badge { display: inline-flex; align-items: center; gap: 4px; font-size: 10px; font-weight: 600; border-radius: 5px; padding: 3px 8px; white-space: nowrap; }
    .badge-ok  { color: #15803d; background: #f0fdf4; border: 1px solid #bbf7d0; }
    .badge-nv  { color: #7c3aed; background: #f5f3ff; border: 1px solid #ddd6fe; }
    .badge-man { color: #b45309; background: #fef3c7; border: 1px solid #fde68a; }
    .nome strong { color: #111827; }
    .nome .sub  { font-size: 10px; color: #9ca3af; margin-top: 1px; }

    @media print { body { background: #fff; } .btn-download { display: none; } }

    .corso-annullato-banner { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; font-weight: bold; text-align: center; padding: 10px 14px; margin-bottom: 16px; border-radius: 8px; font-size: 13px; }
</style>
</head>
<body>
<div class="page">

@if($corso->isAnnullato())
    <div class="corso-annullato-banner">⚠ CORSO ANNULLATO</div>
@endif

@php
    $evento      = $corso->evento;
    $iscrizioni  = $corso->iscrizioni->sortBy('partecipante.cognome');
    $quota       = $corso->quota_partecipazione;
    $totale      = $iscrizioni->count();
    $conRicevuta = $iscrizioni->filter(fn($i) => $i->rispostaPreregistrazione?->ricevuta_path)->count();
    $mancanti    = $totale - $conRicevuta;
    $totaleRicevuto = $quota ? $quota * $conRicevuta : null;
@endphp

<div class="header">
    <div>
        <h1>REGISTRO QUOTE — {{ strtoupper($corso->nome) }}</h1>
        <div class="meta">
            @if($corso->numero_protocollo)<span>Prot. <strong>{{ $corso->numero_protocollo }}</strong></span> &nbsp;·&nbsp; @endif
            Evento: <strong>{{ $evento->nome }}</strong>
            @if($quota) &nbsp;·&nbsp; Quota: <strong>€ {{ number_format($quota, 2, ',', '.') }}</strong>@endif
        </div>
    </div>
    <div class="stamp">Generato il {{ now()->format('d/m/Y H:i') }}</div>
</div>

<div class="stats">
    <div class="stat-card">
        <div class="val">{{ $totale }}</div>
        <div class="lbl">Iscritti</div>
    </div>
    <div class="stat-card {{ $mancanti > 0 ? 'mancanti' : '' }}">
        <div class="val" style="{{ $mancanti === 0 ? 'color:#9ca3af' : '' }}">{{ $mancanti }}</div>
        <div class="lbl">Quote mancanti</div>
    </div>
    <div class="stat-card versate">
        <div class="val">{{ $conRicevuta }}</div>
        <div class="lbl">Quote versate</div>
    </div>
    @if($totaleRicevuto)
    <div class="stat-card totale">
        <div class="val">€ {{ number_format($totaleRicevuto, 2, ',', '.') }}</div>
        <div class="lbl">Totale ricevuto</div>
    </div>
    @endif
</div>

<div class="toolbar">
    <h2>Elenco partecipanti</h2>
    <a href="{{ route('corsi.quote-excel', $corso) }}" class="btn-download" target="_blank">
        ↓ Scarica Excel
    </a>
</div>

<div class="wrap">
<table>
    <thead>
        <tr>
            <th class="center">NR</th>
            <th>Cognome Nome</th>
            <th class="center">Quota</th>
            <th class="center">Data pagamento</th>
            <th>Comitato</th>
            <th class="center">Stato</th>
        </tr>
    </thead>
    <tbody>
        @foreach($iscrizioni->values() as $i => $iscrizione)
            @php
                $p             = $iscrizione->partecipante;
                $risposta      = $iscrizione->rispostaPreregistrazione;
                $ricevuta      = $risposta?->ricevuta_path;
                $verificato    = $risposta?->pagamento_verificato ?? false;
                $dataPagamento = $risposta?->data_pagamento;
                $stato = $verificato ? 'Verificato' : ($ricevuta ? 'Non verificata' : 'Mancante');
            @endphp
            <tr>
                <td class="nr">{{ $i + 1 }}</td>
                <td class="nome">
                    <strong>{{ strtoupper($p?->cognome) }}</strong> {{ $p?->nome }}
                    @if($p?->email)<div class="sub">{{ $p->email }}</div>@endif
                </td>
                <td class="quota">{{ $quota ? '€ ' . number_format($quota, 2, ',', '.') : '—' }}</td>
                <td class="{{ $dataPagamento ? 'data' : 'data-no' }}">
                    {{ $dataPagamento ? $dataPagamento->format('d/m/Y') : '—' }}
                </td>
                <td>{{ $p?->comitatoLocale?->nome ?? $p?->comitatoRegionale?->nome ?? '—' }}</td>
                <td class="stato">
                    @if($verificato)
                        <span class="badge badge-ok">✓ Verificato</span>
                    @elseif($ricevuta)
                        <span class="badge badge-nv">Ricevuta da verificare</span>
                    @else
                        <span class="badge badge-man">Mancante</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
</div>

</div>
</body>
</html>
