@extends('app.layout')

@section('title', 'Test inviato')

@push('styles')
@include('app.partials.esami-stili')
@endpush

@section('content')
<a href="{{ route('app.esami') }}" class="back-btn">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    I tuoi esami
</a>
<p class="page-title">Test inviato</p>
<p class="page-sub">{{ $esame->titolo }}</p>
<div class="vuoto">Hai concluso il test scritto. Le tue risposte sono state registrate e non si possono più modificare.</div>
@endsection
