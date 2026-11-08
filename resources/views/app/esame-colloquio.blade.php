@extends('app.layout')

@section('title', 'Colloquio')

@include('app.partials.elenco-discenti-ricerca')

@section('content')
<a href="{{ route('app.esame', $esame) }}" class="back-btn">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    {{ $esame->titolo }}
</a>
<p class="page-title">Colloquio</p>
<p class="page-sub">Autovalutazione del discente: un voto da {{ $esame->modello_snapshot['colloquio']['scala']['min'] }} a {{ $esame->modello_snapshot['colloquio']['scala']['max'] }} e, se vuoi, la tua impressione</p>

<div class="avviso-personale">Valutazione personale: sono appunti tuoi, non entrano nel calcolo dell'esame. Il voto finale lo inserisce il direttore.</div>

@if($righe->isNotEmpty())
    <input type="search" id="cerca" class="cerca" placeholder="Cerca per nome o cognome" autocomplete="off" aria-label="Cerca discente">
@endif

@forelse($righe as $r)
    <a class="disc-card" data-nome="{{ $r['socio']->cognome }} {{ $r['socio']->nome }}" href="{{ route('app.esame.colloquio.scheda', [$esame, $r['socio']]) }}">
        <div class="disc-nome">{{ $r['socio']->cognome }} {{ $r['socio']->nome }}</div>
        <div class="disc-stato">{{ $r['voto'] !== null ? 'Il tuo voto: '.$r['voto'] : 'Da fare' }}</div>
    </a>
@empty
    <div class="vuoto">Non hai schede da compilare.</div>
@endforelse
<div class="vuoto" id="nessun-risultato" hidden>Nessun discente trovato.</div>
@endsection
