<?php

namespace App\Services;

use App\Models\ComitatoLocale;
use App\Models\ComitatoRegionale;

/**
 * Centralizza la sostituzione dei segnaposto {titolare}, {rappresentante_legale},
 * {indirizzo}, {email} (dal comitato) e {responsabile} (da config) nei testi di PrivacyTemplate — prima duplicata in ogni
 * controller dei form pubblici.
 */
class PrivacyPlaceholderResolver
{
    public static function sostituisci(string $testo, ?ComitatoRegionale $comitatoRegionale, ?ComitatoLocale $comitatoLocale = null): string
    {
        $comitato = $comitatoLocale ?? $comitatoRegionale;
        $responsabile = config('app.privacy_responsabile') ?: '<informazione mancante>';

        // Nessun comitato di contesto (es. pagina pubblica generale /privacy): non
        // lasciamo il segnaposto vuoto, sostituiamo con una formula che resta
        // leggibile e rimanda al modulo specifico dove il dato compare per intero.
        if (! $comitato) {
            return strtr($testo, [
                '{titolare}'              => "il Comitato Croce Rossa Italiana presso cui è stata effettuata l'iscrizione o la candidatura",
                '{rappresentante_legale}' => 'il rappresentante legale del Comitato indicato in fase di iscrizione',
                '{indirizzo}'             => 'la sede del Comitato indicato in fase di iscrizione',
                '{email}'                 => "l'indirizzo email del Comitato indicato in fase di iscrizione",
                '{responsabile}'          => $responsabile,
            ]);
        }

        return strtr($testo, [
            '{titolare}'              => $comitato->nome ?: '<informazione mancante>',
            '{rappresentante_legale}' => $comitato->rappresentante_legale ?: '<informazione mancante>',
            '{indirizzo}'             => $comitato->indirizzo ?: '<informazione mancante>',
            '{email}'                 => $comitato->email ?: '<informazione mancante>',
            '{responsabile}'          => $responsabile,
        ]);
    }
}
