<?php

namespace App\Services;

use App\Models\ConflittoIscrizione;
use App\Models\Iscrizione;

/**
 * Ricalcolo dei conflitti di date tra le iscrizioni di un socio (`conflitti_iscrizioni`).
 * Regola (Corso::sovrapponeInPresenza): due corsi sono in conflitto se hanno almeno un giorno in
 * presenza in comune, nello stesso evento o in eventi diversi. Usato sia dal job incrementale
 * (CheckSovrapposizioniSocio) sia da quello globale notturno, così la regola resta in un punto solo.
 */
class SovrapposizioniCorsi
{
    public static function ricalcola(int $socioId): void
    {
        // Le iscrizioni ritirate non contano: un ritiro da uno dei due corsi risolve la sovrapposizione.
        $iscrizioni = Iscrizione::where('partecipante_id', $socioId)
            ->where('stato', '!=', 'ritirato')
            ->with('corso.evento:id,giorni_evento')
            ->get()
            ->filter(fn ($i) => $i->corso)
            ->values();

        ConflittoIscrizione::where('partecipante_id', $socioId)->delete();

        $nuovi = [];

        for ($i = 0; $i < $iscrizioni->count(); $i++) {
            for ($j = $i + 1; $j < $iscrizioni->count(); $j++) {
                $a = $iscrizioni[$i];
                $b = $iscrizioni[$j];

                if (! $a->corso->sovrapponeInPresenza($b->corso)) {
                    continue;
                }

                [$idMin, $idMax] = $a->id < $b->id ? [$a, $b] : [$b, $a];

                $nuovi[] = [
                    'partecipante_id' => $socioId,
                    'iscrizione_a_id' => $idMin->id,
                    'iscrizione_b_id' => $idMax->id,
                    'corso_a_id'      => $idMin->corso_id,
                    'corso_b_id'      => $idMax->corso_id,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ];
            }
        }

        if ($nuovi) {
            ConflittoIscrizione::insert($nuovi);
        }
    }
}
