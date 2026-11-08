<?php

declare(strict_types=1);

namespace App\Esami\Calcolo;

use InvalidArgumentException;

final class CalcoloEsameFactory
{
    public static function per(string $tipoCodice): CalcoloEsame
    {
        return match ($tipoCodice) {
            'TCRI' => self::tcri(),
            default => throw new InvalidArgumentException("Tipo di esame senza calcolo: '{$tipoCodice}'"),
        };
    }

    /** Calcolo TCRI con i suoi metodi specifici (es. totaleSemaforo, usato dalle schede dei laboratori). */
    public static function tcri(): CalcoloTcri
    {
        return new CalcoloTcri(new CalcoloTestScritto());
    }
}
