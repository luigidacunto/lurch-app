<?php

namespace App\Http\Controllers;

use App\Esami\TestScritto;
use App\Models\Esame;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * PDF «correttore» del test scritto: tutte le domande con le 4 opzioni e la risposta corretta salvata, per verificare
 * il caricamento. ⚠️ Contiene le risposte corrette: solo chi gestisce l'esame (Esame::puoScaricareCorrettoreTest),
 * mai il discente. Ordine di origine, nessun mescolamento. Libero e senza effetti, in qualsiasi stato del test.
 */
class EsameCorrettoreTestPdfController extends Controller
{
    public function __invoke(Esame $esame)
    {
        abort_unless($esame->puoScaricareCorrettoreTest(auth()->user()), 403);
        $domande = TestScritto::domandeComplete($esame);
        abort_if($domande === [], 404, 'Il test non ha ancora domande.');

        $pdf = Pdf::loadView('pdf.test-correttore', [
            'esame'   => $esame->load('corso'),
            'domande' => $domande,
            'regola'  => TestScritto::regola($esame),
            'massimo' => \App\Esami\Formato::unDecimale(TestScritto::massimo($esame)),
        ])->setPaper('a4');

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="correttore_test_'.str($esame->titolo)->slug().'.pdf"',
            'Cache-Control'       => 'private, no-store',
        ]);
    }
}
