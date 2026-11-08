<?php

namespace App\Services;

use App\Models\Corso;
use Carbon\Carbon;

/**
 * Genera le sezioni pasto del form di preregistrazione discenti, in `corsi.form_sections` (jsonb).
 *
 * Fonte dei giorni: `corsi.giorni_presenza` — le date in presenza al campo dell'evento
 * scelte esplicitamente dall'operatore (sottoinsieme di `Evento::giorni_evento`). NON si
 * usa più l'intervallo data_inizio→data_fine: quello comprende anche la parte online e
 * generava un pasto per ogni giorno di calendario.
 *
 * Regole:
 *   PRANZO   — su ogni giorno selezionato, sempre.
 *   CENA     — solo se corso.residenziale e il giorno ha un altro giorno selezionato subito
 *              dopo (D+1): si dorme per proseguire il giorno successivo.
 *   PERNOTTO — stessa condizione della cena.
 *   COLAZIONE— mai generata: nei report si deduce dal pernotto (potenziale/previsto/effettivo).
 *   Nessun "giorno prima" / "cena ultimo giorno": sono eccezioni che aggiunge solo
 *   l'operatore dal pannello evento. Lo staff ha una logica sua (EventoSezioniGenerator).
 */
class FormSectionsGenerator
{
    private const PERNOTTO_OPZIONI = ['tenda', 'camerata', 'camera singola', 'camera doppia', 'altro'];

    public function calcola(Corso $corso): array
    {
        $giorni = collect($corso->giorni_presenza ?? [])
            ->map(fn ($d) => Carbon::parse($d)->startOfDay())
            ->unique(fn ($d) => $d->format('Y-m-d'))
            ->sortBy(fn ($d) => $d->format('Y-m-d'))
            ->values();

        if ($giorni->isEmpty()) {
            return [];
        }

        $selezionati = $giorni->map(fn ($d) => $d->format('Y-m-d'))->flip();
        $sezioni = [];

        foreach ($giorni as $giorno) {
            $dataKey = $giorno->format('Y-m-d');
            $label   = $giorno->translatedFormat('l j F');

            $sezioni[] = [
                'tipo'         => 'pranzo',
                'data'         => $dataKey,
                'label'        => "Pranzo — {$label}",
                'attivo'       => true,
                'obbligatorio' => false,
            ];

            if (! $corso->residenziale) {
                continue;
            }

            $haGiornoDopo = isset($selezionati[$giorno->copy()->addDay()->format('Y-m-d')]);
            if (! $haGiornoDopo) {
                continue;
            }

            $sezioni[] = [
                'tipo'         => 'cena',
                'data'         => $dataKey,
                'label'        => "Cena — {$label}",
                'attivo'       => true,
                'obbligatorio' => false,
            ];

            $labelNotte = $giorno->translatedFormat('j F') . ' → ' . $giorno->copy()->addDay()->translatedFormat('j F');
            $sezioni[] = [
                'tipo'         => 'pernotto',
                'data'         => $dataKey,
                'label'        => "Pernotto — notte {$labelNotte}",
                'attivo'       => true,
                'obbligatorio' => false,
                'opzioni'      => self::PERNOTTO_OPZIONI,
            ];
        }

        return $sezioni;
    }
}
