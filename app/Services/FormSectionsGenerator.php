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
 *
 * Pasti non forniti dall'organizzazione (`corsi.pasti_non_gestiti`, chiavi `tipo_Y-m-d`, solo
 * pranzo/cena): il giorno resta in presenza ma quel pasto non diventa una voce del form, quindi
 * non entra in report, griglie e check-in. Il form ne mostra solo una nota informativa.
 */
class FormSectionsGenerator
{
    private const PERNOTTO_OPZIONI = ['tenda', 'camerata', 'camera singola', 'camera doppia', 'altro'];

    /** Tipi che si possono segnare come non forniti dall'organizzazione. */
    public const TIPI_ESCLUDIBILI = ['pranzo', 'cena'];

    /** Voci del form: tutte quelle generate, meno i pasti non forniti. */
    public function calcola(Corso $corso): array
    {
        $esclusi = array_flip($corso->pasti_non_gestiti ?? []);

        return array_values(array_filter(
            $this->tutte($corso),
            fn ($s) => ! isset($esclusi[$s['tipo'] . '_' . $s['data']]),
        ));
    }

    /**
     * Pasti segnabili come non forniti, `chiave => label` (pranzi e cene generati dai giorni).
     *
     * @return array<string, string>
     */
    public function pastiEscludibili(Corso $corso): array
    {
        return collect($this->tutte($corso))
            ->filter(fn ($s) => in_array($s['tipo'], self::TIPI_ESCLUDIBILI, true))
            ->mapWithKeys(fn ($s) => [$s['tipo'] . '_' . $s['data'] => $s['label']])
            ->all();
    }

    /**
     * Pasti non forniti effettivi (solo quelli che corrispondono a una voce generata), per la nota del form.
     *
     * @return array<string, string> chiave => label
     */
    public function pastiNonForniti(Corso $corso): array
    {
        return array_intersect_key($this->pastiEscludibili($corso), array_flip($corso->pasti_non_gestiti ?? []));
    }

    /** Tutte le voci generate dai giorni in presenza, senza tenere conto dei pasti non forniti. */
    private function tutte(Corso $corso): array
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
