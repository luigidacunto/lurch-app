{{-- Fascia rossa delle pagine di accesso: solo testo. Il logo della regione, se c'è, sta nella parte
     bianca (admin-login/_logo-regione); senza logo il nome della regione si legge qui. --}}
@php($regione = \App\Services\PortaleRegionale::daRichiesta())
<p class="text-xs font-medium uppercase tracking-wide opacity-80">
    Croce Rossa Italiana{{ $regione && ! $regione->logoUrl() ? ' · ' . $regione->nome : '' }}
</p>
