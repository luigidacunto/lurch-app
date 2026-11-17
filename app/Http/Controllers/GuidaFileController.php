<?php

namespace App\Http\Controllers;

use App\Support\Guida;
use Barryvdh\DomPDF\Facade\Pdf;

/** File delle pagine «Guida» del pannello: immagini di docs/immagini/ e PDF di una sezione intera. */
class GuidaFileController extends Controller
{
    public function immagine(string $percorso)
    {
        $file = Guida::fileImmagine('immagini/' . $percorso);
        abort_unless($file, 404);

        return response()->file($file, ['Cache-Control' => 'private, max-age=86400']);
    }

    public function pdf(string $sezione)
    {
        abort_unless(isset(Guida::PAGINE[$sezione]), 404);

        $pagine = Guida::sezioneCompleta($sezione);
        $pdf    = Pdf::loadView('pdf.guida', [
            'titolo'   => Guida::PAGINE[$sezione]::getNavigationLabel(),
            'pagine'   => $pagine,
            'versione' => config('app.version'),
        ])->setPaper('a4');

        return $pdf->download('guida-' . substr($sezione, 3) . '.pdf');
    }
}
