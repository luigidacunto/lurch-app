<?php

namespace App\Questionari;

use App\Models\QuestionarioGradimento;
use App\Models\QuestionarioInvito;
use App\Models\QuestionarioRisposta;
use Illuminate\Support\Facades\DB;

/**
 * Compilazione dall'app, dopo il login del socio: niente token. Mentre compila, le risposte valide si salvano come
 * bozza cifrata sull'invito (per non perderle); all'invio, in un'unica transazione, l'invito diventa «compilato»,
 * la bozza si cancella e si inserisce la risposta anonima, senza nessun legame tra le due righe.
 */
class CompilazioneQuestionario
{
    public const MAX_TESTO_LUNGO = 5000;
    public const MAX_TESTO_BREVE = 500;

    /** Invito del socio a questo questionario (null se non è stato invitato). */
    public static function invito(QuestionarioGradimento $questionario, int $socioId): ?QuestionarioInvito
    {
        return $questionario->inviti()
            ->whereHas('iscrizione', fn ($q) => $q->where('partecipante_id', $socioId))
            ->first();
    }

    /**
     * Valida le risposte rispetto a sezioni attive, `required` e `show_if`; i valori nascosti o sconosciuti si scartano.
     *
     * @return array{0: array<string, mixed>, 1: array<string, string>} [risposte pulite, errori per campo]
     */
    public static function valida(QuestionarioGradimento $questionario, array $input): array
    {
        $pulite = [];
        $errori = [];
        $valutati = $questionario->valutati ?? [];

        foreach (Domande::sezioniAttive($questionario->versione, $questionario->sezioni ?? []) as $sezione) {
            $ambiti = $sezione['per_valutato'] ? array_keys($valutati) : [null];

            foreach ($ambiti as $i) {
                // Ambito di una ripetizione: per S9 i valori sono in $input[key][i].
                $valore = fn (string $key) => $i === null ? ($input[$key] ?? null) : ($input[$key][$i] ?? null);

                foreach ($sezione['domande'] as $d) {
                    if ($d['show_if'] && $valore($d['show_if'][0]) !== $d['show_if'][1]) {
                        continue;
                    }
                    $campo = $i === null ? $d['key'] : "{$d['key']}.{$i}";
                    [$v, $errore, $altro] = self::controlla($d, $valore($d['key']), $valore($d['key'] . '_altro'));

                    if ($errore) {
                        $errori[$campo] = $errore;
                        continue;
                    }
                    if ($v === null) {
                        continue;
                    }
                    if ($i === null) {
                        $pulite[$d['key']] = $v;
                        if ($altro !== null) {
                            $pulite[$d['key'] . '_altro'] = $altro;
                        }
                    } else {
                        $pulite[$d['key']][$i] = $v;
                    }
                }
            }
        }

        return [$pulite, $errori];
    }

    /** @return array{0: mixed, 1: ?string, 2: ?string} [valore, errore, testo «Altro»] */
    private static function controlla(array $d, mixed $v, mixed $altro): array
    {
        $v = is_string($v) ? trim($v) : $v;
        if ($v === null || $v === '') {
            return [null, $d['required'] ? 'Risposta obbligatoria.' : null, null];
        }
        if (! is_scalar($v)) {
            return [null, 'Risposta non valida.', null];
        }

        return match ($d['tipo']) {
            'scale5' => ctype_digit((string) $v) && (int) $v >= 1 && (int) $v <= 5
                ? [(int) $v, null, null] : [null, 'Scegli un valore da 1 a 5.', null],
            'single_choice' => in_array($v, $d['opzioni'], true) ? [$v, null, null] : [null, 'Scegli una delle opzioni.', null],
            'yes_no' => in_array($v, ['Sì', 'No'], true) ? [$v, null, null] : [null, 'Scegli Sì o No.', null],
            'single_choice_other' => self::conAltro($d, $v, $altro),
            'text_long', 'text_short' => mb_strlen((string) $v) <= ($d['tipo'] === 'text_long' ? self::MAX_TESTO_LUNGO : self::MAX_TESTO_BREVE)
                ? [(string) $v, null, null] : [null, 'Testo troppo lungo.', null],
        };
    }

    private static function conAltro(array $d, mixed $v, mixed $altro): array
    {
        if (in_array($v, $d['opzioni'], true)) {
            return [$v, null, null];
        }
        if ($v !== 'Altro') {
            return [null, 'Scegli una delle opzioni.', null];
        }
        $altro = is_string($altro) ? trim($altro) : '';
        if ($altro === '') {
            return [null, 'Descrivi la risposta «Altro».', null];
        }

        return mb_strlen($altro) <= self::MAX_TESTO_BREVE ? ['Altro', null, $altro] : [null, 'Testo troppo lungo.', null];
    }

    /**
     * Salvataggio atomico. Rifiuta (false) se il questionario è chiuso, il socio non è invitato o ha già compilato.
     */
    public static function salva(QuestionarioGradimento $questionario, int $socioId, array $risposte): bool
    {
        return DB::transaction(function () use ($questionario, $socioId, $risposte) {
            $invito = QuestionarioInvito::query()
                ->where('questionario_id', $questionario->id)
                ->whereHas('iscrizione', fn ($q) => $q->where('partecipante_id', $socioId))
                ->lockForUpdate()
                ->first();

            if (! $invito || $invito->compilato || ! $questionario->fresh()->eAperto()) {
                return false;
            }

            $invito->update(['compilato' => true, 'bozza' => null]);
            QuestionarioRisposta::create(['questionario_id' => $questionario->id, 'risposte' => $risposte]);

            return true;
        });
    }

    /**
     * Salva come bozza le sole risposte valide (le altre si scartano senza errore). Rifiuta (false) se il questionario
     * è chiuso, il socio non è invitato o ha già compilato. Una bozza senza risposte non conta come «iniziato».
     */
    public static function salvaBozza(QuestionarioGradimento $questionario, int $socioId, array $input): bool
    {
        [$valide] = static::valida($questionario, $input);

        // Stesso blocco dell'invio: un salvataggio arrivato un istante dopo l'invio non rimette una bozza nominativa.
        return DB::transaction(function () use ($questionario, $socioId, $valide) {
            $invito = QuestionarioInvito::query()
                ->where('questionario_id', $questionario->id)
                ->whereHas('iscrizione', fn ($q) => $q->where('partecipante_id', $socioId))
                ->lockForUpdate()
                ->first();
            if (! $invito || $invito->compilato || ! $questionario->eAperto()) {
                return false;
            }
            $invito->update(['bozza' => $valide ?: null, 'aperto' => true]);

            return true;
        });
    }

    /**
     * Conteggi per il pannello, mai nomi: invitati, mai aperto, iniziati (bozza salvata, non inviato), completati.
     *
     * @return array{invitati: int, mai_aperto: int, iniziati: int, completati: int}
     */
    public static function conteggi(QuestionarioGradimento $questionario): array
    {
        $c = $questionario->inviti()->toBase()->selectRaw('
            count(*) as invitati,
            count(*) filter (where not aperto and not compilato) as mai_aperto,
            count(*) filter (where bozza is not null and not compilato) as iniziati,
            count(*) filter (where compilato) as completati')->first();

        return array_map('intval', (array) $c);
    }

    /** Alla chiusura le bozze mai inviate si cancellano: non entrano nei risultati. Restituisce quante. */
    public static function cancellaBozzeScadute(): int
    {
        return QuestionarioInvito::query()
            ->whereNotNull('bozza')
            ->whereHas('questionario', fn ($q) => $q->where('chiude_il', '<', now()))
            ->update(['bozza' => null]);
    }
}
