<?php

namespace App\Http\Controllers\App\Concerns;

use App\Esami\Formato;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Annotazione libera del docente sulla PROPRIA scheda personale (laboratorio, itinere): appunto, mai nel calcolo né
 * negli export. La leggono solo l'autore e il direttore (RiepilogoItinere con $conAnnotazioni). Se la scheda non
 * esiste ancora la crea: una scheda senza voti è già uno stato valido (il motore la ignora). I permessi li controlla il chiamante.
 */
trait SalvaAnnotazione
{
    public const ANNOTAZIONE_MAX = 1000;

    /** @param class-string<Model> $modello  @param array<string, int> $attributi chiave univoca della scheda */
    private function salvaAnnotazioneSu(Request $request, string $modello, array $attributi, int $max = self::ANNOTAZIONE_MAX): JsonResponse
    {
        $dati = $request->validate([
            'annotazione' => ['present', 'nullable', 'string', 'max:'.$max],
        ]);
        $valore = ['annotazione' => Formato::testo($dati['annotazione'])];

        try {
            $modello::updateOrCreate($attributi, $valore);
        } catch (UniqueConstraintViolationException) {
            // Un voto e la nota hanno creato la scheda insieme: il vincolo univoco ha tenuto, aggiorno la riga.
            $modello::where($attributi)->firstOrFail()->update($valore);
        }

        return response()->json(['ok' => true]);
    }
}
