@extends('app.layout')

@section('title', 'I miei eventi')

@push('styles')
<style>
    .home-greeting {
        font-size: 15px;
        color: #6b7280;
        margin-bottom: 20px;
    }
    .home-greeting strong { color: #111827; }
    .section-title {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        color: #9ca3af;
        margin-bottom: 12px;
    }
    .evento-card {
        display: block;
        background: #fff;
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 12px;
        text-decoration: none;
        color: inherit;
        border-left: 4px solid #C8102E;
        box-shadow: 0 1px 4px rgba(0,0,0,0.07);
        transition: box-shadow 0.15s, transform 0.1s;
        position: relative;
    }
    .evento-card:active { transform: scale(0.98); box-shadow: 0 1px 2px rgba(0,0,0,0.06); }
    .card-badges { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 6px; }
    .card-badge {
        display: inline-block;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 2px 8px;
        border-radius: 10px;
    }
    .badge-corsista { background: #fee2e2; color: #b91c1c; }
    .badge-staff { background: #ffedd5; color: #c2410c; }
    .card-nome {
        font-size: 15px;
        font-weight: 700;
        color: #111827;
        margin-bottom: 6px;
        line-height: 1.3;
        padding-right: 24px;
    }
    .card-data {
        font-size: 12px;
        color: #9ca3af;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .card-arrow {
        position: absolute;
        right: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #d1d5db;
        font-size: 18px;
    }
    .empty-state {
        text-align: center;
        padding: 48px 0;
        color: #9ca3af;
    }
    .empty-state svg { width: 48px; height: 48px; margin: 0 auto 12px; display: block; opacity: 0.4; }
    .empty-state p { font-size: 14px; }
</style>
@endpush

@section('content')
<p class="home-greeting">Ciao, <strong>{{ $socio->nome }} {{ $socio->cognome }}</strong></p>

@if($areaDocenti)
    <p class="section-title">Area docenti</p>
    <a href="{{ route('app.esami') }}" class="evento-card">
        <div class="card-nome">Esami da gestire</div>
        <div class="card-data">Riservata a docenti e commissione: schede di valutazione e tutorati</div>
        <span class="card-arrow">›</span>
    </a>
@elseif($haEsami)
    <p class="section-title">Esami</p>
    <a href="{{ route('app.esami') }}" class="evento-card">
        <div class="card-nome">I tuoi esami</div>
        <span class="card-arrow">›</span>
    </a>
@endif

<p class="section-title">I tuoi eventi e corsi</p>

@if($eventi->isEmpty())
    <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="currentColor">
            <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-2 .89-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.11-.9-2-2-2zm0 16H5V8h14v11z"/>
        </svg>
        <p>Non risulti iscritto a nessun evento o corso.</p>
    </div>
@else
    @foreach($eventi as $voce)
        @php
            $evento     = $voce['evento'];
            $dataInizio = $evento->data_inizio;
            $dataFine   = $evento->data_fine;
        @endphp
        <a href="{{ route('app.evento', $evento->id) }}" class="evento-card">
            <div class="card-badges">
                @if($voce['is_corsista'])<span class="card-badge badge-corsista">Corsista</span>@endif
                @if($voce['is_staff'])<span class="card-badge badge-staff">Staff</span>@endif
            </div>
            <div class="card-nome">{{ $evento->nome }}</div>
            <div class="card-data">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-2 .89-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.11-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/>
                </svg>
                @if($dataFine && $dataFine->ne($dataInizio))
                    {{ $dataInizio->isoFormat('D MMM') }} – {{ $dataFine->isoFormat('D MMM YYYY') }}
                @else
                    {{ $dataInizio->isoFormat('D MMMM YYYY') }}
                @endif
            </div>
            <span class="card-arrow">›</span>
        </a>
    @endforeach
@endif
@endsection
