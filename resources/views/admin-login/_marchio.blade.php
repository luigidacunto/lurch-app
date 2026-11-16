{{-- Intestazione delle pagine di accesso: regione del dominio richiamato (logo e nome) o solo «Croce Rossa Italiana». --}}
@php($regione = \App\Services\PortaleRegionale::daRichiesta())
<div style="display:flex; align-items:center; gap:10px;">
    @if($regione?->logoUrl())
        {{-- Logo ufficiale: solo ridimensionato in proporzione, mai ritagliato. --}}
        <span style="background:#fff; border-radius:6px; padding:3px; display:inline-flex;">
            <img src="{{ $regione->logoUrl() }}" alt="{{ $regione->nome }}" style="height:28px; width:auto; max-width:120px; object-fit:contain;">
        </span>
    @endif
    <p class="text-xs font-medium uppercase tracking-wide opacity-80">
        Croce Rossa Italiana{{ $regione ? ' · ' . $regione->nome : '' }}
    </p>
</div>
