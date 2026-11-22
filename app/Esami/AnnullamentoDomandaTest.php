<?php

namespace App\Esami;

use App\Models\Esame;
use App\Models\TestDomanda;
use App\Models\User;
use App\Services\SocioCorrente;
use Illuminate\Support\Facades\DB;

/**
 * Annullamento di una domanda del test scritto: la domanda resta, con le risposte salvate di tutti, ma esce dal calcolo
 * (punteggio, massimo, giuste: TestScritto::domandePerCalcolo). Solo il direttore, a esame in corso, e solo quando TUTTI
 * i discenti ammessi hanno inviato il test (online o cartaceo). Motivazione obbligatoria. Reversibile («Ripristina») alle
 * stesse condizioni; mai l'ultima domanda valida. A esame chiuso vale la fotografia degli esiti.
 */
class AnnullamentoDomandaTest
{
    public const MOTIVO_MAX = 1000;

    /** Perché annullare/ripristinare non è possibile adesso (tooltip), null se si può. */
    public static function motivoNonAnnullabile(Esame $esame, ?User $utente): ?string
    {
        if (! $esame->isDirettore($utente)) {
            return 'Solo il direttore del corso annulla le domande.';
        }
        if (! $esame->puoInserireFinali($utente)) {
            return $esame->isChiuso() ? "L'esame è chiuso: sola lettura." : "L'esame non è in corso.";
        }
        $mancanti = self::testMancanti($esame);
        if ($mancanti > 0) {
            return 'Si annulla solo quando tutti hanno inviato il test: ne '.($mancanti === 1 ? 'manca 1' : 'mancano '.$mancanti).'.';
        }

        return null;
    }

    /** Discenti ammessi che non hanno ancora un test inviato. */
    public static function testMancanti(Esame $esame): int
    {
        $ammessi = $esame->discentiAmmessi()->pluck('iscrizioni.partecipante_id');
        $inviati = $esame->testConsegne()->whereNotNull('consegnato_il')->whereIn('discente_socio_id', $ammessi)->count();

        return max(0, $ammessi->count() - $inviati);
    }

    /** @throws TestException */
    public static function annulla(Esame $esame, ?User $utente, int $domandaId, string $motivo): void
    {
        $motivo = Formato::testo($motivo);
        if ($motivo === null) {
            throw new TestException('Indica il motivo dell\'annullamento.', 422);
        }
        if (mb_strlen($motivo) > self::MOTIVO_MAX) {
            throw new TestException('Il motivo può avere al massimo '.self::MOTIVO_MAX.' caratteri.', 422);
        }

        self::modifica($esame, $utente, $domandaId, function (TestDomanda $d) use ($esame, $utente, $motivo) {
            if ($d->eAnnullata()) {
                throw new TestException('La domanda è già annullata.', 409);
            }
            if (TestScritto::numeroDomandeValide($esame) <= 1) {
                throw new TestException("Non si può annullare l'ultima domanda valida del test.", 409);
            }
            $d->update(['annullata_il' => now(), 'annullata_da' => SocioCorrente::perUtente($utente), 'motivo_annullamento' => $motivo]);
        });
    }

    /** @throws TestException */
    public static function ripristina(Esame $esame, ?User $utente, int $domandaId): void
    {
        self::modifica($esame, $utente, $domandaId, function (TestDomanda $d) {
            if (! $d->eAnnullata()) {
                throw new TestException('La domanda non è annullata.', 409);
            }
            $d->update(['annullata_il' => null, 'annullata_da' => null, 'motivo_annullamento' => null]);
        });
    }

    /** Controlli comuni, poi la modifica sotto blocco dell'esame (una chiusura in parallelo non la scavalca). */
    private static function modifica(Esame $esame, ?User $utente, int $domandaId, \Closure $operazione): void
    {
        $motivo = self::motivoNonAnnullabile($esame, $utente);
        if ($motivo !== null) {
            throw new TestException($motivo, 403);
        }

        DB::transaction(function () use ($esame, $domandaId, $operazione) {
            try {
                Esame::verificaInCorsoConBlocco($esame->id);
            } catch (\RuntimeException $e) {
                throw new TestException($e->getMessage(), 409);
            }
            $domanda = TestDomanda::query()->where('esame_id', $esame->id)->whereKey($domandaId)->lockForUpdate()->first()
                ?? throw new TestException('Domanda non trovata.', 404);
            $operazione($domanda);
        });
    }
}
