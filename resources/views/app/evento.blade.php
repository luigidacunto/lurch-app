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

    .section-label {
        font-size: 11px; font-weight: 700; text-transform: uppercase;
        letter-spacing: 0.8px; color: #9ca3af; margin: 0 0 10px;
    }
    .hub-grid { display: grid; gap: 12px; }
    .hub-card {
        background: #fff; border-radius: 12px; padding: 16px;
        box-shadow: 0 1px 4px rgba(0,0,0,0.07);
        display: flex; align-items: center; gap: 14px;
        text-decoration: none; color: inherit;
        overflow: hidden;
    }
    .hub-card:active { background: #fafafa; }
    .hub-card-icon {
        width: 44px; height: 44px; background: #fee2e2; border-radius: 10px;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .hub-card-icon.staff-icon { background: #ffedd5; }
    .hub-card-icon svg { width: 22px; height: 22px; color: #C8102E; }
    .hub-card-icon.staff-icon svg { color: #ea580c; }
    .hub-card-body { flex: 1; min-width: 0; }
    .hub-card-title { font-size: 15px; font-weight: 700; color: #111827; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .hub-card-sub   { font-size: 12px; color: #6b7280; margin-top: 2px; }
    .hub-card-arrow { color: #d1d5db; font-size: 22px; flex-shrink: 0; }
    .form-stato {
        display: inline-block; font-size: 10px; font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.4px;
        padding: 2px 8px; border-radius: 8px; margin-top: 5px;
    }
    .form-stato-pending { background: #fef3c7; color: #92400e; }
    .form-stato-inviato { background: #dcfce7; color: #15803d; }
    .msg-badge {
        display: inline-flex; align-items: center; justify-content: center;
        background: #C8102E; color: #fff;
        font-size: 10px; font-weight: 800;
        min-width: 18px; height: 18px; border-radius: 9px;
        padding: 0 5px; margin-left: 6px; flex-shrink: 0;
    }
</style>
@endpush

@section('content')

<a href="{{ route('app.home') }}" class="back-btn">
    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
    I miei eventi
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
            <span>{{ $evento->sede }}@if($evento->indirizzo), {{ $evento->indirizzo }}@endif</span>
        </div>
    @endif
</div>

<p class="section-label">Sezioni</p>

<div class="hub-grid">

    {{-- Messaggi badge --}}
    <a href="{{ route('app.evento.messaggi', $evento) }}" class="hub-card">
        <div class="hub-card-icon">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12z"/></svg>
        </div>
        <div class="hub-card-body">
            <div class="hub-card-title" style="display:flex;align-items:center;">
                Messaggi
                @if($messaggiNonLetti > 0)
                    <span class="msg-badge">{{ $messaggiNonLetti }}</span>
                @endif
            </div>
            <div class="hub-card-sub">
                @if($messaggiNonLetti > 0)
                    {{ $messaggiNonLetti }} non {{ $messaggiNonLetti === 1 ? 'letto' : 'letti' }}
                    @if($messaggiTotali > $messaggiNonLetti)
                        · {{ $messaggiTotali }} totali
                    @endif
                @elseif($messaggiTotali > 0)
                    {{ $messaggiTotali }} {{ $messaggiTotali === 1 ? 'messaggio letto' : 'messaggi letti' }}
                @else
                    Messaggi ricevuti tramite il badge
                @endif
            </div>
        </div>
        <span class="hub-card-arrow">›</span>
    </a>

    {{-- Info evento --}}
    <a href="{{ route('app.evento.info', $evento) }}" class="hub-card">
        <div class="hub-card-icon">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
        </div>
        <div class="hub-card-body">
            <div class="hub-card-title">Info evento</div>
            <div class="hub-card-sub">Dettagli, allegati e documenti</div>
        </div>
        <span class="hub-card-arrow">›</span>
    </a>

    {{-- Form preregistrazione per ogni iscrizione corsista --}}
    @foreach($iscrizioni as $iscrizione)
        @php $formInviato = $iscrizione->rispostaPreregistrazione !== null; @endphp
        <a href="{{ route('app.evento.form.corsista', [$evento, $iscrizione]) }}" class="hub-card">
            <div class="hub-card-icon">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
            </div>
            <div class="hub-card-body" style="min-width:0;">
                <div class="hub-card-title">Preregistrazione corso{{ $iscrizione->corso->numero_protocollo ? ' ' . $iscrizione->corso->numero_protocollo : '' }}</div>
                <div class="hub-card-sub">Contatti, pasti, consensi</div>
                @if($formInviato)
                    <span class="form-stato form-stato-inviato">Inviato</span>
                @else
                    <span class="form-stato form-stato-pending">Da compilare</span>
                @endif
            </div>
            <span class="hub-card-arrow">›</span>
        </a>
    @endforeach

    {{-- Form preregistrazione staff --}}
    @if($isStaff && $iscrizioneStaff)
        @php $formStaffInviato = $iscrizioneStaff->rispostaPreregistrazione !== null; @endphp
        <a href="{{ route('app.evento.form.staff', [$evento, $iscrizioneStaff]) }}" class="hub-card">
            <div class="hub-card-icon staff-icon">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
            </div>
            <div class="hub-card-body">
                <div class="hub-card-title">Preregistrazione staff</div>
                <div class="hub-card-sub">Contatti, disponibilità, pasti</div>
                @if($formStaffInviato)
                    <span class="form-stato form-stato-inviato">Inviato</span>
                @else
                    <span class="form-stato form-stato-pending">Da compilare</span>
                @endif
            </div>
            <span class="hub-card-arrow">›</span>
        </a>
    @endif

</div>

@endsection
