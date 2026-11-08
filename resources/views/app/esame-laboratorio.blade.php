@extends('app.layout')

@section('title', 'Laboratorio '.$laboratorio->numero)

@push('styles')
<style>
    .back-btn {
        display: inline-flex; align-items: center; gap: 6px;
        color: #C8102E; font-size: 14px; font-weight: 600;
        text-decoration: none; margin-bottom: 16px;
    }
    .back-btn svg { width: 16px; height: 16px; }
    .page-title { font-size: 20px; font-weight: 800; color: #111827; margin-bottom: 4px; }
    .page-sub { font-size: 13px; color: #6b7280; margin-bottom: 14px; overflow-wrap: anywhere; }
    .avviso-personale { background: #eff6ff; color: #1e40af; border-radius: 10px; padding: 10px 12px; font-size: 13px; margin-bottom: 14px; }
    .flash-ok { background: #ecfdf5; color: #065f46; border-radius: 10px; padding: 10px 12px; font-size: 14px; margin-bottom: 14px; }
    a.disc-card {
        display: block; color: inherit; text-decoration: none; min-height: 56px;
        background: #fff; border-radius: 12px; padding: 14px 16px; margin-bottom: 10px;
        border-left: 4px solid #C8102E; box-shadow: 0 1px 4px rgba(0,0,0,0.07);
    }
    .disc-nome { font-size: 15px; font-weight: 700; color: #111827; overflow-wrap: anywhere; }
    .disc-stato { font-size: 13px; color: #6b7280; margin-top: 4px; }
    .disc-stato.inviata { color: #047857; font-weight: 600; }
    .vuoto { background: #fff; border-radius: 12px; padding: 16px; font-size: 14px; color: #6b7280; }
</style>
@endpush

@section('content')
<a href="{{ route('app.esame', $esame) }}" class="back-btn">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    {{ $esame->titolo }}
</a>
<p class="page-title">Laboratorio {{ $laboratorio->numero }}</p>
<p class="page-sub">@if($laboratorio->data){{ $laboratorio->data->isoFormat('D MMMM YYYY') }} · @endif I discenti del tuo gruppo</p>

<div class="avviso-personale">Valutazione personale: sono appunti tuoi, non entrano nel calcolo dell'esame.</div>

@if(session('ok'))<div class="flash-ok">{{ session('ok') }}</div>@endif

@forelse($righe as $r)
    <a class="disc-card" href="{{ route('app.esame.scheda', [$esame, $laboratorio, $r['socio']]) }}">
        <div class="disc-nome">{{ $r['socio']->cognome }} {{ $r['socio']->nome }}</div>
        @if($r['inviata'])
            <div class="disc-stato inviata">Inviata{{ $r['totale'] ? ' · '.$r['totale']['punti'].' su '.$r['totale']['massimo'] : '' }}</div>
        @elseif($r['compilate'] > 0)
            <div class="disc-stato">Bozza · {{ $r['compilate'] }} voci su {{ $nVoci }}</div>
        @else
            <div class="disc-stato">Da compilare</div>
        @endif
    </a>
@empty
    <div class="vuoto">
        @if($eDocente)
            Non hai discenti assegnati in questo laboratorio. Chiedi al direttore di inserirti in un gruppo.
        @else
            Non hai schede da compilare in questo laboratorio.
        @endif
    </div>
@endforelse
@endsection
