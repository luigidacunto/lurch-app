<?php

namespace App\Esami;

use App\Esami\Calcolo\Frazione;
use App\Models\Esame;

/**
 * Struttura della scheda a semaforo dallo snapshot dell'esame (testi, competenze, scala): unico punto per laboratori,
 * prova pratica personale (PWA) e prova pratica finale (pannello). Nessun testo o valore nel codice.
 */
class StrutturaSemaforo
{
    /**
     * @return array{competenze: array, scala: array<string, array>, codici: list<string>}
     */
    public static function per(Esame $esame): array
    {
        $semaforo = $esame->modello_snapshot['semaforo'];
        $codici = [];
        foreach ($semaforo['competenze'] as $c) {
            array_push($codici, ...array_column($c['voci'], 'codice'));
        }

        // ⚠️ jsonb non conserva l'ordine delle chiavi (rosso, verde, giallo): si ordina per valore crescente 0 → 0,25 → 1.
        $scala = $semaforo['scala'];
        uasort($scala, fn ($a, $b) => Frazione::da($a['valore'])->confronta(Frazione::da($b['valore'])));

        return ['competenze' => $semaforo['competenze'], 'scala' => $scala, 'codici' => $codici];
    }

    /** Chiave della scala (rosso/giallo/verde) per un valore salvato ("1.00" → verde), con confronto esatto. */
    public static function livello(array $scala, ?string $valore): ?string
    {
        if ($valore === null) {
            return null;
        }
        foreach ($scala as $chiave => $livello) {
            if (Frazione::da($valore)->confronta(Frazione::da($livello['valore'])) === 0) {
                return $chiave;
            }
        }

        return null;
    }
}
