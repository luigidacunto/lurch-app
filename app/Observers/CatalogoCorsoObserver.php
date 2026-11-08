<?php

namespace App\Observers;

use App\Jobs\RicalcolaScadenzeQualificheSoci;
use App\Models\CatalogoCorso;

class CatalogoCorsoObserver
{
    public function updated(CatalogoCorso $corso): void
    {
        if ($corso->wasChanged('codice')) {
            $this->aggiornaCodiceNeiRiferimenti(
                $corso->getOriginal('codice'),
                $corso->codice
            );
        }

        if ($corso->wasChanged(['scadenza_tipo', 'scadenza_durata'])) {
            RicalcolaScadenzeQualificheSoci::dispatch($corso->id);
        }
    }

    private function aggiornaCodiceNeiRiferimenti(string $vecchio, string $nuovo): void
    {
        foreach (['corsi_aggiornamento', 'eredita_scadenza_da_corsi'] as $campo) {
            CatalogoCorso::whereJsonContains($campo, $vecchio)
                ->get()
                ->each(function (CatalogoCorso $c) use ($campo, $vecchio, $nuovo) {
                    $c->$campo = collect($c->$campo)
                        ->map(fn ($codice) => $codice === $vecchio ? $nuovo : $codice)
                        ->values()
                        ->toArray();
                    $c->saveQuietly();
                });
        }
    }
}
