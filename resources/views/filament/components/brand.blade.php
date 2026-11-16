{{--
    Marchio del pannello. Con il logo della regione del dominio:
    - pagina larga: marchio L.U.R.C.H. a sinistra + logo della regione al centro della barra in alto;
    - pagina stretta (< 1024 px): solo il logo della regione, al posto del marchio L.U.R.C.H.
    Il logo è dentro il link del marchio (porta alla dashboard). Logo ufficiale: solo ridimensionato
    in proporzione, mai ritagliato. Nell'intestazione del menu laterale (telefono) resta solo L.U.R.C.H.
--}}
@php($regione = \App\Services\PortaleRegionale::daRichiesta())
@php($logoRegione = $regione?->logoUrl())
@once
    <style>
        .lurch-logo-regione { display: block; height: 60px; width: auto; max-width: 320px; object-fit: contain; }
        .fi-topbar .lurch-logo-regione { position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); }
        .fi-sidebar-header .lurch-logo-regione { display: none; }
        @media (max-width: 1023px) {
            .fi-topbar .lurch-marchio--con-logo-regione { display: none; }
            .fi-topbar .lurch-logo-regione { position: static; transform: none; height: 52px; }
        }
    </style>
@endonce
<div style="display:flex; align-items:center; gap:10px; padding:2px 0;">
    <div class="lurch-marchio{{ $logoRegione ? ' lurch-marchio--con-logo-regione' : '' }}" style="display:flex; align-items:center; gap:10px;">
        <img src="{{ asset('images/logo_lurch.png') }}" alt="LURCH" style="height:38px; width:38px; object-fit:contain;">
        <div style="display:flex; flex-direction:column; line-height:1.2;">
            <span style="font-size:1.15rem; font-weight:900; letter-spacing:0.15em; color:#dc2626;">L.U.R.C.H.</span>
            @if($regione && ! $logoRegione)
                <span style="font-size:0.7rem; font-weight:600; color:#6b7280;">{{ $regione->nome }}</span>
            @else
                <span style="font-size:0.58rem; font-weight:500; letter-spacing:0.05em; color:#9ca3af; text-transform:uppercase;">Logistica Unificata Registrazione Corsi e Hospitality</span>
            @endif
        </div>
    </div>
    @if($logoRegione)
        <img class="lurch-logo-regione" src="{{ $logoRegione }}" alt="{{ $regione->nome }}">
    @endif
</div>
