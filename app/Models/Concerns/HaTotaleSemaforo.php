<?php

namespace App\Models\Concerns;

use App\Esami\Calcolo\CalcoloEsameFactory;

/**
 * Totale di una scheda a semaforo (laboratorio, prova pratica personale o finale) dal motore di calcolo: un solo
 * punto, mai salvato. Richiede la relazione `voci` con `voce_codice` e `valore`.
 */
trait HaTotaleSemaforo
{
    /**
     * `punti` null = voci mancanti.
     *
     * @return array{punti: ?\App\Esami\Calcolo\Frazione, massimo: \App\Esami\Calcolo\Frazione, mancanti: list<string>}
     */
    public function totale(array $snapshot): array
    {
        $valori = $this->voci->mapWithKeys(fn ($v) => [$v->voce_codice => $v->valore === null ? null : (string) $v->valore])->all();

        return CalcoloEsameFactory::tcri()->totaleSemaforo($snapshot, $valori);
    }

    /** Totale formattato ("28,00") solo a scheda completa, altrimenti null; mai salvato. */
    public function totaleFormattato(array $snapshot): ?array
    {
        $t = $this->totale($snapshot);

        return $t['punti'] === null ? null : [
            'punti'   => str_replace('.', ',', $t['punti']->tronca(2)),
            'massimo' => str_replace('.', ',', $t['massimo']->tronca(2)),
        ];
    }
}
