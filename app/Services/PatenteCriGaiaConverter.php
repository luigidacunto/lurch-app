<?php

namespace App\Services;

/**
 * Conversione delle tipologie "Patente CRI" lette dal curriculum GAIA verso le tipologie del
 * modello ufficiale 2024, l'unico usato nel catalogo `patenti_cri` di LURCH.
 *
 * Su GAIA si trovano righe con numerazione mista: alcune ancora nei modelli 138/05 o 138/19
 * (identici tra loro), altre già nel modello 2024. La mappa copre entrambi i casi:
 *  - tipi comuni a tutti i modelli (1-8): identità
 *  - tipi solo-vecchi da convertire: 5b→2b, 4b→7b (Allegato C-1, righe "Be" e "D"),
 *    9→nessun corrispettivo 2024
 *  - tipi solo-2024 già nuovi (2b, 7b): identità, si importano così come sono
 * La colonna 138/93 (lettere A/B/Be/C/D/E) è ignorata: su GAIA non compare mai.
 *
 * ponytail: mappa statica, nessuna configurazione a DB. Se in futuro escono altri modelli si
 * aggiunge una riga qui.
 */
class PatenteCriGaiaConverter
{
    /**
     * tipologia GAIA (lowercase, senza prefisso "PATENTE CRI ") => tipologia 2024, oppure null
     * se non esiste un corrispettivo 2024 (riga da segnalare all'operatore, mai importata).
     */
    private const MAPPA = [
        '1'  => '1',
        '2'  => '2',
        '2b' => '2b', // già modello 2024
        '3'  => '3',
        '4'  => '4',
        '5'  => '5',
        '5b' => '2b', // riga "Be" C-1: {5, 5b} ↔ {5, 2b}
        '4b' => '7b', // riga "D"  C-1: {4b, 7} ↔ {7, 7b}
        '6'  => '6',
        '7'  => '7',
        '7b' => '7b', // già modello 2024
        '8'  => '8',
        // Tipo 9 (macchine operatrici, carrelli elevatori, motocarri): nel modello 2024 non è
        // più una patente CRI — è diventata un brevetto esterno, non gestito da LURCH. Nessuna
        // conversione possibile ed è corretto così: la riga viene solo segnalata, mai importata.
        '9'  => null,
    ];

    /**
     * @return array{tipologia_2024: ?string, convertibile: bool, nota: ?string}
     */
    public static function converti(string $tipologiaGaia): array
    {
        $key = self::normalizza($tipologiaGaia);

        if (! array_key_exists($key, self::MAPPA)) {
            return [
                'tipologia_2024' => null,
                'convertibile'   => false,
                'nota'           => "Tipologia \"$tipologiaGaia\" non riconosciuta in nessun modello patente CRI (138/05, 138/19 o 2024).",
            ];
        }

        $t2024 = self::MAPPA[$key];

        if ($t2024 === null) {
            return [
                'tipologia_2024' => null,
                'convertibile'   => false,
                'nota'           => "Tipo $key non è più una patente CRI nel modello 2024 (ora brevetto esterno): non viene importata. Nessuna azione richiesta.",
            ];
        }

        return [
            'tipologia_2024' => $t2024,
            'convertibile'   => true,
            'nota'           => $t2024 !== $key ? "Convertito da tipo $key (modello 138/05 o 138/19) a tipo $t2024 (2024)." : null,
        ];
    }

    /**
     * "PATENTE CRI 5B" / "Patente CRI 5b" / "5B" => "5b"
     */
    public static function normalizza(string $raw): string
    {
        $s = mb_strtolower(trim($raw));
        $s = preg_replace('/^patente\s+cri\s+/', '', $s);

        return trim($s);
    }
}
