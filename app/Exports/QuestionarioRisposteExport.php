<?php

namespace App\Exports;

use App\Models\QuestionarioGradimento;
use App\Questionari\Domande;
use App\Questionari\InvioQuestionario;
use Maatwebsite\Excel\Concerns\FromArray;

/**
 * Una riga per risposta anonima, una colonna per domanda; le domande di S9 hanno una colonna per ogni coppia
 * valutato × voce. Righe in ordine di UUID (casuale): nessun ordine di compilazione. Testi del discente protetti
 * dalle formule come negli esiti dell'esame.
 */
class QuestionarioRisposteExport implements FromArray
{
    public function __construct(private QuestionarioGradimento $questionario) {}

    public function array(): array
    {
        $colonne = [];
        $valutati = $this->questionario->valutati ?? [];

        foreach (Domande::sezioniAttive($this->questionario->versione, $this->questionario->sezioni ?? []) as $sezione) {
            foreach ($sezione['per_valutato'] ? array_keys($valutati) : [null] as $i) {
                foreach ($sezione['domande'] as $d) {
                    $label = str_replace('{docente}', $i === null ? '' : $valutati[$i]['nome'], $d['label']);
                    $prefisso = $i === null ? '' : InvioQuestionario::titoloValutato($valutati[$i]) . ' – ';
                    $colonne[] = [$prefisso . $label, fn (array $r) => $i === null ? ($r[$d['key']] ?? null) : ($r[$d['key']][$i] ?? null)];
                    if ($d['tipo'] === 'single_choice_other') {
                        $colonne[] = [$label . ' (Altro)', fn (array $r) => $r[$d['key'] . '_altro'] ?? null];
                    }
                }
            }
        }

        $righe = [array_column($colonne, 0)];
        foreach ($this->questionario->risposte()->orderBy('id')->pluck('risposte') as $r) {
            $righe[] = array_map(fn (array $c) => is_string($v = $c[1]($r)) ? EsitiEsameExport::sicura($v) : $v, $colonne);
        }

        return $righe;
    }
}
