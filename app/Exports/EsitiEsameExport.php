<?php

namespace App\Exports;

use App\Esami\Calcolo\Frazione;
use App\Esami\RisultatiEsame;
use App\Models\Esame;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

/**
 * CSV degli esiti: `;` come separatore, BOM, decimali con la virgola. Colonne: anagrafica minima (nome, cognome,
 * codice fiscale), per ogni prova punti / massimo / contributo in centesimi, finale, esito, LAP, versione modello.
 * ⚠️ Le stringhe di origine utente che iniziano con = + - @ (o tab / a capo) sono prefissate con ' (formula injection):
 * gli altri export dell'app non lo fanno, qui sì perché il file contiene il codice fiscale e va aperto in Excel.
 */
class EsitiEsameExport implements FromArray, WithCustomCsvSettings
{
    public function __construct(private Esame $esame) {}

    public function getCsvSettings(): array
    {
        return ['delimiter' => ';', 'use_bom' => true, 'enclosure' => '"'];
    }

    public static function sicura(?string $valore): string
    {
        $v = (string) $valore;

        return $v !== '' && preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v;
    }

    private static function virgola(?string $v): string
    {
        return $v === null ? '' : str_replace('.', ',', $v);
    }

    private static function nd(?string $nd): string
    {
        return $nd === null ? '' : str_replace('.', ',', Frazione::daFrazione($nd)->tronca(2));
    }

    public function array(): array
    {
        $esame = $this->esame;
        $chiuso = $esame->isChiuso();
        $intestazione = ['Cognome', 'Nome', 'Codice fiscale', 'Stato'];
        foreach (RisultatiEsame::ETICHETTE_PROVE as $etichetta) {
            array_push($intestazione, "$etichetta - punti", "$etichetta - massimo", "$etichetta - contributo (centesimi)");
        }
        array_push($intestazione, 'Punteggio finale', 'Esito', 'Note', 'LAP', 'Versione modello', 'Stato esame', 'Esame', 'Chiuso il');

        $righe = [$intestazione];
        foreach (RisultatiEsame::per($esame) as $r) {
            $esito = match (true) {
                $r['stato'] === 'non_ammesso' => 'Non ammesso',
                $r['stato'] === 'incompleto' => 'Incompleto',
                $r['idoneo'] === true => 'Idoneo',
                default => 'Non idoneo',
            };
            $riga = [self::sicura($r['socio']->cognome), self::sicura($r['socio']->nome), self::sicura($r['socio']->codice_fiscale), $r['stato']];
            foreach (array_keys(RisultatiEsame::ETICHETTE_PROVE) as $p) {
                $pr = $r['prove'][$p] ?? null;
                array_push($riga, self::nd($pr['punti'] ?? null), self::nd($pr['massimo'] ?? null), self::virgola($pr['contributo'] ?? null));
            }
            // Nessun testo libero (motivo di non ammissione ecc.) negli export: solo punteggi e stato.
            $note = $r['stato'] === 'incompleto' ? 'Manca: '.implode(', ', $r['mancanti']) : '';
            array_push(
                $riga,
                self::virgola($r['finale']),
                $esito,
                self::sicura($note),
                self::virgola(rtrim(rtrim((string) $r['lap'], '0'), '.')),
                (string) $esame->modello_versione,
                $chiuso ? 'chiuso' : 'provvisorio',
                self::sicura($esame->titolo),
                $chiuso ? $esame->chiuso_il?->format('d/m/Y H:i') : '',
            );
            $righe[] = $riga;
        }

        return $righe;
    }
}
