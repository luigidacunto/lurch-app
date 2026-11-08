<?php

namespace App\Observers;

use App\Jobs\RicalcolaScadenzeQualificheSoci;
use App\Models\CatalogoCorsoValiditaRegionale;

class CatalogoCorsoValiditaRegionaleObserver
{
    public function saved(CatalogoCorsoValiditaRegionale $override): void
    {
        if (! $override->wasChanged(['scadenza_tipo', 'scadenza_durata'])) {
            return;
        }

        RicalcolaScadenzeQualificheSoci::dispatch(
            $override->catalogo_corso_id,
            $override->comitato_regionale_id
        );
    }

    public function deleted(CatalogoCorsoValiditaRegionale $override): void
    {
        // Override eliminato: la regola nazionale torna attiva, ricalcola
        RicalcolaScadenzeQualificheSoci::dispatch(
            $override->catalogo_corso_id,
            $override->comitato_regionale_id
        );
    }
}
