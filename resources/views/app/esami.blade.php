@extends('app.layout')

@section('title', 'Esami')

@push('styles')
<style>
    .back-btn {
        display: inline-flex; align-items: center; gap: 6px;
        color: #C8102E; font-size: 14px; font-weight: 600;
        text-decoration: none; margin-bottom: 16px;
    }
    .back-btn svg { width: 16px; height: 16px; }
    .page-title { font-size: 20px; font-weight: 800; color: #111827; margin-bottom: 20px; }
    a.esame-card { display: block; color: inherit; text-decoration: none; }
    .esame-card {
        background: #fff; border-radius: 12px; padding: 16px; margin-bottom: 12px;
        border-left: 4px solid #C8102E; box-shadow: 0 1px 4px rgba(0,0,0,0.07);
    }
    .card-badges { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 6px; }
    .card-badge {
        display: inline-block; font-size: 10px; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.5px; padding: 2px 8px; border-radius: 10px;
        background: #e0e7ff; color: #3730a3;
    }
    .card-badge.stato { background: #f3f4f6; color: #4b5563; }
    .card-nome { font-size: 15px; font-weight: 700; color: #111827; line-height: 1.3; overflow-wrap: anywhere; }
    .card-corso { font-size: 13px; color: #6b7280; margin-top: 4px; overflow-wrap: anywhere; }
    .card-data { font-size: 12px; color: #9ca3af; margin-top: 4px; }
    .empty-state { text-align: center; padding: 48px 0; color: #9ca3af; font-size: 14px; }
</style>
@endpush

@section('content')
<a href="{{ route('app.home') }}" class="back-btn">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    I miei eventi
</a>
<p class="page-title">I tuoi esami</p>

@forelse($esami as $voce)
    @php($esame = $voce['esame'])
    <a class="esame-card" href="{{ route('app.esame', $esame) }}">
        <div class="card-badges">
            @foreach($voce['ruoli'] as $ruolo)<span class="card-badge">{{ $ruolo }}</span>@endforeach
            <span class="card-badge stato">{{ $esame->isChiuso() ? 'Chiuso' : 'In corso' }}</span>
        </div>
        <div class="card-nome">{{ $esame->titolo }}</div>
        <div class="card-corso">{{ $esame->corso?->nome }}</div>
        @if($esame->corso?->data_inizio)
            <div class="card-data">
                {{ $esame->corso->data_inizio->isoFormat('D MMM YYYY') }}
                @if($esame->corso->data_fine && $esame->corso->data_fine->ne($esame->corso->data_inizio))
                    – {{ $esame->corso->data_fine->isoFormat('D MMM YYYY') }}
                @endif
            </div>
        @endif
    </a>
@empty
    <div class="empty-state">Non hai esami in corso.</div>
@endforelse
@endsection
