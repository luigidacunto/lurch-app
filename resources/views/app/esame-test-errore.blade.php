@extends('app.layout')

@section('title', 'Test scritto')

@push('styles')
@include('app.partials.esami-stili')
@endpush

@section('content')
<a href="{{ route('app.esame.test', $esame) }}" class="back-btn">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    Torna al test
</a>
<p class="page-title">Test scritto</p>
<p class="page-sub">{{ $esame->titolo }}</p>
<div class="avviso-errore">{{ $messaggio }}</div>
@endsection
