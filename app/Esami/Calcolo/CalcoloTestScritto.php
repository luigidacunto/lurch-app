<?php

declare(strict_types=1);

namespace App\Esami\Calcolo;

use InvalidArgumentException;

/**
 * Punteggio del test scritto: meccanismo condiviso tra i tipi di esame, indipendente da pesi e
 * formula del singolo esame (la formula del TCRI lo usa, non il contrario).
 */
final class CalcoloTestScritto
{
    /**
     * @param  array<int|string, array>  $domande  id domanda => ['corretta' => int (base 0), 'punti' => ?, 'penalita_errata' => ?, 'penalita_vuota' => ?, 'n_opzioni' => ?int]
     *                                             (punti default 1, penalità default 0; valori int o stringhe decimali, es. "1.00";
     *                                             n_opzioni facoltativo: se presente, corretta e scelta devono stare in 0..n-1)
     * @param  array<int|string, int|null>|null  $risposte  id domanda => indice dell'opzione scelta, null = lasciata vuota;
     *                                                       null (l'intero argomento) = test non consegnato
     * @return array{punti: ?Frazione, massimo: Frazione}  punti null se non consegnato; mai sotto 0
     */
    public function calcola(array $domande, ?array $risposte): array
    {
        $massimo = Frazione::di(0);
        $totale = Frazione::di(0);

        foreach ($domande as $id => $domanda) {
            // Indici delle opzioni in base 0. Con `n_opzioni` la corretta e la scelta vanno controllate nel campo 0..n-1
            // (l'app passa sempre 4); senza, nessun controllo (compatibilità con i chiamanti che non lo conoscono).
            if (isset($domanda['n_opzioni']) && (! is_int($domanda['corretta']) || $domanda['corretta'] < 0 || $domanda['corretta'] >= $domanda['n_opzioni'])) {
                throw new InvalidArgumentException("Risposta corretta fuori campo (0..".($domanda['n_opzioni'] - 1).") alla domanda {$id}.");
            }
            $punti = Frazione::da($domanda['punti'] ?? 1);
            $massimo = $massimo->somma($punti);

            if ($risposte === null) {
                continue;
            }
            if (! array_key_exists($id, $risposte)) {
                throw new InvalidArgumentException("Manca la risposta (anche vuota) alla domanda {$id}.");
            }
            $scelta = $risposte[$id];
            if ($scelta === null) {
                $totale = $totale->sottrai(Frazione::da($domanda['penalita_vuota'] ?? 0));
            } elseif (! is_int($scelta)) {
                throw new InvalidArgumentException("Risposta non valida alla domanda {$id}.");
            } elseif (isset($domanda['n_opzioni']) && ($scelta < 0 || $scelta >= $domanda['n_opzioni'])) {
                throw new InvalidArgumentException("Opzione fuori campo (0..".($domanda['n_opzioni'] - 1).") alla domanda {$id}.");
            } elseif ($scelta === $domanda['corretta']) {
                $totale = $totale->somma($punti);
            } else {
                $totale = $totale->sottrai(Frazione::da($domanda['penalita_errata'] ?? 0));
            }
        }

        if ($risposte !== null) {
            $estranee = array_diff_key($risposte, $domande);
            if ($estranee !== []) {
                throw new InvalidArgumentException('Risposte a domande non presenti nel test: '.implode(', ', array_keys($estranee)));
            }
            if ($totale->confronta(Frazione::di(0)) < 0) {
                $totale = Frazione::di(0);
            }
        }

        return ['punti' => $risposte === null ? null : $totale, 'massimo' => $massimo];
    }
}
