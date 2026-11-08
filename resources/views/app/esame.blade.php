@extends('app.layout')

@section('title', 'Esame')

@push('styles')
<style>
    .back-btn {
        display: inline-flex; align-items: center; gap: 6px;
        color: #C8102E; font-size: 14px; font-weight: 600;
        text-decoration: none; margin-bottom: 16px;
    }
    .back-btn svg { width: 16px; height: 16px; }
    .page-title { font-size: 20px; font-weight: 800; color: #111827; margin-bottom: 4px; overflow-wrap: anywhere; }
    .page-sub { font-size: 13px; color: #6b7280; margin-bottom: 12px; overflow-wrap: anywhere; }
    .card-badges { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 16px; }
    .card-badge {
        display: inline-block; font-size: 10px; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.5px; padding: 2px 8px; border-radius: 10px;
        background: #e0e7ff; color: #3730a3;
    }
    .card-badge.stato { background: #f3f4f6; color: #4b5563; }
    .sezione { font-size: 13px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: .5px; margin: 8px 0 10px; }
    a.lab-card {
        display: block; color: inherit; text-decoration: none;
        background: #fff; border-radius: 12px; padding: 16px; margin-bottom: 12px;
        border-left: 4px solid #C8102E; box-shadow: 0 1px 4px rgba(0,0,0,0.07);
    }
    .lab-nome { font-size: 15px; font-weight: 700; color: #111827; }
    .lab-data { font-size: 12px; color: #9ca3af; margin-top: 2px; }
    .lab-stato { font-size: 13px; color: #4b5563; margin-top: 8px; }
    .vuoto { background: #fff; border-radius: 12px; padding: 16px; font-size: 14px; color: #6b7280; margin-bottom: 12px; }
</style>
@endpush

@section('content')
<a href="{{ route('app.esami') }}" class="back-btn">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    Esami da gestire
</a>
<p class="page-title">{{ $esame->titolo }}</p>
<p class="page-sub">{{ $esame->corso?->nome }}</p>
<div class="card-badges">
    @foreach($ruoli as $ruolo)<span class="card-badge">{{ $ruolo }}</span>@endforeach
    <span class="card-badge stato">{{ $esame->isChiuso() ? 'Chiuso' : 'In corso' }}</span>
</div>

@if($tutorati)
<p class="sezione">Tutoraggio</p>
<a class="lab-card" href="{{ route('app.esame.tutorati', $esame) }}">
    <div class="lab-nome">I miei tutorati</div>
    <div class="lab-stato">{{ $tutorati }} {{ $tutorati === 1 ? 'discente' : 'discenti' }} · andamento con le schede di tutti i docenti — sola lettura</div>
</a>
@endif

@if($provaFinale)
@include('app.partials.esame-sezione-prova-finale')
@endif

<p class="sezione">Itinere</p>
<a class="lab-card" href="{{ route('app.esame.itinere', $esame) }}">
    <div class="lab-nome">Schede itinere</div>
    <div class="lab-stato">
        @if($itinere['discenti'] > 0)
            Schede iniziate: {{ $itinere['iniziate'] }} su {{ $itinere['discenti'] }}
        @else
            Nessuna scheda da compilare
        @endif
    </div>
</a>
@if($riepilogoItinere)
<a class="lab-card" href="{{ route('app.esame.itinere.riepilogo', $esame) }}">
    <div class="lab-nome">Riepilogo itinere</div>
    <div class="lab-stato">Schede di tutti i docenti e punteggio finale d'area — sola lettura</div>
</a>
@endif

@unless($provaFinale)
@include('app.partials.esame-sezione-prova-finale')
@endunless

@if($testScritto)
<p class="sezione">Test scritto</p>
<a class="lab-card" href="{{ route('app.esame.test.anteprima', $esame) }}">
    <div class="lab-nome">Test scritto</div>
    <div class="lab-stato">Domande con le risposte corrette e risultati — sola lettura</div>
</a>
@endif

<p class="sezione">Laboratori</p>
@forelse($laboratori as $voce)
    <a class="lab-card" href="{{ route('app.esame.laboratorio', [$esame, $voce['lab']]) }}">
        <div class="lab-nome">Laboratorio {{ $voce['lab']->numero }}</div>
        @if($voce['lab']->data)<div class="lab-data">{{ $voce['lab']->data->isoFormat('D MMM YYYY') }}</div>@endif
        <div class="lab-stato">
            @if($voce['discenti'] > 0)
                Schede inviate: {{ $voce['inviate'] }} su {{ $voce['discenti'] }}
            @elseif($eDocente)
                Nessun discente assegnato a te
            @else
                Nessuna scheda da compilare
            @endif
        </div>
    </a>
@empty
    <div class="vuoto">Nessun laboratorio.</div>
@endforelse
@endsection
