<?php

namespace App\Esami;

use App\Models\Esame;
use App\Models\EsitoPubblicazione;
use App\Models\Esito;
use App\Models\User;
use App\Services\SocioCorrente;

/**
 * Pubblicazione dell'esito finale al discente, uno alla volta (si chiamano i discenti per il feedback finale): nella app
 * vede totale, idoneità e contributo delle prove. Solo per chi ha l'esito CALCOLATO (tutte le prove fissate); chi gestisce
 * l'esame (Esame::puoPubblicareRisultati), a esame in corso o chiuso. Nessun blocco sulle valutazioni dopo la pubblicazione
 * (scelta del proprietario): il discente vede sempre i valori attuali di RisultatiEsame, e dopo la chiusura quelli salvati.
 */
class PubblicazioneEsito
{
    /** @throws TestException */
    public static function pubblica(Esame $esame, ?User $utente, int $discenteId): void
    {
        self::controlla($esame, $utente);
        if ($motivo = self::motivoNonPubblicabilePer($esame, $discenteId)) {
            throw new TestException($motivo, 409);
        }

        $p = EsitoPubblicazione::firstOrNew(['esame_id' => $esame->id, 'discente_socio_id' => $discenteId]);
        if (! $p->ePubblicata()) {
            $p->fill(['pubblicato_il' => now(), 'pubblicato_da' => SocioCorrente::perUtente($utente)])->save();
        }
    }

    /**
     * «Pubblica l'esito a tutti»: a ogni discente con l'esito calcolato e non ancora pubblicato; salta gli incompleti e i
     * non ammessi. Ripetibile: ogni volta pubblica solo chi nel frattempo si è completato.
     *
     * @return int quanti esiti sono stati pubblicati adesso
     *
     * @throws TestException
     */
    public static function pubblicaTutti(Esame $esame, ?User $utente): int
    {
        self::controlla($esame, $utente);
        $ids = self::daPubblicare($esame);
        $da = SocioCorrente::perUtente($utente);
        foreach ($ids as $discenteId) {
            EsitoPubblicazione::updateOrCreate(
                ['esame_id' => $esame->id, 'discente_socio_id' => $discenteId],
                ['pubblicato_il' => now(), 'pubblicato_da' => $da],
            );
        }

        return count($ids);
    }

    /**
     * Discenti con l'esito calcolato e non ancora pubblicato.
     *
     * @param  list<array>|null  $righe  righe di RisultatiEsame già lette
     * @return list<int>
     */
    public static function daPubblicare(Esame $esame, ?array $righe = null): array
    {
        $pubblicati = $esame->esitoPubblicazioni()->whereNotNull('pubblicato_il')->pluck('discente_socio_id')->map(fn ($id) => (int) $id)->all();

        return collect($righe ?? RisultatiEsame::per($esame))
            ->filter(fn (array $r) => $r['stato'] === Esito::STATO_CALCOLATO)
            ->map(fn (array $r) => (int) $r['socio']->id)
            ->reject(fn (int $id) => in_array($id, $pubblicati, true))
            ->values()->all();
    }

    /** @throws TestException */
    public static function ritira(Esame $esame, ?User $utente, int $discenteId): void
    {
        self::controlla($esame, $utente);
        EsitoPubblicazione::where('esame_id', $esame->id)->where('discente_socio_id', $discenteId)
            ->whereNotNull('pubblicato_il')->update(['pubblicato_il' => null, 'updated_at' => now()]);
    }

    /** Riga di RisultatiEsame del discente (ammesso), null se non c'è. */
    public static function riga(Esame $esame, int $discenteId): ?array
    {
        return collect(RisultatiEsame::per($esame))->first(fn (array $r) => (int) $r['socio']->id === $discenteId);
    }

    /** Perché l'esito di QUESTO discente non si può pubblicare (tooltip), null se si può. */
    public static function motivoNonPubblicabilePer(Esame $esame, int $discenteId, ?array $riga = null): ?string
    {
        $riga ??= self::riga($esame, $discenteId);
        if ($riga === null) {
            return 'Discente non trovato tra gli iscritti.';
        }

        return match ($riga['stato']) {
            Esito::STATO_CALCOLATO => null,
            Esito::STATO_NON_AMMESSO => 'Non ammesso: nessun esito da pubblicare.',
            default => 'Mancano valutazioni: l\'esito si pubblica quando tutte le prove sono fissate.',
        };
    }

    /** @throws TestException */
    private static function controlla(Esame $esame, ?User $utente): void
    {
        if ($motivo = PubblicazioneTest::motivoNonPubblicabile($esame, $utente)) {
            throw new TestException($motivo, 403);
        }
    }
}
