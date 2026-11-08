@extends('app.layout')

@section('title', 'Messaggi — ' . $evento->nome)

@push('styles')
<style>
    .back-btn {
        display: inline-flex; align-items: center; gap: 6px;
        color: #C8102E; font-size: 14px; font-weight: 600;
        text-decoration: none; margin-bottom: 16px;
    }
    .back-btn svg { width: 16px; height: 16px; }
    .page-title {
        font-size: 20px; font-weight: 800; color: #111827;
        margin-bottom: 4px;
    }
    .page-sub {
        font-size: 13px; color: #6b7280; margin-bottom: 20px;
    }
    .msg-list { display: grid; gap: 10px; }
    .msg-card {
        background: #fff; border-radius: 12px;
        box-shadow: 0 1px 4px rgba(0,0,0,0.07);
        padding: 16px; text-decoration: none; color: inherit;
        display: flex; align-items: flex-start; gap: 12px;
        position: relative;
    }
    .msg-card:active { background: #fafafa; }
    .msg-dot {
        width: 10px; height: 10px; border-radius: 50%;
        background: #C8102E; flex-shrink: 0; margin-top: 5px;
    }
    .msg-dot.letto { background: transparent; }
    .msg-body { flex: 1; min-width: 0; }
    .msg-data {
        font-size: 11px; color: #9ca3af; margin-bottom: 4px;
        font-variant-numeric: tabular-nums;
    }
    .msg-preview {
        font-size: 14px; color: #374151;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        font-weight: 600;
    }
    .msg-preview.letto { font-weight: 400; color: #6b7280; }
    .msg-arrow { color: #d1d5db; font-size: 20px; flex-shrink: 0; align-self: center; }
    .empty {
        text-align: center; padding: 48px 16px;
        color: #9ca3af; font-size: 14px; line-height: 1.6;
    }
    .empty-icon { font-size: 40px; margin-bottom: 12px; }
</style>
@endpush

@section('content')

<a href="{{ route('app.evento', $evento) }}" class="back-btn">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    {{ $evento->nome }}
</a>

<div class="page-title">Messaggi</div>
<div class="page-sub">Messaggi anonimi ricevuti tramite il tuo badge</div>

@if($messaggi->isEmpty())
    <div class="empty">
        <div class="empty-icon">📭</div>
        Nessun messaggio ricevuto ancora.<br>
        Chi scansiona il tuo badge QR può lasciarti un messaggio qui.
    </div>
@else
    <div class="msg-list">
        @foreach($messaggi as $msg)
            @php
                $letto = $msg->letto_at !== null;
                $preview = strip_tags($msg->corpo);
                $preview = mb_strlen($preview) > 80 ? mb_substr($preview, 0, 80) . '…' : $preview;
            @endphp
            <a href="{{ route('app.evento.messaggi.show', [$evento, $msg]) }}" class="msg-card">
                <div class="msg-dot {{ $letto ? 'letto' : '' }}"></div>
                <div class="msg-body">
                    <div class="msg-data">{{ $msg->created_at->translatedFormat('l j F Y, H:i') }}</div>
                    <div class="msg-preview {{ $letto ? 'letto' : '' }}">{{ $preview ?: '(messaggio vuoto)' }}</div>
                </div>
                <span class="msg-arrow">›</span>
            </a>
        @endforeach
    </div>
@endif

@endsection
