<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #111; background: #fff; padding: 14mm 16mm; }

    /* Header */
    .header { margin-bottom: 10px; }
    .header-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 5px; }
    .header h1 { font-size: 13px; font-weight: bold; margin-bottom: 3px; }
    .header .meta { font-size: 8.5px; color: #555; line-height: 1.6; }
    .header .meta span { margin-right: 14px; }
    .stamp { font-size: 8px; color: #aaa; text-align: right; white-space: nowrap; padding-top: 2px; }

    /* Riepilogo — riga in cima */
    .summary { width: 100%; border-collapse: collapse; margin-bottom: 10px; border: 1px solid #e5e7eb; border-radius: 6px; }
    .summary td { text-align: center; padding: 6px 10px; border-right: 1px solid #e5e7eb; }
    .summary td:last-child { border-right: none; }
    .summary .val { font-size: 15px; font-weight: bold; color: #1e3a5f; line-height: 1.1; display: block; }
    .summary .val.green { color: #15803d; }
    .summary .val.amber { color: #b45309; }
    .summary .lbl { font-size: 7.5px; color: #9ca3af; text-transform: uppercase; letter-spacing: .05em; display: block; margin-top: 1px; }

    /* Tabella */
    table { width: 100%; border-collapse: collapse; font-size: 9px; }
    thead tr { background: #1e3a5f; color: #fff; }
    thead th { padding: 6px 8px; text-align: left; font-weight: bold; font-size: 8.5px; letter-spacing: .04em; text-transform: uppercase; }
    thead th.center { text-align: center; }

    tbody tr { border-bottom: 1px solid #f0f0f0; }
    tbody tr:nth-child(even) { background: #f8fafc; }
    tbody td { padding: 5px 8px; vertical-align: middle; }
    tbody td.nr { width: 28px; color: #aaa; text-align: center; font-size: 8px; }
    tbody td.quota { font-weight: bold; color: #1e3a5f; text-align: center; }
    tbody td.data-ok { text-align: center; }
    tbody td.data-no { text-align: center; color: #d97706; font-style: italic; font-size: 8.5px; }
    tbody td.stato-ok { color: #15803d; font-weight: bold; text-align: center; }
    tbody td.stato-ricevuta { color: #7c3aed; text-align: center; }
    tbody td.stato-mancante { color: #d97706; text-align: center; }
    tbody td.stato-ritirato { color: #9ca3af; font-style: italic; text-align: center; }
    tbody tr.riga-ritirata td { color: #bbb; }

    /* Footer */
    .footer { margin-top: 10px; font-size: 7.5px; color: #bbb; border-top: 1px solid #eee; padding-top: 5px; display: flex; justify-content: space-between; }

    /* Banner corso annullato */
    .corso-annullato-banner { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; font-weight: bold; text-align: center; padding: 8px; margin-bottom: 10px; border-radius: 6px; font-size: 11px; }
</style>
</head>
<body>

@if($corso->isAnnullato())
    <div class="corso-annullato-banner">⚠ CORSO ANNULLATO</div>
@endif

@php
    $evento       = $corso->evento;
    $iscrizioni   = $corso->iscrizioni->sortBy('partecipante.cognome');
    $quota        = $corso->quota_partecipazione;
    $attive         = $iscrizioni->where('stato', '!=', 'ritirato');
    $nRitirati      = $iscrizioni->where('stato', 'ritirato')->count();
    $totale         = $attive->count();
    $conRicevuta    = $attive->filter(fn ($i) => $i->rispostaPreregistrazione?->ricevuta_path)->count();
    $mancanti       = $totale - $conRicevuta;
    $totaleRicevuto = $quota ? $quota * $conRicevuta : null;

    $dateCorso = $corso->data_inizio->format('d/m/Y');
    if ($corso->data_fine && $corso->data_fine->ne($corso->data_inizio)) {
        $dateCorso .= ' – ' . $corso->data_fine->format('d/m/Y');
    }
@endphp

<div class="header">
    <div class="header-top">
        <div>
            <h1>REGISTRO PAGAMENTI QUOTE — {{ strtoupper($corso->nome) }}</h1>
            <div class="meta">
                @if($corso->numero_protocollo)<span>Prot. <strong>{{ $corso->numero_protocollo }}</strong></span>@endif
                <span>Date: <strong>{{ $dateCorso }}</strong></span>
                @if($evento->sede)<span>Sede: <strong>{{ $evento->sede }}</strong></span>@endif
                @if($quota)<span>Quota: <strong>€ {{ number_format($quota, 2, ',', '.') }}</strong></span>@endif
            </div>
            <div class="meta">
                Evento: <strong>{{ $evento->nome }}</strong>
                @if($evento->comitatoRegionale) &nbsp;·&nbsp; {{ $evento->comitatoRegionale->nome }} @endif
            </div>
        </div>
        <div class="stamp">Stampato il {{ now()->format('d/m/Y H:i') }}</div>
    </div>
</div>

{{-- Riepilogo compatto in cima --}}
<table class="summary">
    <tr>
        <td><span class="val">{{ $totale }}</span><span class="lbl">Iscritti attivi</span></td>
        @if($nRitirati > 0)
        <td><span class="val" style="color:#9ca3af;">{{ $nRitirati }}</span><span class="lbl">Ritirati</span></td>
        @endif
        <td><span class="val {{ $mancanti > 0 ? 'amber' : '' }}">{{ $mancanti }}</span><span class="lbl">Quote mancanti</span></td>
        <td><span class="val green">{{ $conRicevuta }}</span><span class="lbl">Quote versate</span></td>
        @if($totaleRicevuto)
        <td><span class="val" style="color:#1e3a5f;">€ {{ number_format($totaleRicevuto, 2, ',', '.') }}</span><span class="lbl">Totale ricevuto</span></td>
        @endif
    </tr>
</table>

<table>
    <thead>
        <tr>
            <th class="center">NR</th>
            <th>COGNOME NOME DISCENTE</th>
            <th class="center">TOTALE QUOTA PAGATA</th>
            <th class="center">DATA PAGAMENTO</th>
            <th>COMITATO</th>
            <th class="center">STATO</th>
        </tr>
    </thead>
    <tbody>
        @php $nr = 0; @endphp
        @foreach($iscrizioni as $iscrizione)
            @php
                $ritirato = $iscrizione->isRitirato();
                if (! $ritirato) $nr++;
                $p        = $iscrizione->partecipante;
                $risposta = $iscrizione->rispostaPreregistrazione;
                $ricevuta = $risposta?->ricevuta_path;
                $verificato    = $risposta?->pagamento_verificato ?? false;
                $dataPagamento = $risposta?->data_pagamento;
            @endphp
            <tr{{ $ritirato ? ' class="riga-ritirata"' : '' }}>
                <td class="nr">{{ $ritirato ? '—' : $nr }}</td>
                <td><strong>{{ strtoupper($p?->cognome) }}</strong> {{ $p?->nome }}</td>
                <td class="quota">
                    @if($ritirato) — @elseif($quota) € {{ number_format($quota, 2, ',', '.') }} @else — @endif
                </td>
                <td class="{{ $dataPagamento ? 'data-ok' : 'data-no' }}">
                    {{ $dataPagamento ? $dataPagamento->format('d/m/Y') : 'non indicata' }}
                </td>
                <td>{{ $p?->comitatoLocale?->nome ?? $p?->comitatoRegionale?->nome ?? '—' }}</td>
                <td class="{{ $ritirato ? 'stato-ritirato' : ($verificato ? 'stato-ok' : ($ricevuta ? 'stato-ricevuta' : 'stato-mancante')) }}">
                    @if($ritirato) Ritirato
                    @elseif($verificato) ✓ Verificato
                    @elseif($ricevuta) Ricevuta allegata
                    @else Mancante
                    @endif
                </td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="footer">
    <span>LURCH — Logistica Unificata Registrazione Corsi e Hospitality</span>
    <span>{{ $evento->comitatoRegionale?->nome ?? '' }}</span>
</div>

</body>
</html>
