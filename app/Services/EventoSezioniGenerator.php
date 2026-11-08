<?php

namespace App\Services;

use App\Models\Evento;
use Carbon\Carbon;

/**
 * Genera le sezioni pasto/pernotto e gli slot di disponibilità (mattina/pomeriggio)
 * per i giorni di un evento — unica fonte per i form staff, i pannelli operatore e
 * il check-in, sostituisce la stessa logica che prima era duplicata in ~8 file.
 *
 * "Blocco" = gruppo di giorni consecutivi in Evento::giorniEvento() (con giorni non
 * contigui un evento può avere più blocchi, es. due weekend separati).
 */
class EventoSezioniGenerator
{
    private const TIPO_LABEL = ['colazione' => 'Colazione', 'pranzo' => 'Pranzo', 'cena' => 'Cena', 'pernotto' => 'Pernotto'];
    private const TIPO_ORDINE = ['colazione' => 0, 'pranzo' => 1, 'cena' => 2, 'pernotto' => 3];

    /**
     * @param bool $bypassResidenziale true = contesto operatore (pannello/check-in): cena/pernotto
     *             sempre disponibili su ogni giorno reale, indipendentemente da Evento::residenziale.
     *             false = pianificazione lato staff: cena/pernotto disponibili solo se Evento::residenziale.
     * @param bool $includeColazione   true solo nei contesti di check-in/presenze effettive (registra un
     *             fatto reale). false in ogni contesto di pianificazione — lì la colazione non è mai un
     *             campo da compilare, si deduce nei report dal pernotto dichiarato/confermato.
     * @return array<int, array{tipo:string, data:string, chiave:string, label:string}>
     */
    public static function perGiorni(Evento $evento, bool $bypassResidenziale = false, bool $includeColazione = false): array
    {
        return self::perElencoGiorni($evento->giorniEvento(), $bypassResidenziale || $evento->residenziale, $includeColazione);
    }

    /**
     * Stessa logica di perGiorni() su un elenco di giorni arbitrario (es. `corsi.giorni_presenza`,
     * sottoinsieme dei giorni evento) — mai un range data_inizio→data_fine.
     *
     * @param \Illuminate\Support\Collection<int, Carbon> $giorni ordinati cronologicamente
     */
    public static function perElencoGiorni(\Illuminate\Support\Collection $giorni, bool $pernottoDisponibile, bool $includeColazione = false): array
    {
        $sezioni = [];
        $viste   = [];

        $aggiungi = function (string $tipo, Carbon $data, ?string $suffisso = null) use (&$sezioni, &$viste): void {
            $chiave = $tipo . '_' . $data->format('Y-m-d');
            if (isset($viste[$chiave])) return;
            $viste[$chiave] = true;
            $label = self::TIPO_LABEL[$tipo] . ' · ' . $data->translatedFormat('l j F') . ($suffisso ? " ({$suffisso})" : '');
            $sezioni[] = ['tipo' => $tipo, 'data' => $data->format('Y-m-d'), 'chiave' => $chiave, 'label' => $label];
        };

        foreach ($giorni as $i => $giorno) {
            $precedente  = $giorni->get($i - 1);
            $haNotteFrom = $precedente && (int) abs($giorno->diffInDays($precedente)) === 1;

            if ($includeColazione && $haNotteFrom) {
                $aggiungi('colazione', $giorno);
            }
            $aggiungi('pranzo', $giorno);

            if ($pernottoDisponibile) {
                $aggiungi('cena', $giorno);
                $aggiungi('pernotto', $giorno);

                if (! $haNotteFrom) {
                    // Inizio di un blocco: arrivo anticipato la sera prima.
                    $giornoPrima = $giorno->copy()->subDay();
                    $aggiungi('cena', $giornoPrima, 'giorno prima');
                    $aggiungi('pernotto', $giornoPrima, 'giorno prima');
                }
            }
        }

        usort($sezioni, fn ($a, $b) => $a['data'] <=> $b['data'] ?: self::TIPO_ORDINE[$a['tipo']] <=> self::TIPO_ORDINE[$b['tipo']]);

        return $sezioni;
    }

    /**
     * Slot di disponibilità mattina/pomeriggio per lo staff: i giorni dell'evento più,
     * per ogni blocco, il pomeriggio del giorno prima (montaggio) e la mattina del
     * giorno dopo (smontaggio). Solo per lo staff — i corsisti non hanno questo concetto.
     *
     * @return array<int, array{data:string, porzione:string, chiave:string, label:string}>
     */
    public static function slotDisponibilitaStaff(Evento $evento): array
    {
        $giorni = $evento->giorniEvento();
        $slots  = [];
        $viste  = [];

        $aggiungiSlot = function (Carbon $data, string $porzione) use (&$slots, &$viste): void {
            $chiave = $data->format('Y-m-d') . '_' . $porzione;
            if (isset($viste[$chiave])) return;
            $viste[$chiave] = true;
            $slots[] = [
                'data'     => $data->format('Y-m-d'),
                'porzione' => $porzione,
                'chiave'   => $chiave,
                'label'    => $data->translatedFormat('l j F') . ' — ' . ($porzione === 'mattina' ? 'Mattina' : 'Pomeriggio'),
            ];
        };

        foreach ($giorni as $i => $giorno) {
            $precedente  = $giorni->get($i - 1);
            $successivo  = $giorni->get($i + 1);
            $haNotteFrom = $precedente && (int) abs($giorno->diffInDays($precedente)) === 1;
            $haNotteTo   = $successivo && (int) abs($successivo->diffInDays($giorno)) === 1;

            $aggiungiSlot($giorno, 'mattina');
            $aggiungiSlot($giorno, 'pomeriggio');

            if (! $haNotteFrom) {
                $aggiungiSlot($giorno->copy()->subDay(), 'pomeriggio');
            }
            if (! $haNotteTo) {
                $aggiungiSlot($giorno->copy()->addDay(), 'mattina');
            }
        }

        usort($slots, fn ($a, $b) => $a['data'] <=> $b['data'] ?: ($a['porzione'] === 'mattina' ? 0 : 1) <=> ($b['porzione'] === 'mattina' ? 0 : 1));

        return $slots;
    }
}
