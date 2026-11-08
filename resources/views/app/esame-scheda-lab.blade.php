@extends('app.layout')

@section('title', $socio->cognome.' · Laboratorio '.$laboratorio->numero)

@php
    $urlVoce = route('app.esame.scheda.voce', [$esame, $laboratorio, $socio]);
    $urlInvia = route('app.esame.scheda.invia', [$esame, $laboratorio, $socio]);
@endphp

@push('styles')
@include('app.partials.semaforo-stili')
<style>
    details.confronto > summary { cursor: pointer; font-size: 13px; font-weight: 700; color: #4b5563; }
    .confronto-scroll { overflow-x: auto; margin: 10px 0 6px; }
    .confronto-scroll table { border-collapse: collapse; font-size: 12px; min-width: 100%; }
    .confronto-scroll th, .confronto-scroll td { padding: 4px 6px; border-top: 1px solid #e5e7eb; text-align: center; }
    .confronto-scroll th:first-child, .confronto-scroll td:first-child { text-align: left; }
    .confronto-titolo { font-size: 13px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: .5px; margin: 18px 0 8px; }
    .confronto-comp { font-weight: 700; padding-top: 8px !important; }
    .confronto-scroll tr.discorde { background: #fef9c3; }
    .confronto-scroll tr.salto { background: #fee2e2; }
    .confronto-totale td { border-top: 2px solid #9ca3af; font-weight: 800; padding-top: 6px; }
    .pillola-totale { display: inline-block; padding: 1px 6px; border-radius: 6px; color: #111827; white-space: nowrap; }
    .lv { font-weight: 800; }
    .lv-rosso { color: #dc2626; } .lv-giallo { color: #a16207; } .lv-verde { color: #16a34a; }
</style>
@endpush

@section('content')
<a href="{{ route('app.esame.laboratorio', [$esame, $laboratorio]) }}" class="back-btn">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    Laboratorio {{ $laboratorio->numero }}
</a>
<p class="page-title">{{ $socio->cognome }} {{ $socio->nome }}</p>
<p class="page-sub">Laboratorio {{ $laboratorio->numero }}@if($laboratorio->data) · {{ $laboratorio->data->isoFormat('D MMM YYYY') }}@endif</p>

<div class="avviso-personale">Valutazione personale: sono appunti tuoi, non entrano nel calcolo dell'esame.</div>
@if(session('errore'))<div class="avviso-errore">{{ session('errore') }}</div>@endif
@if(! $modificabile)<div class="avviso-sola-lettura">L'esame non è in corso: la scheda si può solo consultare.</div>@endif

@include('app.partials.semaforo-corpo')

@include('app.partials.esami-annotazione', [
    'urlAnnotazione' => route('app.esame.scheda.annotazione', [$esame, $laboratorio, $socio]),
    'chiLegge' => 'li leggono il direttore e, a scheda inviata, gli altri docenti nel confronto dei laboratori',
])

@if($andamento)
    <div class="andamento">
        <h3>Il tuo andamento con {{ $socio->nome }}</h3>
        @foreach($andamento as $a)
            <div>Laboratorio {{ $a['laboratorio']->numero }}:
                @if($a['totale']) {{ $a['totale']['punti'] }} su {{ $a['totale']['massimo'] }}@if($a['inviata']) (inviata)@endif
                @else bozza @endif
            </div>
        @endforeach
    </div>
@endif

@if($confronto)
    <h3 class="confronto-titolo">Schede inviate dei docenti · sola lettura</h3>
    @foreach($confronto as $c)
        @php $m = $c['dettaglio']; @endphp
        <details class="andamento confronto" @if($c['corrente']) open @endif>
            <summary>Laboratorio {{ $c['laboratorio']->numero }}@if($c['laboratorio']->data) · {{ $c['laboratorio']->data->isoFormat('D MMM') }}@endif
                @if($c['corrente']) (questo)@endif · {{ count($m['docenti']) }} {{ count($m['docenti']) === 1 ? 'scheda' : 'schede' }}</summary>
            @if($c['corrente'] && count($m['docenti']) < 2)
                <p class="legenda">Nessun altro docente ha ancora inviato la scheda di {{ $socio->nome }}.</p>
            @else
                <div class="confronto-scroll">
                    <table>
                        <thead><tr><th>Voce</th>@foreach($m['docenti'] as $i => $docNome)<th>{{ $docNome }}@if($m['note'][$i] ?? null) @include('app.partials.esami-nota-dialog', ['docente' => $docNome, 'testo' => $m['note'][$i], 'titolo' => 'Laboratorio '.$c['laboratorio']->numero.' · '.$socio->cognome.' '.$socio->nome])@endif</th>@endforeach</tr></thead>
                        <tbody>
                            @foreach($m['competenze'] as $comp)
                                <tr><td colspan="{{ count($m['docenti']) + 1 }}" class="confronto-comp">{{ $comp['nome'] }}</td></tr>
                                @foreach($comp['voci'] as $voce)
                                    <tr class="{{ $voce['salto'] ? 'salto' : ($voce['discordante'] ? 'discorde' : '') }}">
                                        <td>{{ $voce['testo'] }}</td>
                                        @foreach($voce['valori'] as $val)<td class="lv lv-{{ $val ?? 'vuoto' }}">{{ ['rosso' => 'R', 'giallo' => 'G', 'verde' => 'V'][$val] ?? '—' }}</td>@endforeach
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="confronto-totale">
                                <td>Totale (su {{ $m['massimo'] }})</td>
                                @foreach($m['totali'] as $t)<td>@if($t)<span class="pillola-totale" style="background:{{ $t['colore'] }};">{{ $t['totale'] }}</span>@else — @endif</td>@endforeach
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </details>
    @endforeach
    <p class="legenda">R rosso · G giallo · V verde. Sfondo giallo: risposte diverse; sfondo rosso: un docente ha dato rosso e un altro verde. La tua scheda resta modificabile.</p>
@endif

@include('app.partials.semaforo-barra')
@endsection
