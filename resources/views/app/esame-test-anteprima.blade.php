@extends('app.layout')

@section('title', 'Test scritto')

@php
    $lettere = ['A', 'B', 'C', 'D'];
    $etichettaStato = ['bozza' => 'In bozza', 'avviato' => 'Avviato', 'con_consegne' => 'Con test inviati'][$stato] ?? $stato;
    $statoRiga = ['non_avviato' => 'Non avviato', 'disattivato' => 'Disattivato', 'avviato' => 'Avviato', 'in_compilazione' => 'In compilazione', 'inviato' => 'Inviato'];
@endphp

@push('styles')
@include('app.partials.esami-stili')
<style>
    .box { background: #fff; border-radius: 12px; padding: 14px 16px; margin-bottom: 14px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); }
    .box h3 { font-size: 13px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 10px; }
    .dom-testo { font-size: 15px; font-weight: 600; color: #111827; margin-bottom: 6px; overflow-wrap: anywhere; white-space: pre-wrap; }
    .op { font-size: 14px; padding: 5px 8px; border-radius: 8px; margin-bottom: 4px; overflow-wrap: anywhere; white-space: pre-wrap; }
    .op.giusta { background: #dcfce7; color: #166534; font-weight: 700; }
    .op.data { outline: 2px solid #1d4ed8; }
    .riga { display: flex; justify-content: space-between; gap: 8px; padding: 8px 0; border-top: 1px solid #f3f4f6; font-size: 14px; }
    .riga .nome { font-weight: 600; overflow-wrap: anywhere; }
    .riga .esito { text-align: right; color: #374151; }
    details.dett > summary { cursor: pointer; font-size: 13px; color: #1d4ed8; padding: 4px 0; }
    .nota { font-size: 12px; color: #6b7280; }
</style>
@endpush

@section('content')
<a href="{{ route('app.esame', $esame) }}" class="back-btn">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    {{ $esame->titolo }}
</a>
<p class="page-title">Test scritto</p>
<p class="page-sub">Stato: {{ $etichettaStato }} · sola lettura</p>

<div class="box">
    <h3>Punteggio</h3>
    @if($config['penalita'])
        <div>Corretta +{{ \App\Esami\TestScritto::mostraDecimale($config['corretta']) }} · sbagliata −{{ \App\Esami\TestScritto::mostraDecimale($config['errata']) }} · non data −{{ \App\Esami\TestScritto::mostraDecimale($config['vuota']) }} · si possono lasciare risposte vuote</div>
    @else
        <div>1 punto per risposta corretta · tutte le risposte obbligatorie</div>
    @endif
    <div class="nota">Punteggio massimo: {{ $massimo }} · totale mai sotto 0</div>
</div>

<div class="box">
    <h3>Domande ({{ count($domande) }}) · la corretta è in verde</h3>
    @forelse($domande as $i => $d)
        <div style="margin-bottom:12px">
            <div class="dom-testo">{{ $i + 1 }}. {{ $d['testo'] }}</div>
            @foreach($d['opzioni'] as $k => $testo)
                <div class="op {{ $k === $d['corretta'] ? 'giusta' : '' }}">{{ $lettere[$k] ?? $k + 1 }}) {{ $testo }}</div>
            @endforeach
        </div>
    @empty
        <div class="nota">Nessuna domanda ancora.</div>
    @endforelse
</div>

<div class="box">
    <h3>Discenti</h3>
    @forelse($righe as $r)
        <div class="riga">
            <div class="nome">{{ $r['socio']->cognome }} {{ $r['socio']->nome }}
                @if($r['modalita'] === 'cartaceo')<span class="nota">· cartaceo</span>@endif
            </div>
            <div class="esito">
                {{ $statoRiga[$r['stato']] ?? $r['stato'] }}
                @if($r['punteggio'])<br><strong>{{ $r['punteggio'] }}</strong>@endif
                @if($r['stato'] === 'in_compilazione')<br><span class="nota">{{ $r['date'] }} su {{ $r['totale'] }}</span>@endif
            </div>
        </div>
        @if($r['stato'] === 'inviato' && $r['risposte'] !== null)
            <details class="dett">
                <summary>Vedi risposte @if($r['modalita'] === 'cartaceo')(cartaceo: {{ $r['motivo'] }})@endif</summary>
                @foreach($domande as $i => $d)
                    @php($data = $r['risposte'][$d['id']] ?? null)
                    <div class="nota" style="margin-top:6px">{{ $i + 1 }}. {{ $data === null ? 'non data' : ($lettere[$data] ?? $data + 1).') '.($d['opzioni'][$data] ?? '') }}
                        @if($data !== null) {{ $data === $d['corretta'] ? '✓' : '✗' }} @endif</div>
                @endforeach
            </details>
        @endif
    @empty
        <div class="nota">Nessun discente ammesso.</div>
    @endforelse
</div>
@endsection
