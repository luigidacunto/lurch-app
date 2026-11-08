@extends('app.layout')

@section('title', 'Prova pratica')

@include('app.partials.elenco-discenti-ricerca')

@section('content')
<a href="{{ route('app.esame', $esame) }}" class="back-btn">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    {{ $esame->titolo }}
</a>
<p class="page-title">Prova pratica</p>
<p class="page-sub">Scheda a semaforo: {{ $nVoci }} voci per discente</p>

<div class="avviso-personale">Valutazione personale: sono appunti tuoi, non entrano nel calcolo dell'esame. La scheda finale la inserisce il direttore.</div>
@if(session('ok'))<div class="avviso-sola-lettura">{{ session('ok') }}</div>@endif

@if($righe->isNotEmpty())
    <input type="search" id="cerca" class="cerca" placeholder="Cerca per nome o cognome" autocomplete="off" aria-label="Cerca discente">
@endif

@forelse($righe as $r)
    <a class="disc-card" data-nome="{{ $r['socio']->cognome }} {{ $r['socio']->nome }}" href="{{ route('app.esame.provapratica.scheda', [$esame, $r['socio']]) }}">
        <div class="disc-nome">{{ $r['socio']->cognome }} {{ $r['socio']->nome }}</div>
        <div class="disc-stato">
            @if($r['inviata'])
                Inviata{{ $r['totale'] ? ' · '.$r['totale']['punti'].' su '.$r['totale']['massimo'] : '' }}
            @elseif($r['compilate'] > 0)
                Bozza · {{ $r['compilate'] }} voci su {{ $nVoci }}
            @else
                Da compilare
            @endif
        </div>
    </a>
@empty
    <div class="vuoto">Non hai schede da compilare.</div>
@endforelse
<div class="vuoto" id="nessun-risultato" hidden>Nessun discente trovato.</div>
@endsection
