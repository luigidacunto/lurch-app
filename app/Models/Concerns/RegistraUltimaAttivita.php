<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Data/ora dell'ultima attività, nella colonna indicata da COLONNA_ULTIMA_ATTIVITA del modello.
 * Scrive al massimo una volta ogni 5 minuti per record: un caricamento di pagina conta come
 * attività senza una scrittura sul DB a ogni richiesta (precisione: ~5 minuti).
 */
trait RegistraUltimaAttivita
{
    public function registraAttivita(): void
    {
        $colonna = static::COLONNA_ULTIMA_ATTIVITA;

        if ($this->{$colonna} && $this->{$colonna}->gt(now()->subMinutes(5))) {
            return;
        }

        $this->{$colonna} = now();

        // Query diretta: non tocca updated_at e non scatena eventi/observer del modello.
        DB::table($this->getTable())
            ->where($this->getKeyName(), $this->getKey())
            ->update([$colonna => $this->{$colonna}]);
    }
}
