{{-- Logo della regione del dominio richiamato, in cima alla parte bianca delle pagine di accesso.
     Logo ufficiale: solo ridimensionato in proporzione, mai ritagliato. --}}
@php($regione = \App\Services\PortaleRegionale::daRichiesta())
@if($regione?->logoUrl())
    <div style="display:flex; justify-content:center; margin-bottom:16px;">
        <img src="{{ $regione->logoUrl() }}" alt="{{ $regione->nome }}" style="height:110px; width:auto; max-width:100%; object-fit:contain;">
    </div>
@endif
