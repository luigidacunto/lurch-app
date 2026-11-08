<?php

declare(strict_types=1);

namespace App\Esami\Calcolo;

/**
 * Contratto comune dei calcoli per tipo di esame: CalcoloEsameFactory sceglie l'implementazione
 * dal codice del tipo (esami.tipo_codice), così chi chiama non conosce la classe concreta.
 */
interface CalcoloEsame
{
    /**
     * @param  array  $snapshot  struttura del modello (esami.modello_snapshot / JSON del modello)
     * @param  array  $dati  dati grezzi dell'esame
     */
    public function calcola(array $snapshot, array $dati): array;
}
