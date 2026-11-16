<?php

namespace App\Traits;

use App\Exceptions\PostaRegionaleNonDisponibile;
use App\Services\PostaRegionale;
use Filament\Notifications\Notification;
use Illuminate\Mail\Mailer;

/** Pagine del pannello che inviano mail di eventi, staff e graduatorie con l'account della regione. */
trait UsaPostaRegionale
{
    /** Mailer della regione; se manca l'account avvisa l'operatore e restituisce null (invio da non fare). */
    protected function postaRegionale(?int $comitatoRegionaleId): ?Mailer
    {
        try {
            return PostaRegionale::perEvento($comitatoRegionaleId);
        } catch (PostaRegionaleNonDisponibile $e) {
            Notification::make()->title('Invio bloccato')->body($e->getMessage())->danger()->persistent()->send();

            return null;
        }
    }
}
