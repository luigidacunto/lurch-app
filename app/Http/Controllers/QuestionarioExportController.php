<?php

namespace App\Http\Controllers;

use App\Exports\QuestionarioRisposteExport;
use App\Models\Corso;
use App\Models\QuestionarioGradimento;
use App\Questionari\InvioQuestionario;
use App\Questionari\PermessiQuestionario;
use App\Questionari\StatisticheQuestionario;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Export del questionario di gradimento (Excel delle risposte, PDF del corso, PDF di un singolo valutato da
 * consegnare al docente). Stessa regola della pagina: PermessiQuestionario::puoGestire, e nulla sotto la soglia.
 */
class QuestionarioExportController extends Controller
{
    public function excel(Corso $corso)
    {
        $questionario = $this->questionario($corso);

        return Excel::download(new QuestionarioRisposteExport($questionario), 'questionario_' . str($corso->numero_protocollo ?: $corso->nome)->slug() . '.xlsx', null, ['Cache-Control' => 'private, no-store']);
    }

    public function pdf(Corso $corso)
    {
        $questionario = $this->questionario($corso);

        return $this->rispostaPdf($corso, StatisticheQuestionario::per($questionario), 'Riepilogo del corso', 'questionario_riepilogo');
    }

    public function pdfValutato(Corso $corso, int $indice)
    {
        $questionario = $this->questionario($corso);
        $valutato = $questionario->valutati[$indice] ?? abort(404);

        return $this->rispostaPdf(
            $corso,
            StatisticheQuestionario::per($questionario, $indice),
            InvioQuestionario::titoloValutato($valutato),
            'questionario_' . str($valutato['nome'])->slug(),
        );
    }

    private function questionario(Corso $corso): QuestionarioGradimento
    {
        abort_unless(PermessiQuestionario::puoGestire(auth()->user(), $corso), 403);
        $questionario = $corso->questionarioGradimento;
        abort_unless($questionario?->eInviato() && StatisticheQuestionario::visibili($questionario), 404);

        return $questionario;
    }

    private function rispostaPdf(Corso $corso, array $sezioni, string $sottotitolo, string $file)
    {
        $pdf = Pdf::loadView('pdf.questionario-gradimento', [
            'corso'        => $corso,
            'questionario' => $corso->questionarioGradimento,
            'sezioni'      => $sezioni,
            'sottotitolo'  => $sottotitolo,
            'compilazioni' => StatisticheQuestionario::compilazioni($corso->questionarioGradimento),
        ])->setPaper('a4');

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $file . '_' . str($corso->numero_protocollo ?: $corso->nome)->slug() . '.pdf"',
            'Cache-Control'       => 'private, no-store',
        ]);
    }
}
