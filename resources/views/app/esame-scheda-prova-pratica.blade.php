@extends('app.layout')

@section('title', $socio->cognome.' · Prova pratica')

@php
    $urlVoce = route('app.esame.provapratica.voce', [$esame, $socio]);
    $urlInvia = route('app.esame.provapratica.invia', [$esame, $socio]);
@endphp

@push('styles')
@include('app.partials.semaforo-stili')
@endpush

@section('content')
<a href="{{ route('app.esame.provapratica', $esame) }}" class="back-btn">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    Prova pratica
</a>
<p class="page-title">{{ $socio->cognome }} {{ $socio->nome }}</p>
<p class="page-sub">Prova pratica · scheda personale</p>

<div class="avviso-personale">Valutazione personale: sono appunti tuoi, non entrano nel calcolo dell'esame.</div>
@if(session('errore'))<div class="avviso-errore">{{ session('errore') }}</div>@endif
@if(! $modificabile)<div class="avviso-sola-lettura">La scheda si può solo consultare: l'esame non è in corso oppure la prova finale è chiusa.</div>@endif

@include('app.partials.semaforo-corpo')

@include('app.partials.semaforo-barra')
@endsection
