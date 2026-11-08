{{-- Icona «i» accanto al nome del docente: apre in sola lettura la sua annotazione sulla scheda di laboratorio.
     Solo modale lato client (nessuna Action: non scrive nulla). Parametri: $docente, $testo, $titolo. --}}
<x-filament::modal width="lg" :heading="$docente" :description="$titolo">
    <x-slot name="trigger">
        <x-filament::icon-button icon="heroicon-o-information-circle" size="sm" color="info"
            label="Annotazione di {{ $docente }}" tooltip="Annotazione del docente" />
    </x-slot>
    <div style="white-space:pre-line; overflow-wrap:anywhere;">{{ $testo }}</div>
</x-filament::modal>
