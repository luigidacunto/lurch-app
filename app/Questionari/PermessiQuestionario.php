<?php

namespace App\Questionari;

use App\Models\Corso;
use App\Models\QuestionarioGradimento;
use App\Models\User;
use App\Services\SocioCorrente;

/**
 * Chi gestisce il questionario di un corso (configura, invia, vede tutti i risultati): superadmin, admin regionale
 * nella sua regione, admin locale nel suo comitato, direttore del corso con qualsiasi ruolo (prevale l'essere
 * direttore). Gli operatori no, nemmeno se assegnati all'evento. I docenti vedono la propria sezione solo nell'app.
 */
class PermessiQuestionario
{
    public static function puoGestire(?User $user, Corso $corso): bool
    {
        if (! $user) {
            return false;
        }
        if ($user->isSuperadmin()) {
            return true;
        }

        $socioId = SocioCorrente::perUtente($user);
        if ($socioId && $corso->direttore_socio_id === $socioId) {
            return true;
        }

        $evento = $corso->evento;

        return match (true) {
            $user->isAdmin()       => $evento?->comitato_regionale_id === $user->comitato_regionale_id,
            $user->isAdminLocale() => $evento?->comitato_locale_id !== null && $evento->comitato_locale_id === $user->comitato_locale_id,
            default                => false,
        };
    }

    /** Indice del socio tra i valutati del questionario (S9), null se non è valutato. */
    public static function indiceValutato(QuestionarioGradimento $questionario, ?int $socioId): ?int
    {
        if (! $socioId) {
            return null;
        }
        foreach ($questionario->valutati ?? [] as $i => $v) {
            if (($v['socio_id'] ?? null) === $socioId) {
                return $i;
            }
        }

        return null;
    }
}
