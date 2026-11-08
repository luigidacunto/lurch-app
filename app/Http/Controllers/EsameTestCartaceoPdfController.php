<?php

namespace App\Http\Controllers;

use App\Esami\TestScritto;
use App\Models\Esame;
use App\Models\Socio;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * PDF del test scritto da stampare per UN discente (BES/DSA): dati del discente (nome e cognome, mai il codice
 * fiscale), regola di punteggio, domande con le 4 opzioni. ⚠️ MAI le risposte corrette: il foglio lo riceve il discente
 * (le domande passano da TestScritto::domandePerDiscente, che non carica `corretta`). Libero e senza effetti: non avvia,
 * non cambia stato. Lo chiede chi gestisce l'esame (Esame::puoScaricareTestCartaceo), in qualsiasi stato.
 * Se il test è ancora in bozza il foglio dice che non vale per lo svolgimento.
 */
class EsameTestCartaceoPdfController extends Controller
{
    public function __invoke(Esame $esame, Socio $discente)
    {
        abort_unless($esame->puoScaricareTestCartaceo(auth()->user()), 403);
        abort_unless($esame->discentiAmmessi()->where('iscrizioni.partecipante_id', $discente->id)->exists(), 404);
        $domande = TestScritto::domandePerDiscente($esame);
        abort_if($domande === [], 404, 'Il test non ha ancora domande.');

        $pdf = Pdf::loadView('pdf.test-cartaceo', [
            'esame'   => $esame->load('corso'),
            'discente' => $discente,
            'domande' => $domande,
            'regola'  => TestScritto::regola($esame),
            'bozza'   => $esame->statoTest() === Esame::TEST_BOZZA,
        ])->setPaper('a4');

        $nome = 'test_'.str($discente->cognome.' '.$discente->nome)->slug().'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nome.'"',
            'Cache-Control'       => 'private, no-store',
        ]);
    }
}
