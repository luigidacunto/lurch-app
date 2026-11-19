@extends('app.layout')

@section('title', 'Esito finale')

@push('styles')
@include('app.partials.esami-stili')
<style>
    .box { background: #fff; border-radius: 12px; padding: 16px; margin-bottom: 14px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); }
    .nota { font-size: 13px; color: #6b7280; line-height: 1.4; }
    .totale { font-size: 30px; font-weight: 800; color: #111827; line-height: 1.2; margin-top: 2px; }
    .totale small { font-size: 16px; font-weight: 600; color: #6b7280; }
    .esito { display: inline-block; margin-top: 8px; padding: 4px 12px; border-radius: 999px; font-size: 14px; font-weight: 800; }
    .esito.ok { background: #dcfce7; color: #15803d; }
    .esito.no { background: #fef2f2; color: #b91c1c; }
    /* Griglia: bordi su ogni cella, colonne numeriche allineate a destra */
    table.prove { width: 100%; border-collapse: collapse; font-size: 14px; color: #374151; font-variant-numeric: tabular-nums; }
    table.prove th, table.prove td { border: 1px solid #e5e7eb; padding: 9px 10px; text-align: right; }
    table.prove th { background: #f9fafb; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: #6b7280; }
    table.prove th:first-child, table.prove td:first-child { text-align: left; }
    table.prove td { white-space: nowrap; }
    table.prove td:first-child { white-space: normal; font-weight: 600; color: #111827; }
    table.prove td small { color: #9ca3af; }
    table.prove tr.tot td { background: #f9fafb; font-weight: 800; color: #111827; }
    table.prove tr td.ok { color: #15803d; }
    table.prove tr td.no { color: #b91c1c; }
</style>
@endpush

@section('content')
<a href="{{ route('app.esame', $esame) }}" class="back-btn">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    {{ $esame->titolo }}
</a>
<p class="page-title">Esito finale</p>
<p class="page-sub">{{ $esame->titolo }}</p>

<div class="box">
    <div class="nota">Punteggio finale</div>
    <div class="totale">{{ $esito['finale'] }} <small>su 100</small></div>
    <span class="esito {{ $esito['idoneo'] ? 'ok' : 'no' }}">{{ $esito['idoneo'] ? 'Idoneo' : 'Non idoneo' }}</span>
</div>

<div class="box">
    <table class="prove">
        <thead>
            <tr><th>Prova</th><th>Punteggio</th><th>In centesimi</th></tr>
        </thead>
        <tbody>
            @foreach($esito['prove'] as $p)
                <tr>
                    <td>{{ $p['nome'] }}</td>
                    <td>{{ $p['punti'] ?? '–' }} <small>su {{ $p['massimo'] ?? '–' }}</small></td>
                    <td>{{ $p['contributo'] ?? '–' }} <small>su {{ $p['peso'] }}</small></td>
                </tr>
            @endforeach
            <tr class="tot"><td>Punteggio finale</td><td></td><td>{{ $esito['finale'] }} <small>su 100</small></td></tr>
            <tr><td>LAP (soglia di idoneità)</td><td></td><td>{{ $esito['lap'] }}</td></tr>
            <tr class="tot"><td>Esito</td><td></td><td class="{{ $esito['idoneo'] ? 'ok' : 'no' }}">{{ $esito['idoneo'] ? 'Idoneo' : 'Non idoneo' }}</td></tr>
        </tbody>
    </table>
    <p class="nota" style="margin-top:10px;">Ogni prova vale un massimo di centesimi (il numero dopo «su» nella terza colonna): il punteggio della prova è convertito in proporzione e il punteggio finale è la somma.</p>
</div>
@endsection
