<?php

namespace App\Esami;

use App\Models\Esame;
use App\Models\TestDomanda;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Import del test scritto da JSON (domande + 4 opzioni + lettera della corretta): SOSTITUISCE tutte le domande del test.
 * Solo con il test in bozza e per chi lo prepara (Esame::puoPreparareTest, stesso controllo di PreparaTest); il punteggio
 * (configurazione) non si tocca. Tutto o niente: se una sola domanda non è valida non cambia nulla e il messaggio le elenca
 * (con il numero di domanda) perché l'agente che ha prodotto il JSON possa correggerle. Formato e prompt: vedi PROMPT.
 * Riusa le validazioni di PreparaTest (4 opzioni non vuote, corretta tra le 4).
 */
class ImportaTest
{
    public const MAX_DOMANDE = 500;

    /** Esempio del formato; è anche quello che il prompt mostra all'agente (un test ne verifica la validità). */
    public const ESEMPIO = '{"domande":[{"testo":"Quanto fa 2+2?","opzioni":["3","4","5","6"],"corretta":"B"}]}';

    /** Prompt da dare a un assistente insieme al file Word del direttore. */
    public const PROMPT = <<<'TXT'
Ti allego un file (Word o PDF) con le domande di un test a risposta multipla e l'indicazione delle risposte corrette. Estrai TUTTE le domande, nell'ordine del file, e rispondi SOLO con un JSON valido (nessun testo prima o dopo) in questo formato: {"domande":[{"testo":"…","opzioni":["…","…","…","…"],"corretta":"B"}]}.
Regole: ogni domanda ha ESATTAMENTE 4 opzioni, scritte senza la lettera iniziale (niente «A)», «B.»); "corretta" è la lettera (A, B, C o D) della risposta giusta secondo il file; non riassumere né modificare i testi; se una domanda non ha 4 opzioni o la risposta corretta non è chiara, fermati e chiedimelo invece di inventare.
TXT;

    /**
     * Valida il JSON e restituisce le domande normalizzate.
     *
     * @return list<array{testo: string, opzioni: list<string>, corretta: int}>
     *
     * @throws TestException
     */
    public static function leggi(string $json): array
    {
        $json = trim(preg_replace('/^\xEF\xBB\xBF/', '', $json));
        // Gli assistenti spesso racchiudono il JSON in un blocco ```json … ```: lo togliamo.
        if (preg_match('/^```[a-zA-Z]*\s*(.*?)\s*```$/s', $json, $m)) {
            $json = trim($m[1]);
        }
        if ($json === '') {
            throw new TestException('Il JSON è vuoto.', 422);
        }
        $dati = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($dati)) {
            throw new TestException('Il testo non è un JSON valido (' . json_last_error_msg() . '): incolla solo il JSON, senza altro testo.', 422);
        }
        $domande = array_is_list($dati) ? $dati : ($dati['domande'] ?? null);
        if (! is_array($domande) || ! array_is_list($domande)) {
            throw new TestException('Manca l\'elenco "domande": il JSON deve essere {"domande":[ … ]}.', 422);
        }
        if ($domande === []) {
            throw new TestException('L\'elenco delle domande è vuoto.', 422);
        }
        if (count($domande) > self::MAX_DOMANDE) {
            throw new TestException('Troppe domande (massimo ' . self::MAX_DOMANDE . ').', 422);
        }

        $risultato = [];
        $errori = [];
        foreach ($domande as $i => $d) {
            $n = $i + 1;
            try {
                $risultato[] = self::domanda($d);
            } catch (TestException $e) {
                $errori[] = "Domanda {$n}: " . $e->getMessage();
            }
        }
        if ($errori) {
            $altre = count($errori) - 8;
            throw new TestException(
                'Nessuna domanda importata, correggi il JSON. ' . implode(' ', array_slice($errori, 0, 8)) . ($altre > 0 ? " (e altri {$altre} errori)" : ''),
                422,
            );
        }

        return $risultato;
    }

    /** @throws TestException */
    private static function domanda(mixed $d): array
    {
        if (! is_array($d) || ! is_string($d['testo'] ?? null) || ! is_array($d['opzioni'] ?? null)) {
            throw new TestException('servono "testo" (testo), "opzioni" (elenco di 4 testi) e "corretta" (lettera).', 422);
        }
        if (! array_is_list($d['opzioni']) || array_filter($d['opzioni'], fn ($o) => ! is_string($o) && ! is_numeric($o))) {
            throw new TestException('"opzioni" deve essere un elenco di 4 testi.', 422);
        }
        [$testo, $opzioni] = PreparaTest::validaTesti($d['testo'], $d['opzioni']);
        if (count(array_unique(array_map(fn ($o) => mb_strtolower($o), $opzioni))) !== count($opzioni)) {
            throw new TestException('due opzioni sono uguali.', 422);
        }
        $lettera = strtoupper(trim((string) ($d['corretta'] ?? '')));
        $corretta = strpos('ABCD', $lettera);
        if (strlen($lettera) !== 1 || $corretta === false) {
            throw new TestException('"corretta" deve essere una lettera tra A, B, C, D.', 422);
        }
        PreparaTest::validaCorretta($corretta);

        return ['testo' => $testo, 'opzioni' => $opzioni, 'corretta' => $corretta];
    }

    /**
     * Sostituisce TUTTE le domande del test con quelle date (già validate da leggi()). Restituisce quante ne ha create.
     *
     * @param  list<array{testo: string, opzioni: list<string>, corretta: int}>  $domande
     *
     * @throws TestException
     */
    public static function sostituisci(Esame $esame, ?User $utente, array $domande): int
    {
        PreparaTest::controllaPreparabile($esame, $utente);

        DB::transaction(function () use ($esame, $domande) {
            PreparaTest::scartaCompilazioniNonInviate($esame);
            $esame->testDomande()->delete();
            foreach ($domande as $i => $d) {
                TestDomanda::create([
                    'esame_id' => $esame->id,
                    'ordine'   => $i + 1,
                    'testo'    => $d['testo'],
                    'opzioni'  => $d['opzioni'],
                    'corretta' => $d['corretta'],
                ]);
            }
        });

        return count($domande);
    }

    /** Validazione + sostituzione in un passo: l'unico punto d'ingresso del pannello. */
    public static function importa(Esame $esame, ?User $utente, string $json): int
    {
        PreparaTest::controllaPreparabile($esame, $utente);

        return self::sostituisci($esame, $utente, self::leggi($json));
    }
}
