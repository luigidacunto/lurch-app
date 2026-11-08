<?php

namespace App\Esami;

use App\Models\Esame;
use App\Models\Esito;
use App\Models\User;
use App\Services\SocioCorrente;
use Illuminate\Support\Facades\DB;

/**
 * Chiusura e riapertura dell'esame.
 *
 *  - chiudi: SOLO il direttore (Esame::puoInserireFinali), a esame in corso, SOLO se ogni discente ammesso ha tutti i dati
 *    (scelta di progetto: niente esiti «non calcolabili»). Scrive lo snapshot in `esiti` (ammessi + non ammessi con
 *    motivo, mai i ritirati) e porta l'esame a `chiuso`. Dopo non si ricalcola più: PDF/CSV leggono lo snapshot.
 *  - riapri: SOLO il superadmin, con motivo, ENTRO 15 giorni di calendario dalla chiusura (Esame::RIAPERTURA_GIORNI);
 *    oltre, in nessun modo. Sovrascrive `riaperto_*` (nessuno storico), azzera `chiuso_*` e CANCELLA gli esiti.
 * Il calcolo è sempre e solo CalcoloTcri::calcola() (via RisultatiEsame).
 */
class ChiusuraEsame
{
    public const MOTIVO_MIN_CARATTERI = 10;

    /**
     * Motivo per cui l'esame non si può chiudere adesso, null se si può. Ordine: stato, chi sei, dati mancanti.
     *
     * @param  list<array<string,mixed>>|null  $righe  RisultatiEsame::per() già calcolato (evita di rifarlo)
     */
    public static function motivoNonChiudibile(Esame $esame, ?User $utente, ?array $righe = null): ?string
    {
        if ($esame->isChiuso()) {
            return "L'esame è già chiuso.";
        }
        if ($esame->stato !== Esame::STATO_IN_CORSO || $esame->modello_snapshot === null) {
            return "L'esame non è ancora attivo.";
        }
        if (! $esame->isDirettore($utente)) {
            $direttore = $esame->corso?->direttore;
            $nome = $direttore?->nome_completo ?? $esame->corso?->direttore_corso ?? 'direttore non indicato';
            if ($direttore && ! User::where('socio_id', $direttore->id)->exists()) {
                return "Il direttore {$nome} non ha un utente del pannello collegato al suo socio: collegalo da Utenti perché possa chiudere.";
            }

            return "Può chiudere l'esame solo il direttore del corso ({$nome}).";
        }

        $righe ??= RisultatiEsame::per($esame);
        $incompleti = array_values(array_filter($righe, fn ($r) => $r['stato'] === 'incompleto'));
        if ($incompleti !== []) {
            return 'Mancano dati per '.count($incompleti).' '.(count($incompleti) === 1 ? 'discente ammesso' : 'discenti ammessi').': vedi le righe «Incompleto».';
        }
        if (collect($righe)->where('stato', Esito::STATO_CALCOLATO)->isEmpty()) {
            return "L'esame non ha discenti ammessi.";
        }

        return null;
    }

    /** @throws \RuntimeException con il motivo, se non si può chiudere. */
    public static function chiudi(Esame $esame, ?User $utente): void
    {
        DB::transaction(function () use ($esame, $utente) {
            // Serializza con chi scrive i dati del calcolo (che rilegge l'esame con sharedLock()).
            $fresco = Esame::query()->lockForUpdate()->findOrFail($esame->getKey());
            if ($motivo = self::motivoNonChiudibile($fresco, $utente)) {
                throw new \RuntimeException($motivo);
            }

            $righe = RisultatiEsame::per($fresco);
            $ora = now();
            $fresco->esiti()->delete(); // difesa: di norma la riapertura li ha già tolti
            foreach ($righe as $riga) {
                Esito::create(RisultatiEsame::perEsito($riga, $fresco) + ['chiuso_il' => $ora]);
            }
            $fresco->update([
                'stato' => Esame::STATO_CHIUSO,
                'chiuso_il' => $ora,
                'chiuso_da' => SocioCorrente::perUtente($utente),
            ]);
            $esame->refresh();
        });
    }

    /** Motivo per cui l'esame non si può riaprire, null se si può (tooltip). */
    public static function motivoNonRiapribile(Esame $esame, ?User $utente): ?string
    {
        if (! $esame->isChiuso()) {
            return "L'esame non è chiuso.";
        }
        if (! $utente?->isSuperadmin()) {
            return 'Solo il superadmin può riaprire un esame chiuso.';
        }
        if ($esame->riapribileFinoAl() === null || now()->greaterThan($esame->riapribileFinoAl())) {
            return 'Sono passati più di '.Esame::RIAPERTURA_GIORNI." giorni dalla chiusura: l'esame non è più riapribile.";
        }

        return null;
    }

    /** @throws \RuntimeException */
    public static function riapri(Esame $esame, ?User $utente, string $motivo): void
    {
        $motivo = trim($motivo);
        DB::transaction(function () use ($esame, $utente, $motivo) {
            $fresco = Esame::query()->lockForUpdate()->findOrFail($esame->getKey());
            if ($m = self::motivoNonRiapribile($fresco, $utente)) {
                throw new \RuntimeException($m);
            }
            if (mb_strlen($motivo) < self::MOTIVO_MIN_CARATTERI) {
                throw new \RuntimeException('Il motivo della riapertura è obbligatorio (almeno '.self::MOTIVO_MIN_CARATTERI.' caratteri).');
            }

            $fresco->esiti()->delete();
            // «Apri la prova finale» e gli avvii del test restano com'erano: le guardie guardano solo lo stato.
            $fresco->update([
                'stato' => Esame::STATO_IN_CORSO,
                'chiuso_il' => null,
                'chiuso_da' => null,
                'riaperto_da' => $utente->id,
                'riaperto_il' => now(),
                'riaperto_motivo' => $motivo,
            ]);
            $esame->refresh();
        });
    }
}
