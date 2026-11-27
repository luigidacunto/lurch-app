@extends('app.layout')

@section('title', 'Risultati del questionario')

@push('styles')
@include('app.partials.esami-stili')
<style>
    .box { background: #fff; border-radius: 12px; padding: 16px; margin-bottom: 14px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); }
</style>
@endpush

@section('content')
<a href="{{ route('app.home') }}" class="back-btn">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    I miei eventi
</a>
<p class="page-title">Questionario di gradimento</p>
<p class="page-sub">La tua valutazione – {{ $questionario->corso->numero_protocollo ?: $questionario->corso->nome }}</p>

@if(! $visibili)
    <div class="avviso-sola-lettura">I risultati si vedono quando hanno risposto almeno {{ \App\Questionari\StatisticheQuestionario::SOGLIA }} discenti, per non rendere riconoscibili le prime risposte.</div>
@else
    <div class="avviso-personale">Risposte anonime: punteggi aggregati e commenti in ordine casuale, senza date.</div>
    <div class="box">
        @include('questionari.statistiche', ['sezioni' => $sezioni, 'modo' => 'app'])
    </div>
@endif
@endsection
