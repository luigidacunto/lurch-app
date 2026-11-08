<?php

namespace App\Esami;

use App\Esami\Calcolo\Frazione;

/**
 * Sfondi tenui dei descrittori di una scala (colloquio, itinere) in base al LAP dell'esame: solo ausilio visivo,
 * non entra in nessun calcolo. La percentuale del livello è valore/max (come conta nel motore).
 *
 * Fasce nette: sotto il LAP rosso → arancione → giallo (terzi di 0–LAP); il primo livello che raggiunge il LAP è
 * verde (primo sufficiente); i livelli oltre il sufficiente vanno dall'azzurro al blu (eccellenza).
 */
class ColoreScala
{
    private const ROSSO = 'hsl(0, 75%, 86%)';
    private const ARANCIONE = 'hsl(28, 90%, 82%)';
    private const GIALLO = 'hsl(50, 90%, 80%)';
    private const VERDE = 'hsl(130, 55%, 80%)';

    /**
     * @param  array<int, int|float|string>  $valori  tutti i livelli della scala
     * @return array<string, string> valore (come stringa) => colore CSS
     */
    public static function per(array $valori, int|float|string $max, int|float|string $lap): array
    {
        $max = (float) $max;
        $lap = (float) $lap;
        $perc = fn ($v) => $max > 0 ? (float) $v / $max * 100 : 0.0;

        $positivi = collect($valori)->filter(fn ($v) => $perc($v) >= $lap)->sortBy(fn ($v) => (float) $v)->values();
        $resto = max($positivi->count() - 1, 0);

        $colori = [];
        foreach ($valori as $v) {
            $p = $perc($v);
            if ($p < $lap) {
                $colori[(string) $v] = $p < $lap / 3 ? self::ROSSO : ($p < 2 * $lap / 3 ? self::ARANCIONE : self::GIALLO);
                continue;
            }
            $posizione = $positivi->search(fn ($x) => (float) $x === (float) $v);
            // Oltre il primo sufficiente: tonalità da azzurro (195°) a blu (220°) in base alla posizione.
            $colori[(string) $v] = $posizione === 0
                ? self::VERDE
                : sprintf('hsl(%d, 80%%, 84%%)', round(195 + 25 * $posizione / $resto));
        }

        return $colori;
    }

    /**
     * Stesse 5 fasce per un valore CONTINUO (medie e totali dei laboratori), in percentuale del massimo: sotto il LAP
     * rosso/arancione/giallo per terzi; da LAP alla metà di quel che resta fino a 100 verde; sopra, azzurro.
     */
    public static function perValore(Frazione $valore, Frazione $max, int|float|string $lap): string
    {
        $p = $max->eZero() ? 0.0 : (float) $valore->diviso($max)->tronca(4) * 100;
        $lap = (float) $lap;

        return match (true) {
            $p < $lap / 3      => self::ROSSO,
            $p < 2 * $lap / 3  => self::ARANCIONE,
            $p < $lap          => self::GIALLO,
            $p < ($lap + 100) / 2 => self::VERDE,
            default            => 'hsl(207, 80%, 84%)',
        };
    }
}
