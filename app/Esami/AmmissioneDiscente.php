<?php

namespace App\Esami;

use App\Models\Esame;
use App\Models\Iscrizione;
use App\Models\TcriColloquio;
use App\Models\TcriProvaPratica;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «Non ammesso»: solo il direttore, solo a esame in corso (stessa regola delle schede finali:
 * Esame::puoInserireFinali). Scrive SOLO `iscrizioni.non_ammesso` e `non_ammesso_motivo`: non cancella
 * mai schede o test. Riguarda solo la prova finale; i ritirati non si toccano (si ritira dal corso).
 * Reversibile: togliendo il flag tutto ciò che era stato scritto riappare.
 */
class AmmissioneDiscente
{
    /** @throws \RuntimeException non autorizzato / discente non valido; ValidationException per il motivo. */
    public static function segnaNonAmmesso(Esame $esame, ?User $utente, int $discenteId, string $motivo): void
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw ValidationException::withMessages(['motivo' => 'Il motivo è obbligatorio.']);
        }
        self::scrivi($esame, $utente, $discenteId, true, $motivo);
    }

    public static function togliNonAmmesso(Esame $esame, ?User $utente, int $discenteId): void
    {
        self::scrivi($esame, $utente, $discenteId, false, null);
    }

    /** Motivo per cui l'azione non è disponibile (tooltip), null se lo è. */
    public static function motivoNonDisponibile(Esame $esame, ?User $utente): ?string
    {
        if ($esame->isChiuso()) {
            return "L'esame è chiuso: sola lettura.";
        }
        if ($esame->stato !== Esame::STATO_IN_CORSO) {
            return "L'esame non è ancora attivo.";
        }
        if (! $esame->isDirettore($utente)) {
            return "Può segnare un discente come non ammesso solo il direttore del corso.";
        }

        return null;
    }

    /**
     * Cosa verrebbe nascosto segnando il discente non ammesso (per la modale; nulla viene cancellato).
     *
     * @return list<string>
     */
    public static function impatto(Esame $esame, int $discenteId): array
    {
        $r = [];
        $aree = $esame->decisioniItinere()->where('discente_socio_id', $discenteId)->count();
        $col = $esame->colloqui()->where('tipo', TcriColloquio::TIPO_FINALE)->where('discente_socio_id', $discenteId)->exists();
        $pra = $esame->provePratiche()->where('tipo', TcriProvaPratica::TIPO_FINALE)->where('discente_socio_id', $discenteId)->exists();
        if ($aree || $col || $pra) {
            $r[] = 'schede finali già inserite ('.($aree ? "{$aree} aree d'itinere" : '').($col ? ($aree ? ', ' : '').'colloquio' : '').($pra ? ($aree || $col ? ', ' : '').'prova pratica' : '').')';
        }
        if (TestScritto::consegna($esame, $discenteId)?->eInviata()) {
            $r[] = 'test scritto già inviato (resta salvato ma non conta)';
        } elseif (TestScritto::avvio($esame, $discenteId)?->eAttivo()) {
            $r[] = 'test avviato: il discente non lo vedrà più';
        }

        return $r;
    }

    private static function scrivi(Esame $esame, ?User $utente, int $discenteId, bool $nonAmmesso, ?string $motivo): void
    {
        DB::transaction(function () use ($esame, $utente, $discenteId, $nonAmmesso, $motivo) {
            Esame::verificaInCorsoConBlocco($esame->id);
            if ($m = self::motivoNonDisponibile($esame->fresh(), $utente)) {
                throw new \RuntimeException($m);
            }
            $iscrizione = $esame->discenti()->where('iscrizioni.partecipante_id', $discenteId)->first();
            if (! $iscrizione) {
                throw new \RuntimeException("Il discente non fa parte dell'esame (o è ritirato).");
            }
            Iscrizione::whereKey($iscrizione->id)->update([
                'non_ammesso' => $nonAmmesso,
                'non_ammesso_motivo' => $nonAmmesso ? $motivo : null,
            ]);
        });
    }
}
