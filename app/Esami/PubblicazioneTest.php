<?php

namespace App\Esami;

use App\Models\Esame;
use App\Models\TestPubblicazione;
use App\Models\User;
use App\Services\SocioCorrente;
use Illuminate\Support\Facades\DB;

/**
 * Pubblicazione dei risultati del test scritto ai discenti: da quel momento il discente vede nella app punteggio,
 * domande sbagliate e risposte corrette (PermessiEsame::vedeRisultatiTest). Chi gestisce l'esame (direttore, admin
 * dell'ambito, superadmin: Esame::puoPubblicareRisultati); solo per chi ha INVIATO
 * (online o cartaceo inserito); a esame in corso e anche a esame chiuso: cambia cosa vede il discente, non le valutazioni
 * (tabella a parte, le tabelle delle valutazioni restano bloccate). Nessun blocco rispetto agli avvii: chi ha già i
 * risultati può passarli a chi deve ancora svolgere il test, il pannello lo ricorda (scelta del proprietario).
 */
class PubblicazioneTest
{
    /**
     * «Pubblica per tutti» ($discentiIds null), per i selezionati o per uno. Idempotente.
     *
     * @param  list<int>|null  $discentiIds
     * @return int quanti discenti hanno ricevuto adesso la pubblicazione
     *
     * @throws TestException
     */
    public static function pubblica(Esame $esame, ?User $utente, ?array $discentiIds = null): int
    {
        self::controlla($esame, $utente);
        $da = SocioCorrente::perUtente($utente);

        return DB::transaction(function () use ($esame, $discentiIds, $da) {
            $n = 0;
            foreach (self::conTestInviato($esame, $discentiIds) as $discenteId) {
                $p = TestPubblicazione::firstOrNew(['esame_id' => $esame->id, 'discente_socio_id' => $discenteId]);
                if ($p->ePubblicata()) {
                    continue;
                }
                $p->fill(['pubblicato_il' => now(), 'pubblicato_da' => $da])->save();
                $n++;
            }

            return $n;
        });
    }

    /**
     * «Ritira» ($discentiIds con un id) o «Ritira per tutti» (null): il discente torna alla sola lettura delle sue risposte.
     *
     * @param  list<int>|null  $discentiIds
     *
     * @throws TestException
     */
    public static function ritira(Esame $esame, ?User $utente, ?array $discentiIds = null): int
    {
        self::controlla($esame, $utente);

        $q = TestPubblicazione::where('esame_id', $esame->id)->whereNotNull('pubblicato_il');
        if ($discentiIds !== null) {
            $q->whereIn('discente_socio_id', array_map('intval', $discentiIds));
        }

        return $q->update(['pubblicato_il' => null, 'updated_at' => now()]);
    }

    /** Motivo per cui i pulsanti di pubblicazione sono spenti (tooltip), null se accesi. Stessa regola per test ed esito. */
    public static function motivoNonPubblicabile(Esame $esame, ?User $utente): ?string
    {
        if ($esame->isBozza()) {
            return "L'esame non è ancora attivo.";
        }
        if (! $esame->puoPubblicareRisultati($utente)) {
            return "Solo chi gestisce l'esame (direttore, amministratore, superadmin) pubblica i risultati.";
        }

        return null;
    }

    /** @throws TestException */
    private static function controlla(Esame $esame, ?User $utente): void
    {
        if ($motivo = self::motivoNonPubblicabile($esame, $utente)) {
            throw new TestException($motivo, 403);
        }
    }

    /**
     * Ammessi con il test INVIATO, intersecati con gli id richiesti: mai un id arrivato dal form senza questa intersezione.
     *
     * @param  list<int>|null  $discentiIds
     * @return list<int>
     */
    private static function conTestInviato(Esame $esame, ?array $discentiIds): array
    {
        $ammessi = $esame->discentiAmmessi()->pluck('iscrizioni.partecipante_id')->map(fn ($id) => (int) $id);
        $inviati = $esame->testConsegne()->whereNotNull('consegnato_il')->pluck('discente_socio_id')->map(fn ($id) => (int) $id);
        $ids = $ammessi->intersect($inviati);
        if ($discentiIds !== null) {
            $ids = $ids->intersect(array_map('intval', $discentiIds));
        }

        return $ids->unique()->values()->all();
    }
}
