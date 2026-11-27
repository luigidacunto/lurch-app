<?php

namespace App\Questionari;

use App\Mail\QuestionarioGradimentoInvito;
use App\Models\Corso;
use App\Models\EsitoPubblicazione;
use App\Models\Iscrizione;
use App\Models\QuestionarioGradimento;
use App\Models\QuestionarioInvito;
use App\Models\Socio;
use App\Models\TestPubblicazione;
use App\Models\User;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Invio del questionario. Il primo invio fissa versione, sezioni, valutati e data di chiusura (23:59 del giorno
 * invio + N); gli invii successivi, finché è aperto, aggiungono solo chi non l'ha mai ricevuto: nessuna seconda
 * email alla stessa persona, nessun sollecito.
 */
class InvioQuestionario
{
    /** Motivo per cui oggi non si può inviare, null se si può. */
    public static function motivoBlocco(Corso $corso, ?QuestionarioGradimento $questionario): ?string
    {
        return match (true) {
            $corso->isAnnullato()                    => 'Corso annullato: il questionario non si invia.',
            ! today()->gt($corso->data_fine)         => 'Il questionario si invia dal giorno dopo la fine del corso (' . $corso->data_fine->format('d/m/Y') . ').',
            ! $corso->direttore_socio_id             => 'Collega il direttore del corso a un socio (modifica del corso) prima di inviare il questionario.',
            $questionario?->eChiuso() === true       => 'Questionario chiuso: non si possono più aggiungere destinatari.',
            $questionario === null                   => 'Imposta prima giorni di apertura e sezioni del questionario.',
            default                                  => null,
        };
    }

    /**
     * Direttore e docenti valutati in S9: il direttore sempre per primo, una volta sola anche se è tra i docenti.
     * Il direttore è sempre un socio: l'invio lo richiede (motivoBlocco).
     */
    public static function valutati(Corso $corso): array
    {
        $valutati = [['socio_id' => $corso->direttore_socio_id, 'nome' => $corso->direttore?->nome_completo ?? $corso->direttore_corso, 'ruolo' => 'direttore']];

        $corso->docenti()->orderBy('cognome')->orderBy('nome')->get()
            ->reject(fn (Socio $s) => $s->id === $corso->direttore_socio_id)
            ->each(function (Socio $s) use (&$valutati) {
                $valutati[] = ['socio_id' => $s->id, 'nome' => $s->nome_completo, 'ruolo' => 'docente'];
            });

        return $valutati;
    }

    public static function titoloValutato(array $valutato): string
    {
        return ($valutato['ruolo'] === 'direttore' ? 'Direttore del corso – ' : 'Docente – ') . $valutato['nome'];
    }

    /**
     * La frase «risultati dell'esame consultabili nell'app» va a chi ha pubblicati il test scritto o l'esito finale,
     * mai a ritirati o non ammessi (distinta dalla spunta «Esame finale» del questionario).
     */
    public static function fraseEsame(Iscrizione $iscrizione): bool
    {
        if ($iscrizione->isRitirato() || $iscrizione->isNonAmmesso()) {
            return false;
        }
        $esameId = $iscrizione->corso?->esame?->id;
        if (! $esameId) {
            return false;
        }

        $pubblicata = fn (string $modello) => $modello::where('esame_id', $esameId)
            ->where('discente_socio_id', $iscrizione->partecipante_id)
            ->whereNotNull('pubblicato_il')
            ->exists();

        return $pubblicata(TestPubblicazione::class) || $pubblicata(EsitoPubblicazione::class);
    }

    /** Selezionati ammessi e non ritirati che non riceverebbero la frase sull'esame (avviso prima dell'invio). */
    public static function senzaRisultatiPubblicati(Corso $corso, Collection $iscrizioni): int
    {
        if (! $corso->esame) {
            return 0;
        }

        return $iscrizioni
            ->reject(fn (Iscrizione $i) => $i->isRitirato() || $i->isNonAmmesso())
            ->reject(fn (Iscrizione $i) => static::fraseEsame($i))
            ->count();
    }

    /** @return array{inviati: int, saltati: int} saltati = senza email o già invitati */
    public static function invia(Corso $corso, QuestionarioGradimento $questionario, User $user, Collection $iscrizioni, Mailer $posta): array
    {
        if ($motivo = static::motivoBlocco($corso, $questionario)) {
            throw new \RuntimeException($motivo);
        }

        if (! $questionario->eInviato()) {
            DB::transaction(function () use ($corso, $questionario, $user) {
                $adesso = now();
                $questionario->update([
                    'versione'   => Domande::VERSIONE_CORRENTE,
                    'valutati'   => static::valutati($corso),
                    'inviato_da' => $user->id,
                    'inviato_il' => $adesso,
                    'chiude_il'  => $adesso->copy()->addDays($questionario->giorni_apertura)->endOfDay(),
                ]);
            });
        }

        $giaInvitati = $questionario->inviti()->pluck('iscrizione_id')->all();
        $inviati = 0;
        $saltati = 0;

        foreach ($iscrizioni as $iscrizione) {
            /** @var Iscrizione $iscrizione */
            $email = $iscrizione->partecipante?->email;
            if (! $email || $iscrizione->corso_id !== $corso->id || in_array($iscrizione->id, $giaInvitati, true)) {
                $saltati++;
                continue;
            }

            $posta->to($email)->send(new QuestionarioGradimentoInvito($iscrizione, $questionario, static::fraseEsame($iscrizione)));
            QuestionarioInvito::create(['questionario_id' => $questionario->id, 'iscrizione_id' => $iscrizione->id, 'inviato_il' => now()]);
            $inviati++;
        }

        return ['inviati' => $inviati, 'saltati' => $saltati];
    }
}
