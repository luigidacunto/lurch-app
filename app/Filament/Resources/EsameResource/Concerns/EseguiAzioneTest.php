<?php

namespace App\Filament\Resources\EsameResource\Concerns;

use App\Esami\TestException;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/** Esecuzione comune delle azioni del test scritto: errore di dominio -> notifica e modale aperta, altrimenti esito. */
trait EseguiAzioneTest
{
    private function esegui(Action $action, \Closure $operazione, string $esito): void
    {
        try {
            $operazione();
        } catch (TestException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
            $action->halt();

            return;
        }
        $this->record->refresh();
        Notification::make()->title($esito)->success()->send();
    }
}
