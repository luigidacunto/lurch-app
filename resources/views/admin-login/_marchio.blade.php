{{-- Intestazione delle pagine di accesso: logo della regione del dominio richiamato (contiene già il nome),
     altrimenti «Croce Rossa Italiana · <regione>», o solo «Croce Rossa Italiana» sull'indirizzo generale. --}}
@php($regione = \App\Services\PortaleRegionale::daRichiesta())
@if($regione?->logoUrl())
    {{-- Logo ufficiale: solo ridimensionato in proporzione, mai ritagliato. --}}
    <span style="background:#fff; border-radius:6px; padding:4px 8px; display:inline-flex;">
        <img src="{{ $regione->logoUrl() }}" alt="{{ $regione->nome }}" style="height:40px; width:auto; max-width:220px; object-fit:contain;">
    </span>
@else
    <p class="text-xs font-medium uppercase tracking-wide opacity-80">
        Croce Rossa Italiana{{ $regione ? ' · ' . $regione->nome : '' }}
    </p>
@endif
