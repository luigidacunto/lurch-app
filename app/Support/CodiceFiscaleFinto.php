<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Generatore di codici fiscali FINTI per l'anonimizzazione del DB di sviluppo
 * (comando dev:anonimizza-dati).
 *
 * Il CF ha struttura e carattere di controllo corretti (così qualunque validatore
 * di formato lo accetta), ma il codice catastale (Belfiore) è sempre `Z000`, che
 * è NON CONFORME per definizione: la parte numerica di un codice Belfiore reale
 * non è mai `000` (i comuni partono da 001+, gli stati esteri da Z100+). Garantisce
 * che un CF generato non possa mai coincidere con quello di una persona reale,
 * senza dover confrontare con la lista dei codici effettivamente assegnati.
 */
class CodiceFiscaleFinto
{
    /** Codice Belfiore non conforme per definizione (numerico `000` non esiste) — vedi docblock di classe. */
    public const BELFIORE = 'Z000';

    private const MESI = 'ABCDEHLMPRST';

    public static function genera(string $cognome, string $nome, \DateTimeInterface $nascita, bool $femmina = false): string
    {
        $parziale = self::codiceCognome($cognome)
            . self::codiceNome($nome)
            . $nascita->format('y')
            . self::MESI[((int) $nascita->format('n')) - 1]
            . str_pad((string) ((int) $nascita->format('j') + ($femmina ? 40 : 0)), 2, '0', STR_PAD_LEFT)
            . self::BELFIORE;

        return $parziale . self::carattereControllo($parziale);
    }

    /** True se il carattere di controllo del CF è coerente con i primi 15 caratteri. */
    public static function checksumValido(string $cf): bool
    {
        $cf = strtoupper(trim($cf));

        return (bool) preg_match('/^[A-Z0-9]{15}[A-Z]$/', $cf)
            && self::carattereControllo(substr($cf, 0, 15)) === $cf[15];
    }

    private static function pulisci(string $s): string
    {
        return preg_replace('/[^A-Z]/', '', strtoupper(Str::ascii($s)));
    }

    private static function consonanti(string $s): string
    {
        return preg_replace('/[^BCDFGHJKLMNPQRSTVWXYZ]/', '', self::pulisci($s));
    }

    private static function vocali(string $s): string
    {
        return preg_replace('/[^AEIOU]/', '', self::pulisci($s));
    }

    private static function codiceCognome(string $cognome): string
    {
        return substr(self::consonanti($cognome) . self::vocali($cognome) . 'XXX', 0, 3);
    }

    private static function codiceNome(string $nome): string
    {
        $cons = self::consonanti($nome);

        if (strlen($cons) >= 4) {
            return $cons[0] . $cons[2] . $cons[3];
        }

        return substr($cons . self::vocali($nome) . 'XXX', 0, 3);
    }

    private static function carattereControllo(string $primi15): string
    {
        // Tabelle standard: per i primi 10 valori la cifra e la lettera corrispondente
        // (0/A, 1/B, ...) condividono lo stesso peso, quindi si costruiscono in un colpo.
        static $dispari = null, $pari = null;

        if ($dispari === null) {
            $baseDispari = [1, 0, 5, 7, 9, 13, 15, 17, 19, 21];
            $codaDispari = [2, 4, 18, 20, 11, 3, 6, 8, 12, 14, 16, 10, 22, 25, 24, 23]; // K..Z

            $dispari = $pari = [];
            for ($i = 0; $i <= 9; $i++) {
                $dispari[(string) $i] = $baseDispari[$i];
                $dispari[chr(65 + $i)] = $baseDispari[$i];
                $pari[(string) $i] = $i;
                $pari[chr(65 + $i)] = $i;
            }
            for ($i = 10; $i < 26; $i++) {
                $dispari[chr(65 + $i)] = $codaDispari[$i - 10];
                $pari[chr(65 + $i)] = $i;
            }
        }

        $somma = 0;
        foreach (str_split($primi15) as $pos => $ch) {
            $somma += ($pos % 2 === 0) ? $dispari[$ch] : $pari[$ch]; // pos 0-based: 0 = posizione dispari
        }

        return chr(($somma % 26) + 65);
    }
}
