<?php

namespace App\Esami;

use App\Models\Esame;
use App\Models\TestAvvio;
use App\Models\TestConsegna;
use App\Models\TestRisposta;
use App\Models\User;
use App\Services\SocioCorrente;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Avvio del test scritto per discente: solo il direttore, a esame in corso, per i discenti AMMESSI
 * (Esame::discentiAmmessi, mai un id arrivato dal form senza questa intersezione). Indipendente da «Apri la prova finale»
 * e da qualsiasi data. Reversibile finché il discente non ha inviato; dopo l'invio non si tocca più.
 * Ogni attivazione genera un nuovo token: i moduli già aperti in una finestra annullata non scrivono più.
 * La disattivazione conserva le risposte salvate (la compilazione resta in attesa); si scartano solo cambiando domande
 * (PreparaTest) o modalità (online ↔ cartaceo).
 */
class AvvioTest
{
    /**
     * «Avvia per tutti» ($discentiIds null) o «Avvia per i selezionati». Attiva solo gli ammessi non ancora attivi e
     * che non hanno inviato; idempotente.
     *
     * @param  list<int>|null  $discentiIds
     * @return int quanti discenti sono stati attivati adesso
     *
     * @throws TestException
     */
    public static function avvia(Esame $esame, ?User $utente, ?array $discentiIds = null): int
    {
        self::controllaAvviabile($esame, $utente);

        $ammessi = $esame->discentiAmmessi()->pluck('iscrizioni.partecipante_id')->map(fn ($id) => (int) $id);
        $ids = $discentiIds === null ? $ammessi : $ammessi->intersect(array_map('intval', $discentiIds));
        $da = SocioCorrente::perUtente($utente);

        return DB::transaction(function () use ($esame, $ids, $da) {
            $n = 0;
            foreach ($ids->unique() as $discenteId) {
                $avvio = TestScritto::avvio($esame, $discenteId);
                if ($avvio?->eAttivo() || TestScritto::consegna($esame, $discenteId)?->eInviata()) {
                    continue;
                }
                self::scartaBozzaSeCambiaModalita($esame, $discenteId, TestAvvio::ONLINE);
                self::attiva($esame, $discenteId, TestAvvio::ONLINE, null, $da);
                $n++;
            }

            return $n;
        });
    }

    /**
     * Interruttore «lo farà su carta» (BES/DSA): motivo obbligatorio; abilita l'inserimento a mano da parte del direttore.
     *
     * @throws TestException
     */
    public static function impostaCartaceo(Esame $esame, ?User $utente, int $discenteId, string $motivo): void
    {
        self::controllaAvviabile($esame, $utente);
        self::controllaAmmesso($esame, $discenteId);
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new TestException('Indica il motivo (es. BES/DSA).', 422);
        }
        if (TestScritto::consegna($esame, $discenteId)?->eInviata()) {
            throw new TestException('Il discente ha già inviato il test: non si può più cambiare.', 409);
        }

        DB::transaction(function () use ($esame, $utente, $discenteId, $motivo) {
            self::scartaBozzaSeCambiaModalita($esame, $discenteId, TestAvvio::CARTACEO);
            self::attiva($esame, $discenteId, TestAvvio::CARTACEO, $motivo, SocioCorrente::perUtente($utente));
        });
    }

    /** @throws TestException */
    public static function disattiva(Esame $esame, ?User $utente, int $discenteId): void
    {
        self::controllaDirettore($esame, $utente);
        if (TestScritto::consegna($esame, $discenteId)?->eInviata()) {
            throw new TestException('Il discente ha già inviato il test: non si può più disattivare.', 409);
        }
        $avvio = TestScritto::avvio($esame, $discenteId);
        if (! $avvio?->eAttivo()) {
            return;
        }
        $avvio->update(['avviato_il' => null, 'token' => (string) Str::uuid()]);
    }

    /**
     * «Riporta il test in bozza»: disattiva tutti gli avvii (le risposte salvate restano in attesa) e riapre la
     * modifica di domande e configurazione. Solo se nessun discente ha inviato.
     *
     * @throws TestException
     */
    public static function riportaInBozza(Esame $esame, ?User $utente): void
    {
        self::controllaDirettore($esame, $utente);
        if ($esame->statoTest() === Esame::TEST_CON_CONSEGNE) {
            throw new TestException('Almeno un discente ha già inviato il test: non si può riportare in bozza.', 409);
        }
        foreach ($esame->testAvvii()->whereNotNull('avviato_il')->get() as $avvio) {
            $avvio->update(['avviato_il' => null, 'token' => (string) Str::uuid()]);
        }
    }

    /** Motivo per cui «Riporta in bozza» è spento (tooltip), null se si può. */
    public static function motivoNonRiportabileInBozza(Esame $esame, ?User $utente): ?string
    {
        if (! $esame->isDirettore($utente)) {
            return 'Solo il direttore del corso avvia e gestisce il test.';
        }
        if (! $esame->puoInserireFinali($utente)) {
            return $esame->isChiuso() ? "L'esame è chiuso: sola lettura." : "L'esame non è in corso.";
        }
        if ($esame->statoTest() === Esame::TEST_CON_CONSEGNE) {
            return 'Almeno un discente ha già inviato il test.';
        }
        if ($esame->statoTest() === Esame::TEST_BOZZA) {
            return 'Il test è già in bozza.';
        }

        return null;
    }

    private static function attiva(Esame $esame, int $discenteId, string $modalita, ?string $motivo, ?int $da): TestAvvio
    {
        return TestAvvio::updateOrCreate(
            ['esame_id' => $esame->id, 'discente_socio_id' => $discenteId],
            [
                'modalita'        => $modalita,
                'avviato_il'      => now(),
                'token'           => (string) Str::uuid(),
                'motivo_cartaceo' => $modalita === TestAvvio::CARTACEO ? $motivo : null,
                'avviato_da'      => $da,
            ],
        );
    }

    /** La compilazione iniziata in una modalità non vale per l'altra (le risposte online non diventano un cartaceo). */
    private static function scartaBozzaSeCambiaModalita(Esame $esame, int $discenteId, string $nuova): void
    {
        $bozza = TestConsegna::where('esame_id', $esame->id)->where('discente_socio_id', $discenteId)
            ->whereNull('consegnato_il')->where('origine', '!=', $nuova)->first();
        if ($bozza) {
            TestRisposta::where('consegna_id', $bozza->id)->delete();
            $bozza->delete();
        }
    }

    private static function controllaAvviabile(Esame $esame, ?User $utente): void
    {
        self::controllaDirettore($esame, $utente);
        if ($motivo = $esame->motivoTestNonAvviabile()) {
            throw new TestException($motivo, 409);
        }
    }

    private static function controllaDirettore(Esame $esame, ?User $utente): void
    {
        if (! $esame->puoInserireFinali($utente)) {
            throw new TestException('Solo il direttore del corso, a esame in corso, avvia e gestisce il test.', 403);
        }
    }

    private static function controllaAmmesso(Esame $esame, int $discenteId): void
    {
        if (! $esame->discentiAmmessi()->where('iscrizioni.partecipante_id', $discenteId)->exists()) {
            throw new TestException('Il discente non è tra gli ammessi.', 404);
        }
    }
}
