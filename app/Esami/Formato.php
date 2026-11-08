<?php

namespace App\Esami;

use App\Esami\Calcolo\Frazione;

/** Presentazione dei numeri esatti degli esami (PWA e pannello): un solo punto, mai arrotondare. */
class Formato
{
    /** Troncata (mai arrotondata) a 2 decimali, almeno 1, virgola: 7/2 → "3,5", 10/3 → "3,33", 4 → "4,0". */
    public static function decimale(?Frazione $f): ?string
    {
        if ($f === null) {
            return null;
        }
        $t = $f->tronca(2);
        if (str_ends_with($t, '0')) {
            $t = substr($t, 0, -1);
        }

        return str_replace('.', ',', $t);
    }

    /** Come decimale() ma a 1 decimale (punteggio finale d'area, decimal(4,1)): "3,6". */
    public static function unDecimale(?Frazione $f): ?string
    {
        return $f === null ? null : str_replace('.', ',', $f->tronca(1));
    }

    /** Testo libero ripulito ai bordi; null se vuoto o fatto solo di spazi, tabulazioni, a capo (anche Unicode, es. spazio non separabile). */
    public static function testo(?string $t): ?string
    {
        $t = preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', (string) $t);

        return $t === null || $t === '' ? null : $t;
    }
}
