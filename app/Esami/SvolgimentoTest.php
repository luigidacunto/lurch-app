<?php

namespace App\Esami;

use App\Models\Esame;
use App\Models\TestAvvio;
use App\Models\TestConsegna;
use App\Models\TestDomanda;
use App\Models\TestRisposta;
use Illuminate\Support\Facades\DB;

/**
 * Svolgimento del test scritto: salvataggio a ogni tocco e invio. Lo stesso codice per l'online (il discente,
 * dalla PWA) e per il cartaceo (il direttore, dal pannello): cambia solo `$modalita` / `$inseritoDa`. Le autorizzazioni di
 * ruolo le decide il chiamante (PermessiEsame / Esame::puoAvviareTest); qui si ricontrolla tutto ciò che può cambiare tra
 * il caricamento del modulo e la richiesta: esame in corso, avvio attivo, token dell'attivazione, modalità, discente ammesso.
 *
 * - Una sola consegna per (esame, discente): UNIQUE nel database; la riga nasce al primo tocco con consegnato_il NULL.
 * - Un solo invio: compare-and-set su consegnato_il IS NULL, con la riga bloccata (due tab / doppio tocco → 409).
 * - Dopo l'invio nessuna scrittura (409). Senza penalità tutte le risposte sono obbligatorie; con penalità le mancanti
 *   vengono scritte come NULL (= non risposta).
 */
class SvolgimentoTest
{
    private const MSG_AGGIORNATO = 'Il test è stato aggiornato: ricarica la pagina.';

    /**
     * Salva UNA risposta ($opzione null = togliere la scelta, ammesso solo con le penalità attive).
     *
     * @return array{date: int, totale: int}
     *
     * @throws TestException
     */
    public static function salvaRisposta(Esame $esame, int $discenteId, string $token, string $modalita, ?int $inseritoDa, int $domandaId, ?int $opzione): array
    {
        if ($opzione !== null && ($opzione < 0 || $opzione >= TestScritto::N_OPZIONI)) {
            throw new TestException('Opzione non valida.', 422);
        }
        if ($opzione === null && TestScritto::configurazione($esame)['obbligatorie']) {
            throw new TestException('In questo test tutte le risposte sono obbligatorie: non si può togliere la scelta.', 422);
        }
        if (! TestDomanda::where('esame_id', $esame->id)->whereKey($domandaId)->exists()) {
            throw new TestException('Domanda non trovata.', 422);
        }

        return DB::transaction(function () use ($esame, $discenteId, $token, $modalita, $inseritoDa, $domandaId, $opzione) {
            self::controlla($esame, $discenteId, $token, $modalita);
            $consegna = self::consegnaBloccata($esame, $discenteId, $modalita, $inseritoDa);

            TestRisposta::upsert(
                [['consegna_id' => $consegna->id, 'domanda_id' => $domandaId, 'opzione' => $opzione, 'created_at' => now(), 'updated_at' => now()]],
                ['consegna_id', 'domanda_id'],
                ['opzione', 'updated_at'],
            );

            return ['date' => TestScritto::risposteDate($consegna), 'totale' => TestScritto::numeroDomande($esame)];
        });
    }

    /**
     * Invia il test: stato Inviato, da qui in poi non si tocca più.
     *
     * @throws TestException
     */
    public static function invia(Esame $esame, int $discenteId, string $token, string $modalita, ?int $inseritoDa): TestConsegna
    {
        return DB::transaction(function () use ($esame, $discenteId, $token, $modalita, $inseritoDa) {
            self::controlla($esame, $discenteId, $token, $modalita);
            $consegna = self::consegnaBloccata($esame, $discenteId, $modalita, $inseritoDa);

            $domandeIds = TestDomanda::where('esame_id', $esame->id)->pluck('id');
            $conRisposta = $consegna->risposte()->whereNotNull('opzione')->pluck('domanda_id');
            $mancanti = $domandeIds->diff($conRisposta);

            if ($mancanti->isNotEmpty() && TestScritto::configurazione($esame)['obbligatorie']) {
                throw new TestException('Mancano '.$mancanti->count().' risposte: in questo test sono tutte obbligatorie.', 422);
            }
            // Le non date diventano righe vuote esplicite: «risposta lasciata vuota» ≠ «test non inviato».
            $nulle = $domandeIds->map(fn ($id) => ['consegna_id' => $consegna->id, 'domanda_id' => $id, 'opzione' => null, 'created_at' => now(), 'updated_at' => now()])->all();
            if ($nulle !== []) {
                TestRisposta::insertOrIgnore($nulle);
            }

            $aggiornate = TestConsegna::where('id', $consegna->id)->whereNull('consegnato_il')->update(['consegnato_il' => now()]);
            if ($aggiornate !== 1) {
                throw new TestException('Il test risulta già inviato.', 409);
            }

            return $consegna->refresh();
        });
    }

    /** Esame in corso, avvio attivo con lo stesso token e modalità, discente ammesso. Riga dell'avvio bloccata per la durata della transazione. */
    private static function controlla(Esame $esame, int $discenteId, string $token, string $modalita): TestAvvio
    {
        // FOR SHARE: non scrive a cavallo della chiusura (ChiusuraEsame::chiudi prende FOR UPDATE).
        if (Esame::query()->sharedLock()->whereKey($esame->id)->value('stato') !== Esame::STATO_IN_CORSO) {
            throw new TestException('Il test non è più disponibile.', 409);
        }
        $avvio = TestAvvio::where('esame_id', $esame->id)->where('discente_socio_id', $discenteId)->lockForUpdate()->first();
        if (! $avvio || ! $avvio->eAttivo() || $avvio->modalita !== $modalita || ! hash_equals((string) $avvio->token, $token)) {
            throw new TestException(self::MSG_AGGIORNATO, 409);
        }
        if (! $esame->discentiAmmessi()->where('iscrizioni.partecipante_id', $discenteId)->exists()) {
            throw new TestException('Il test non è disponibile per questo discente.', 403);
        }

        return $avvio;
    }

    /** La compilazione del discente (creata al primo tocco, ON CONFLICT DO NOTHING) bloccata; già inviata → 409. */
    private static function consegnaBloccata(Esame $esame, int $discenteId, string $modalita, ?int $inseritoDa): TestConsegna
    {
        TestConsegna::insertOrIgnore([[
            'esame_id' => $esame->id, 'discente_socio_id' => $discenteId, 'origine' => $modalita,
            'inserito_da' => $modalita === TestConsegna::ORIGINE_CARTACEO ? $inseritoDa : null,
            'created_at' => now(), 'updated_at' => now(),
        ]]);
        $consegna = TestConsegna::where('esame_id', $esame->id)->where('discente_socio_id', $discenteId)->lockForUpdate()->firstOrFail();
        if ($consegna->eInviata()) {
            throw new TestException('Il test è già stato inviato: non si può più modificare.', 409);
        }
        if ($consegna->origine !== $modalita) {
            throw new TestException(self::MSG_AGGIORNATO, 409);
        }

        return $consegna;
    }
}
