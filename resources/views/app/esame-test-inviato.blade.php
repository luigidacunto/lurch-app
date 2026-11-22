@extends('app.layout')

@section('title', 'Test scritto')

@php
    $lettere = ['A', 'B', 'C', 'D'];
    $corrette = $correzione['corrette'] ?? null;
    $annullate = $correzione['annullate'] ?? []; // solo dopo la pubblicazione
@endphp

@push('styles')
@include('app.partials.esami-stili')
<style>
    .box { background: #fff; border-radius: 12px; padding: 14px 16px; margin-bottom: 14px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); }
    .risultato { font-size: 22px; font-weight: 800; color: #111827; }
    .nota { font-size: 13px; color: #6b7280; line-height: 1.4; }
    .domanda { background: #fff; border-radius: 12px; padding: 14px 16px; margin-bottom: 14px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); }
    .domanda-num { display: flex; justify-content: space-between; gap: 8px; font-size: 12px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 6px; }
    .esito-ok { color: #15803d; }
    .esito-no { color: #b91c1c; }
    .domanda-testo { font-size: 16px; font-weight: 600; color: #111827; line-height: 1.4; overflow-wrap: anywhere; white-space: pre-wrap; margin-bottom: 10px; }
    .opzione { display: flex; align-items: flex-start; gap: 10px; padding: 9px 12px; margin-bottom: 8px; border-radius: 10px; border: 2px solid #e5e7eb; background: #fff; font-size: 15px; color: #1f2937; }
    .opzione .lettera { flex: none; width: 26px; height: 26px; border-radius: 50%; background: #f3f4f6; color: #4b5563; font-weight: 800; font-size: 13px; display: flex; align-items: center; justify-content: center; }
    .opzione .testo { overflow-wrap: anywhere; white-space: pre-wrap; line-height: 1.35; padding-top: 3px; flex: 1; }
    .opzione .tag { flex: none; font-size: 11px; font-weight: 700; padding-top: 5px; }
    .opzione.data { border-color: #1d4ed8; background: #eff6ff; }
    .opzione.data .lettera { background: #1d4ed8; color: #fff; }
    .opzione.giusta { border-color: #15803d; background: #dcfce7; }
    .opzione.giusta .lettera { background: #15803d; color: #fff; }
    .opzione.sbagliata { border-color: #b91c1c; background: #fef2f2; }
    .opzione.sbagliata .lettera { background: #b91c1c; color: #fff; }
    .annullata .domanda-testo, .annullata .opzione .testo { text-decoration: line-through; color: #6b7280; }
    .motivo-annullata { font-size: 13px; color: #374151; background: #f3f4f6; border-radius: 8px; padding: 8px 10px; margin-bottom: 10px; overflow-wrap: anywhere; white-space: pre-line; }
</style>
@endpush

@section('content')
<a href="{{ route('app.esame', $esame) }}" class="back-btn">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    {{ $esame->titolo }}
</a>
<p class="page-title">Test scritto</p>
<p class="page-sub">{{ $esame->titolo }} · sola lettura</p>

@if($correzione)
    <div class="box">
        <div class="nota">Risultato del test scritto</div>
        <div class="risultato">{{ $correzione['punteggio'] }}</div>
        <div class="nota">Risposte giuste: {{ $correzione['giuste'] }} su {{ $correzione['totale'] }}. In verde la risposta corretta, in rosso la tua se era sbagliata.@if($annullate) {{ count($annullate) === 1 ? 'Una domanda è stata annullata e non conta' : count($annullate).' domande sono state annullate e non contano' }} nel punteggio.@endif</div>
    </div>
@else
    <div class="vuoto">Hai concluso il test scritto: le tue risposte sono registrate e non si possono più modificare. Le correzioni compariranno qui quando il direttore del corso pubblicherà i risultati.</div>
@endif

@if($cartaceo)
    <div class="avviso-personale">Test svolto su carta: le risposte sono quelle inserite dal direttore. Se noti un errore di trascrizione, segnalalo al direttore del corso.</div>
@endif

@foreach($domande as $i => $d)
    @php
        $data = $scelte[$d['id']] ?? null;
        $giusta = $corrette[$d['id']] ?? null;
        $annullata = array_key_exists($d['id'], $annullate);
    @endphp
    <div class="domanda {{ $annullata ? 'annullata' : '' }}">
        <div class="domanda-num">
            <span>Domanda {{ $i + 1 }} di {{ count($domande) }}</span>
            @if($annullata)
                <span>Domanda annullata</span>
            @elseif($corrette !== null)
                @if($data === null)<span class="esito-no">Non data</span>
                @elseif($data === $giusta)<span class="esito-ok">✓ Giusta</span>
                @else<span class="esito-no">✗ Sbagliata</span>@endif
            @elseif($data === null)
                <span>Non data</span>
            @endif
        </div>
        <div class="domanda-testo">{{ $d['testo'] }}</div>
        @if($annullata)<div class="motivo-annullata">Domanda annullata: non conta nel punteggio. Motivo: {{ $annullate[$d['id']] }}</div>@endif
        @foreach($d['opzioni'] as $k => $testo)
            @php
                $classe = match (true) {
                    $annullata => $k === $data ? 'data' : '',
                    $corrette !== null && $k === $giusta => 'giusta',
                    $corrette !== null && $k === $data => 'sbagliata',
                    $k === $data => 'data',
                    default => '',
                };
            @endphp
            <div class="opzione {{ $classe }}">
                <span class="lettera">{{ $lettere[$k] ?? $k + 1 }}</span><span class="testo">{{ $testo }}</span>
                @if($k === $data)<span class="tag">La tua</span>@endif
            </div>
        @endforeach
    </div>
@endforeach
@endsection
