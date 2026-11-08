<?php

namespace App\Esami;

use App\Models\Esame;
use App\Models\TestConsegna;
use App\Models\TestDomanda;
use App\Models\TestRisposta;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Preparazione del test scritto: configurazione del punteggio e domande. Solo con il test in bozza
 * (Esame::puoPreparareTest); dal primo avvio restano ammesse solo le correzioni di refusi (Esame::puoCorreggereRefusiTest:
 * testo di domanda e opzioni, mai la corretta, il numero delle domande o la configurazione).
 * Ogni modifica in bozza scarta le compilazioni non inviate rimaste da finestre annullate: le risposte salvate
 * non avrebbero più senso con domande diverse.
 */
class PreparaTest
{
    /**
     * @param  array{penalita: bool, mescola?: bool, corretta?: string, errata?: string, vuota?: string}  $dati  valori con virgola o punto, al più 1 decimale
     *
     * @throws TestException
     */
    public static function salvaConfigurazione(Esame $esame, ?User $utente, array $dati): void
    {
        self::controllaPreparabile($esame, $utente);

        $penalita = (bool) ($dati['penalita'] ?? false);
        $valori = ['corretta' => '1.0', 'errata' => '0.0', 'vuota' => '0.0'];
        if ($penalita) {
            foreach (['corretta' => 'punti per risposta corretta', 'errata' => 'punti sottratti per risposta sbagliata', 'vuota' => 'punti sottratti per risposta non data'] as $k => $etichetta) {
                $n = TestScritto::normalizzaDecimale((string) ($dati[$k] ?? ''));
                if ($n === null) {
                    throw new TestException("Campo «{$etichetta}»: inserisci un numero con al massimo un decimale (es. 0,5).", 422);
                }
                $valori[$k] = $n;
            }
            if ((float) $valori['corretta'] <= 0) {
                throw new TestException('I punti per risposta corretta devono essere maggiori di zero.', 422);
            }
            if ((float) $valori['errata'] <= 0 && (float) $valori['vuota'] <= 0) {
                throw new TestException('Con le penalità attive serve almeno una penalità maggiore di zero (altrimenti lasciare vuota una risposta non costa nulla).', 422);
            }
        }

        $mescola = (bool) ($dati['mescola'] ?? false);

        DB::transaction(function () use ($esame, $penalita, $valori, $mescola) {
            $esame->update([
                'test_penalita_attive' => $penalita,
                'test_punti_corretta'  => $valori['corretta'],
                'test_penalita_errata' => $valori['errata'],
                'test_penalita_vuota'  => $valori['vuota'],
                'test_mescola'         => $mescola,
            ]);
            self::scartaCompilazioniNonInviate($esame);
        });
    }

    /**
     * @param  list<string>  $opzioni  esattamente 4 testi non vuoti
     *
     * @throws TestException
     */
    public static function creaDomanda(Esame $esame, ?User $utente, string $testo, array $opzioni, int $corretta): TestDomanda
    {
        self::controllaPreparabile($esame, $utente);
        [$testo, $opzioni] = self::validaTesti($testo, $opzioni);
        self::validaCorretta($corretta);

        return DB::transaction(function () use ($esame, $testo, $opzioni, $corretta) {
            self::scartaCompilazioniNonInviate($esame);

            return TestDomanda::create([
                'esame_id' => $esame->id,
                'ordine'   => ((int) $esame->testDomande()->max('ordine')) + 1,
                'testo'    => $testo,
                'opzioni'  => $opzioni,
                'corretta' => $corretta,
            ]);
        });
    }

    /**
     * Modifica completa in bozza; dal primo avvio solo testo e opzioni (la corretta deve restare quella che è).
     *
     * @param  list<string>  $opzioni
     *
     * @throws TestException
     */
    public static function aggiornaDomanda(Esame $esame, ?User $utente, TestDomanda $domanda, string $testo, array $opzioni, int $corretta): TestDomanda
    {
        self::controllaDellEsame($esame, $domanda);
        [$testo, $opzioni] = self::validaTesti($testo, $opzioni);
        self::validaCorretta($corretta);

        if ($esame->puoPreparareTest($utente)) {
            DB::transaction(function () use ($esame, $domanda, $testo, $opzioni, $corretta) {
                self::scartaCompilazioniNonInviate($esame);
                $domanda->update(['testo' => $testo, 'opzioni' => $opzioni, 'corretta' => $corretta]);
            });
        } elseif ($esame->puoCorreggereRefusiTest($utente)) {
            if ($corretta !== (int) $domanda->corretta) {
                throw new TestException('Dopo l\'avvio non si può cambiare la risposta corretta: solo correggere i refusi nei testi.', 422);
            }
            $domanda->update(['testo' => $testo, 'opzioni' => $opzioni]);
        } else {
            throw new TestException(self::motivoNonModificabile($esame, $utente), 403);
        }

        return $domanda->refresh();
    }

    /** @throws TestException */
    public static function eliminaDomanda(Esame $esame, ?User $utente, TestDomanda $domanda): void
    {
        self::controllaPreparabile($esame, $utente);
        self::controllaDellEsame($esame, $domanda);

        DB::transaction(function () use ($esame, $domanda) {
            self::scartaCompilazioniNonInviate($esame);
            $ordine = $domanda->ordine;
            $domanda->delete();
            // Niente buchi nella numerazione: scalo le successive in ordine crescente (il posto liberato è sempre vuoto).
            foreach ($esame->testDomande()->where('ordine', '>', $ordine)->orderBy('ordine')->get() as $successiva) {
                $successiva->update(['ordine' => $successiva->ordine - 1]);
            }
        });
    }

    /** Motivo per cui il test non si può modificare (tooltip), null se si può in qualche forma. */
    public static function motivoNonModificabile(Esame $esame, ?User $utente): ?string
    {
        if ($esame->puoPreparareTest($utente) || $esame->puoCorreggereRefusiTest($utente)) {
            return null;
        }
        if ($esame->isChiuso()) {
            return "L'esame è chiuso: sola lettura.";
        }

        return 'Solo chi gestisce l\'esame (direttore, admin dell\'ambito, superadmin) prepara il test.';
    }

    /** Compilazioni (e relative risposte) mai inviate: appartengono a una finestra di avvio annullata. */
    public static function scartaCompilazioniNonInviate(Esame $esame): void
    {
        $ids = TestConsegna::where('esame_id', $esame->id)->whereNull('consegnato_il')->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }
        TestRisposta::whereIn('consegna_id', $ids)->delete();
        TestConsegna::whereIn('id', $ids)->delete();
    }

    /** @throws TestException */
    public static function controllaPreparabile(Esame $esame, ?User $utente): void
    {
        if (! $esame->puoPreparareTest($utente)) {
            $motivo = $esame->puoCorreggereRefusiTest($utente)
                ? 'Il test è già stato avviato: si possono correggere solo i refusi nei testi. Per cambiare altro riportalo in bozza (se nessuno ha ancora inviato).'
                : self::motivoNonModificabile($esame, $utente);
            throw new TestException($motivo, 403);
        }
    }

    private static function controllaDellEsame(Esame $esame, TestDomanda $domanda): void
    {
        if ($domanda->esame_id !== $esame->id) {
            throw new TestException('Domanda non trovata.', 404);
        }
    }

    /** @return array{0: string, 1: list<string>} */
    public static function validaTesti(string $testo, array $opzioni): array
    {
        $testo = trim($testo);
        $opzioni = array_values(array_map(fn ($o) => trim((string) $o), $opzioni));
        if ($testo === '') {
            throw new TestException('Scrivi il testo della domanda.', 422);
        }
        if (count($opzioni) !== TestScritto::N_OPZIONI || in_array('', $opzioni, true)) {
            throw new TestException('Servono esattamente 4 opzioni, tutte compilate.', 422);
        }

        return [$testo, $opzioni];
    }

    public static function validaCorretta(int $corretta): void
    {
        if ($corretta < 0 || $corretta >= TestScritto::N_OPZIONI) {
            throw new TestException('Scegli la risposta corretta tra le 4 opzioni.', 422);
        }
    }
}
