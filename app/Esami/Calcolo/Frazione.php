<?php

declare(strict_types=1);

namespace App\Esami\Calcolo;

use InvalidArgumentException;
use OverflowException;

/**
 * Frazione esatta num/den su interi PHP, immutabile e sempre ridotta ai minimi termini.
 *
 * ⚠️ PHP converte in silenzio in float l'overflow di PHP_INT_MAX: ogni risultato aritmetico
 * passa da esatto(), che lancia OverflowException invece di lasciar entrare un float.
 * Nessun float entra mai nella classe (da() li rifiuta).
 */
final class Frazione
{
    public readonly int $num;

    public readonly int $den;

    private function __construct(int $num, int $den)
    {
        if ($den === 0) {
            throw new InvalidArgumentException('Denominatore zero.');
        }
        if ($num === PHP_INT_MIN || $den === PHP_INT_MIN) {
            throw new OverflowException('Valore fuori dal campo degli interi esatti.');
        }
        if ($den < 0) {
            $num = -$num;
            $den = -$den;
        }
        $mcd = self::mcd(abs($num), $den);
        $this->num = intdiv($num, $mcd);
        $this->den = intdiv($den, $mcd);
    }

    public static function di(int $num, int $den = 1): self
    {
        return new self($num, $den);
    }

    /**
     * Da intero o stringa decimale con il punto ("0.25", "1.00", "57.5", "-3"). Mai float.
     */
    public static function da(mixed $valore): self
    {
        if (is_int($valore)) {
            return new self($valore, 1);
        }
        if (! is_string($valore)) {
            throw new InvalidArgumentException('Valore non ammesso (serve int o stringa decimale, mai float): '.get_debug_type($valore));
        }
        if (! preg_match('/^([+-]?)(\d+)(?:\.(\d+))?$/', trim($valore), $m)) {
            throw new InvalidArgumentException("Numero decimale non valido: '{$valore}'");
        }
        $decimali = $m[3] ?? '';
        $cifre = ltrim($m[2].$decimali, '0');
        if (strlen($cifre) > 18 || strlen($decimali) > 18) {
            throw new OverflowException("Numero troppo grande per l'aritmetica esatta: '{$valore}'");
        }
        $num = (int) ($cifre === '' ? '0' : $cifre);
        if ($m[1] === '-') {
            $num = -$num;
        }

        return new self($num, self::potenza10(strlen($decimali)));
    }

    /** Dalla forma "num/den" prodotta da __toString() (snapshot degli esiti, uscita del motore). Mai float. */
    public static function daFrazione(string $testo): self
    {
        if (! preg_match('/^(-?\d+)\/(\d+)$/', trim($testo), $m) || (int) $m[2] === 0) {
            throw new InvalidArgumentException("Frazione non valida: '{$testo}'");
        }

        return self::di((int) $m[1], (int) $m[2]);
    }

    public function somma(self $altra): self
    {
        $mcd = self::mcd($this->den, $altra->den);
        $den = self::esatto(intdiv($this->den, $mcd) * $altra->den);
        $num = self::esatto(
            self::esatto($this->num * intdiv($altra->den, $mcd))
            + self::esatto($altra->num * intdiv($this->den, $mcd))
        );

        return new self($num, $den);
    }

    public function sottrai(self $altra): self
    {
        return $this->somma(new self(-$altra->num, $altra->den));
    }

    public function per(self $altra): self
    {
        return new self(
            self::esatto($this->num * $altra->num),
            self::esatto($this->den * $altra->den),
        );
    }

    public function diviso(self $altra): self
    {
        if ($altra->num === 0) {
            throw new InvalidArgumentException('Divisione per zero.');
        }

        return new self(
            self::esatto($this->num * $altra->den),
            self::esatto($this->den * $altra->num),
        );
    }

    /** -1, 0, 1 come <=>. I denominatori sono sempre positivi. */
    public function confronta(self $altra): int
    {
        return self::esatto($this->num * $altra->den) <=> self::esatto($altra->num * $this->den);
    }

    public function eZero(): bool
    {
        return $this->num === 0;
    }

    /**
     * Tronca (mai arrotonda; verso zero) a $decimali cifre. Restituisce sempre esattamente
     * $decimali cifre decimali con il punto ("60.0", "59.90"); la virgola è solo presentazione.
     */
    public function tronca(int $decimali): string
    {
        if ($decimali < 0 || $decimali > 15) {
            throw new InvalidArgumentException('Decimali ammessi: da 0 a 15.');
        }
        $scala = self::potenza10($decimali);
        $scalato = self::esatto(intdiv(self::esatto($this->num * $scala), $this->den));
        $assoluto = self::esatto(abs($scalato));
        $segno = $scalato < 0 ? '-' : '';
        if ($decimali === 0) {
            return $segno.$assoluto;
        }

        return $segno.intdiv($assoluto, $scala).'.'.str_pad((string) ($assoluto % $scala), $decimali, '0', STR_PAD_LEFT);
    }

    public function __toString(): string
    {
        return $this->num.'/'.$this->den;
    }

    private static function mcd(int $a, int $b): int
    {
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }

        return $a === 0 ? 1 : $a;
    }

    private static function potenza10(int $esponente): int
    {
        $r = 1;
        for ($i = 0; $i < $esponente; $i++) {
            $r = self::esatto($r * 10);
        }

        return $r;
    }

    /** Intercetta il float prodotto in silenzio da un overflow intero. */
    private static function esatto(int|float $valore): int
    {
        if (! is_int($valore)) {
            throw new OverflowException('Overflow nel calcolo esatto.');
        }

        return $valore;
    }
}
