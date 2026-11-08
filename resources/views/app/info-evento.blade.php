@extends('app.layout')

@section('title', $evento->nome)

@push('styles')
<style>
    .back-btn {
        display: inline-flex; align-items: center; gap: 6px;
        color: #C8102E; font-size: 14px; font-weight: 600;
        text-decoration: none; margin-bottom: 16px;
    }
    .back-btn svg { width: 16px; height: 16px; }
    .evento-intestazione {
        width: 100%; border-radius: 12px; overflow: hidden;
        margin-bottom: 16px; max-height: 200px; object-fit: cover; display: block;
    }
    .evento-header {
        background: #fff; border-radius: 12px; padding: 16px;
        margin-bottom: 16px; box-shadow: 0 1px 4px rgba(0,0,0,0.07);
    }
    .evento-badges { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 10px; }
    .badge {
        display: inline-block; font-size: 10px; font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.5px;
        padding: 3px 10px; border-radius: 10px;
    }
    .badge-corsista { background: #fee2e2; color: #b91c1c; }
    .badge-staff    { background: #ffedd5; color: #c2410c; }
    .evento-nome { font-size: 20px; font-weight: 800; color: #111827; margin-bottom: 12px; line-height: 1.3; }
    .meta-row {
        display: flex; align-items: flex-start; gap: 8px;
        font-size: 13px; color: #6b7280; margin-bottom: 6px;
    }
    .meta-row svg { width: 15px; height: 15px; flex-shrink: 0; margin-top: 1px; }
    .meta-row a { color: #C8102E; text-decoration: none; }

    .section-card {
        background: #fff; border-radius: 12px; padding: 16px;
        margin-bottom: 16px; box-shadow: 0 1px 4px rgba(0,0,0,0.07);
    }
    .section-card h2 {
        font-size: 11px; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.8px; color: #9ca3af; margin-bottom: 14px;
    }
    .note-app-content { font-size: 14px; color: #374151; line-height: 1.7; }
    .note-app-content h1, .note-app-content h2, .note-app-content h3 { margin: 12px 0 6px; color: #111827; }
    .note-app-content p { margin-bottom: 10px; }
    .note-app-content ul, .note-app-content ol { padding-left: 20px; margin-bottom: 10px; }
    .note-app-content a { color: #C8102E; }
    .note-app-content strong { color: #111827; }
    .note-app-content img { max-width: 100%; border-radius: 8px; }

    .doc-list { list-style: none; }
    .doc-item {
        display: flex; align-items: center; gap: 10px;
        padding: 12px 0; border-bottom: 1px solid #f3f4f6;
    }
    .doc-item:last-child { border-bottom: none; }
    .doc-icon {
        width: 36px; height: 36px; background: #fee2e2; border-radius: 8px;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .doc-icon svg { width: 18px; height: 18px; color: #C8102E; }
    .doc-info { flex: 1; min-width: 0; }
    .doc-name {
        font-size: 14px; font-weight: 600; color: #111827;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .doc-size { font-size: 11px; color: #9ca3af; margin-top: 2px; }
    .doc-open-icon { color: #d1d5db; flex-shrink: 0; font-size: 16px; }
</style>
@endpush

@section('content')

<a href="{{ route('app.evento', $evento) }}" class="back-btn">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    {{ $evento->nome }}
</a>

@if($intestazioneSrc)
    <img src="{{ $intestazioneSrc }}" alt="{{ $evento->nome }}" class="evento-intestazione">
@endif

<div class="evento-header">
    <div class="evento-badges">
        @if($isCorsista)<span class="badge badge-corsista">Corsista</span>@endif
        @if($isStaff)<span class="badge badge-staff">Staff</span>@endif
    </div>

    <div class="evento-nome">{{ $evento->nome }}</div>

    @if($evento->data_inizio)
        <div class="meta-row">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-2 .89-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.11-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/></svg>
            @if($evento->data_fine && $evento->data_fine->ne($evento->data_inizio))
                {{ $evento->data_inizio->isoFormat('D MMMM') }} – {{ $evento->data_fine->isoFormat('D MMMM YYYY') }}
            @else
                {{ $evento->data_inizio->isoFormat('D MMMM YYYY') }}
            @endif
        </div>
    @endif

    @if($evento->sede)
        <div class="meta-row">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
            <span>
                {{ $evento->sede }}
                @if($evento->indirizzo), {{ $evento->indirizzo }}@endif
                @if($evento->coordinate_maps)
                    — <a href="https://maps.google.com/maps?q={{ urlencode($evento->coordinate_maps) }}" target="_blank" rel="noopener">Apri in Maps</a>
                @elseif($evento->indirizzo)
                    — <a href="https://maps.google.com/maps?q={{ urlencode($evento->sede . ' ' . $evento->indirizzo) }}" target="_blank" rel="noopener">Apri in Maps</a>
                @endif
            </span>
        </div>
    @endif
</div>

@if(filled($evento->note_app))
    <div class="section-card">
        <h2>Informazioni</h2>
        <div class="note-app-content">{!! $evento->note_app !!}</div>
    </div>
@endif

@if($documenti->isNotEmpty())
    <div class="section-card">
        <h2>Documenti allegati</h2>
        <ul class="doc-list">
            @foreach($documenti as $doc)
                <li class="doc-item">
                    <a href="{{ route('app.documento', [$evento->id, $doc->id]) }}" target="_blank" rel="noopener" style="display:contents;">
                        <div class="doc-icon">
                            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                        </div>
                        <div class="doc-info">
                            <div class="doc-name">{{ $doc->file_name }}</div>
                            <div class="doc-size">{{ number_format($doc->size / 1024, 0, ',', '.') }} KB</div>
                        </div>
                        <span class="doc-open-icon">›</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
@endif

@endsection
