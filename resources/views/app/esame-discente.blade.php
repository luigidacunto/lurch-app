@extends('app.layout')

@section('title', $esame->titolo)

@php
    $sottotitoloTest = [
        'da_compilare' => 'Da compilare',
        'inviato'      => 'Inviato · le tue risposte in sola lettura',
        'corretto'     => 'Correzione disponibile',
    ][$statoTest];
@endphp

@push('styles')
@include('app.partials.esami-stili')
<style>
    .azione { display: flex; align-items: center; gap: 14px; background: #fff; border-radius: 12px; padding: 16px; margin-bottom: 12px; box-shadow: 0 1px 4px rgba(0,0,0,0.07); text-decoration: none; color: inherit; }
    .azione-icona { width: 44px; height: 44px; border-radius: 10px; background: #fee2e2; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .azione-icona svg { width: 22px; height: 22px; color: #C8102E; }
    .azione-corpo { flex: 1; min-width: 0; }
    .azione-titolo { font-size: 15px; font-weight: 700; color: #111827; }
    .azione-sub { font-size: 12px; color: #6b7280; margin-top: 2px; }
    .azione-freccia { color: #d1d5db; font-size: 22px; flex-shrink: 0; }
</style>
@endpush

@section('content')
<a href="{{ route('app.esami') }}" class="back-btn">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    I tuoi esami
</a>
<p class="page-title">{{ $esame->titolo }}</p>
<p class="page-sub">Il tuo esame</p>

<a href="{{ route('app.esame.test', $esame) }}" class="azione">
    <div class="azione-icona">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
    </div>
    <div class="azione-corpo">
        <div class="azione-titolo">Test scritto</div>
        <div class="azione-sub">{{ $sottotitoloTest }}</div>
    </div>
    <span class="azione-freccia">›</span>
</a>

@if($vedeEsito)
    <a href="{{ route('app.esame.esito', $esame) }}" class="azione">
        <div class="azione-icona">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.9 6.26L22 9.27l-5 4.87L18.18 22 12 18.56 5.82 22 7 14.14l-5-4.87 7.1-1.01L12 2z"/></svg>
        </div>
        <div class="azione-corpo">
            <div class="azione-titolo">Esito finale</div>
            <div class="azione-sub">Totale, esito e contributo di ogni prova</div>
        </div>
        <span class="azione-freccia">›</span>
    </a>
@else
    <div class="vuoto">L'esito finale comparirà qui quando il direttore del corso lo pubblicherà.</div>
@endif
@endsection
