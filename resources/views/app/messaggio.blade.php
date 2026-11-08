@extends('app.layout')

@section('title', 'Messaggio ricevuto')

@push('styles')
<style>
    .back-btn {
        display: inline-flex; align-items: center; gap: 6px;
        color: #C8102E; font-size: 14px; font-weight: 600;
        text-decoration: none; margin-bottom: 16px;
    }
    .back-btn svg { width: 16px; height: 16px; }
    .msg-card {
        background: #fff; border-radius: 12px;
        box-shadow: 0 1px 4px rgba(0,0,0,0.07);
        overflow: hidden;
    }
    .msg-meta {
        background: #f9fafb; border-bottom: 1px solid #f3f4f6;
        padding: 12px 16px;
        font-size: 12px; color: #6b7280;
        display: flex; align-items: center; gap: 6px;
    }
    .msg-meta svg { width: 14px; height: 14px; color: #9ca3af; flex-shrink: 0; }
    .msg-corpo {
        padding: 20px 20px;
        font-size: 15px; color: #111827; line-height: 1.7;
    }
    .msg-corpo p { margin-bottom: 0.8em; }
    .msg-corpo p:last-child { margin-bottom: 0; }
    .msg-corpo ul, .msg-corpo ol { padding-left: 20px; margin-bottom: 0.8em; }
    .anonimo {
        margin-top: 20px;
        background: #f3f4f6; border-radius: 10px;
        padding: 12px 14px;
        font-size: 12px; color: #6b7280; line-height: 1.5;
        display: flex; gap: 8px; align-items: flex-start;
    }
    .anonimo span { font-size: 14px; flex-shrink: 0; }
</style>
@endpush

@section('content')

<a href="{{ route('app.evento.messaggi', $evento) }}" class="back-btn">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    Messaggi
</a>

<div class="msg-card">
    <div class="msg-meta">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zm4.24 16L12 15.45 7.77 18l1.12-4.81-3.73-3.23 4.92-.42L12 5l1.92 4.53 4.92.42-3.73 3.23L16.23 18z"/></svg>
        Ricevuto il {{ $messaggio->created_at->translatedFormat('l j F Y \a\l\l\e H:i') }}
    </div>
    <div class="msg-corpo">{!! $messaggio->corpo !!}</div>
</div>

<div class="anonimo">
    <span>🔒</span>
    <div>Questo messaggio è anonimo. Il mittente avrebbe dovuto firmarsi nel testo se voleva essere riconosciuto.</div>
</div>

@endsection
