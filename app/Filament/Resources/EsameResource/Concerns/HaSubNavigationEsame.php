<?php

namespace App\Filament\Resources\EsameResource\Concerns;

use App\Filament\Resources\EsameResource;
use App\Models\Esame;

/** Sub-nav in alto e controllo d'accesso comune alle pagine di un esame. */
trait HaSubNavigationEsame
{
    public function getSubNavigation(): array
    {
        return static::getResource()::getRecordSubNavigation($this);
    }

    public static function canAccess(array $parameters = []): bool
    {
        // Filament sonda canAccess() senza argomenti all'avvio: no-op, il controllo vero ha il record.
        if (! array_key_exists('record', $parameters)) {
            return true;
        }
        $record = $parameters['record'];

        return $record instanceof Esame && EsameResource::canEdit($record);
    }
}
