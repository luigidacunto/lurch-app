<?php

namespace App\Support;

/** Anni del copyright nei footer: «2025–2026», oppure il solo anno di inizio se coincide con l'anno corrente. */
class Copyright
{
    public static function anni(): string
    {
        $inizio = (int) config('app.anno_inizio');
        $oggi   = (int) date('Y');

        return $oggi > $inizio ? "{$inizio}–{$oggi}" : (string) $inizio;
    }
}
