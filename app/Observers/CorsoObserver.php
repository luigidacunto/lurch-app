<?php

namespace App\Observers;

use App\Jobs\CheckSovrapposizioniSocio;
use App\Models\Corso;

class CorsoObserver
{
    /**
     * Date modificate: ricalcola i conflitti per tutti gli iscritti al corso.
     * Usa getChanges() che contiene gli attributi effettivamente cambiati dopo il save.
     */
    public function updated(Corso $corso): void
    {
        $cambiati = array_keys($corso->getChanges());

        if (array_intersect(['data_inizio', 'data_fine', 'giorni_presenza'], $cambiati)) {
            $corso->iscrizioni()
                ->whereNotNull('partecipante_id')
                ->pluck('partecipante_id')
                ->unique()
                ->each(fn ($id) => CheckSovrapposizioniSocio::dispatch($id));
        }
    }
}
