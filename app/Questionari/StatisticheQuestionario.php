<?php

namespace App\Questionari;

use App\Models\QuestionarioGradimento;

/**
 * Risultati aggregati. Sotto SOGLIA compilazioni non si mostra nulla (anche a questionario aperto): il primo che
 * risponde non deve essere riconoscibile. Testi in ordine casuale, senza date.
 */
class StatisticheQuestionario
{
    public const SOGLIA = 5;

    /**
     * Barra della scala 1–5 con le stesse fasce delle valutazioni d'esame (App\Esami\ColoreScala), in tinte piene:
     * rosso e arancione sotto la sufficienza, verde per il 3, azzurro e blu sopra. Le chiarezze alternate tengono
     * distinti i segmenti vicini anche per i daltonici; i conteggi stanno scritti dentro i segmenti, con il colore
     * del testo indicato qui.
     */
    public const COLORI_SCALA = [
        1 => ['#c42f30', '#ffffff'],
        2 => ['#f5a04a', '#111827'],
        3 => ['#3f9d5a', '#111827'],
        4 => ['#7cc0f0', '#111827'],
        5 => ['#1b64be', '#ffffff'],
    ];

    public static function compilazioni(QuestionarioGradimento $questionario): int
    {
        return $questionario->risposte()->count();
    }

    public static function visibili(QuestionarioGradimento $questionario): bool
    {
        return static::compilazioni($questionario) >= self::SOGLIA;
    }

    /**
     * Blocchi attivi (Domande::blocchi) con le statistiche di ogni domanda e, per le domande a scala, il riepilogo del
     * blocco (media e distribuzione di tutte le sue risposte 1–5). Con $soloValutato (indice in `valutati`) restituisce
     * solo la sezione di quel docente: è ciò che vede il docente stesso.
     *
     * @return array<int, array{titolo: string, valutato: ?int, riepilogo: ?array, parti: array<int, array{sottotitolo: ?string, domande: array}>}>
     */
    public static function per(QuestionarioGradimento $questionario, ?int $soloValutato = null): array
    {
        $risposte = $questionario->risposte()->pluck('risposte')->all();
        $valutati = $questionario->valutati ?? [];
        $out = [];

        foreach (Domande::blocchi($questionario->versione, $questionario->sezioni ?? []) as $blocco) {
            if ($soloValutato !== null && ! $blocco['per_valutato']) {
                continue;
            }
            $ambiti = $blocco['per_valutato'] ? array_keys($valutati) : [null];
            if ($soloValutato !== null) {
                $ambiti = array_intersect($ambiti, [$soloValutato]);
            }

            foreach ($ambiti as $i) {
                $nome = $i === null ? null : $valutati[$i]['nome'];
                $scala = [];
                $parti = array_map(function (array $parte) use ($risposte, $i, $nome, &$scala) {
                    return [
                    'sottotitolo' => $parte['sottotitolo'],
                    'domande'     => array_map(function (array $d) use ($risposte, $i, $nome, &$scala) {
                        $valori = array_values(array_filter(
                            array_map(fn (array $r) => $i === null ? ($r[$d['key']] ?? null) : ($r[$d['key']][$i] ?? null), $risposte),
                            fn ($v) => $v !== null,
                        ));
                        if ($d['tipo'] === 'scale5') {
                            array_push($scala, ...$valori);
                        }

                        return ['label' => str_replace('{docente}', (string) $nome, $d['label'])] + self::statistica($d, $valori, $risposte);
                    }, $parte['domande']),
                    ];
                }, $blocco['parti']);

                $out[] = [
                    'titolo'    => $i === null ? $blocco['titolo'] : InvioQuestionario::titoloValutato($valutati[$i]),
                    'valutato'  => $i,
                    'riepilogo' => $scala ? self::statistica(['tipo' => 'scale5'], $scala, []) : null,
                    'parti'     => $parti,
                ];
            }
        }

        return $out;
    }

    private static function statistica(array $d, array $valori, array $risposte): array
    {
        $n = count($valori);

        if ($d['tipo'] === 'scale5') {
            $conteggi = array_fill_keys(array_keys(Domande::SCALA), 0);
            foreach ($valori as $v) {
                $conteggi[(int) $v]++;
            }

            return ['tipo' => 'scala', 'n' => $n, 'media' => $n ? round(array_sum($valori) / $n, 2) : null, 'conteggi' => $conteggi];
        }

        if (in_array($d['tipo'], ['text_long', 'text_short'], true)) {
            shuffle($valori);

            return ['tipo' => 'testo', 'n' => $n, 'testi' => $valori];
        }

        $opzioni = match ($d['tipo']) {
            'yes_no' => ['Sì', 'No'],
            'single_choice_other' => [...$d['opzioni'], 'Altro'],
            default => $d['opzioni'],
        };
        $conteggi = array_fill_keys($opzioni, 0);
        foreach ($valori as $v) {
            if (array_key_exists($v, $conteggi)) {
                $conteggi[$v]++;
            }
        }
        $altri = [];
        if ($d['tipo'] === 'single_choice_other') {
            $altri = array_values(array_filter(array_map(fn (array $r) => $r[$d['key'] . '_altro'] ?? null, $risposte)));
            shuffle($altri);
        }

        return ['tipo' => 'scelta', 'n' => $n, 'conteggi' => $conteggi, 'altri' => $altri];
    }
}
