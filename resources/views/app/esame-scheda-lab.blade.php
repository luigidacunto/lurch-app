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
    .confronto-titolo { font-size: 13px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: .5px; margin: 18px 0 8px; }
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
                @include('app.partials.esami-matrice-lab', ['titoloNota' => 'Laboratorio '.$c['laboratorio']->numero.' · '.$socio->cognome.' '.$socio->nome])
            @endif
        </details>
    @endforeach
    <p class="legenda">R rosso · G giallo · V verde. Sfondo giallo: risposte diverse; sfondo rosso: un docente ha dato rosso e un altro verde. La tua scheda resta modificabile.</p>
@endif

@include('app.partials.semaforo-barra')
@endsection
