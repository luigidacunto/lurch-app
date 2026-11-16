@php($regione = \App\Services\PortaleRegionale::daRichiesta())
<div style="display:flex; align-items:center; gap:10px; padding:2px 0;">
    <img src="{{ asset('images/logo_lurch.png') }}" alt="LURCH" style="height:38px; width:38px; object-fit:contain;">
    @if($regione?->logoUrl())
        {{-- Logo della regione del dominio (contiene già il nome): solo ridimensionato in proporzione, mai ritagliato. --}}
        <img src="{{ $regione->logoUrl() }}" alt="{{ $regione->nome }}" style="height:38px; width:auto; max-width:140px; object-fit:contain;">
    @endif
    <div style="display:flex; flex-direction:column; line-height:1.2;">
        <span style="font-size:1.15rem; font-weight:900; letter-spacing:0.15em; color:#dc2626;">L.U.R.C.H.</span>
        @if($regione && ! $regione->logoUrl())
            <span style="font-size:0.7rem; font-weight:600; color:#6b7280;">{{ $regione->nome }}</span>
        @else
            <span style="font-size:0.58rem; font-weight:500; letter-spacing:0.05em; color:#9ca3af; text-transform:uppercase;">Logistica Unificata Registrazione Corsi e Hospitality</span>
        @endif
    </div>
</div>
