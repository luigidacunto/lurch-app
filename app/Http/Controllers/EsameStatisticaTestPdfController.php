<?php

namespace App\Http\Controllers;

use App\Esami\Formato;
use App\Esami\TestScritto;
use App\Models\Esame;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * PDF della statistica delle risposte del test scritto: la stessa sezione che il direttore vede nel pannello (ordine del
 * test, conteggi per opzione, evidenze, domande annullate con autore, data e motivo), da condividere con i docenti.
 * Stessa regola della pagina Test scritto (Esame::puoVedereTestScritto), in qualsiasi stato, anche a esame chiuso.
 * ⚠️ Contiene le risposte corrette: mai al discente. Nessun nome di discente, solo conteggi.
 */
class EsameStatisticaTestPdfController extends Controller
{
    public function __invoke(Esame $esame)
    {
        abort_unless($esame->puoVedereTestScritto(auth()->user()), 403);
        $stat = TestScritto::statistica($esame);
        abort_if($stat['domande'] === [], 404, 'Il test non ha ancora domande.');

        $pdf = Pdf::loadView('pdf.test-statistica', [
            'esame'      => $esame->load('corso'),
            'stat'       => $stat,
            'nAnnullate' => count(array_filter($stat['domande'], fn (array $d) => $d['annullata'] !== null)),
            'evidenze'   => TestScritto::EVIDENZE,
            'regola'     => TestScritto::regola($esame),
            'massimo'    => Formato::unDecimale(TestScritto::massimo($esame)),
            'generata'   => now(),
        ])->setPaper('a4');

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="statistica_test_'.str($esame->titolo)->slug().'.pdf"',
            'Cache-Control'       => 'private, no-store',
        ]);
    }
}
