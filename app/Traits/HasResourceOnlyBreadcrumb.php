<?php

namespace App\Traits;

trait HasResourceOnlyBreadcrumb
{
    /**
     * Filament di default aggiunge "Lista" come secondo livello ("Risorsa › Lista") anche se
     * porta esattamente alla stessa pagina — un livello che non aggiunge nulla. Il nome della
     * risorsa da solo, non cliccabile (sei già su questa pagina). "Dashboard" come primo
     * livello arriva dall'override di resources/views/vendor/filament/components/breadcrumbs.blade.php,
     * non da qui.
     */
    public function getBreadcrumbs(): array
    {
        return [static::getResource()::getBreadcrumb()];
    }
}
