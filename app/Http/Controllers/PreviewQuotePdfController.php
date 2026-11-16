<?php

namespace App\Http\Controllers;

use App\Models\Corso;
use Barryvdh\DomPDF\Facade\Pdf;

class PreviewQuotePdfController extends Controller
{
    public function __invoke(Corso $corso)
    {
        abort_unless(auth()->check(), 403);

        // Stessa regola della pagina Gestione corso da cui parte il link.
        abort_unless(\App\Filament\Resources\CorsoResource::puoGestireCorso($corso), 403);
        abort_unless($corso->pagamento_previsto, 404);

        $corso->load([
            'evento.comitatoRegionale',
            'evento.comitatoLocale',
            'iscrizioni.partecipante.comitatoLocale',
            'iscrizioni.partecipante.comitatoRegionale',
            'iscrizioni.rispostaPreregistrazione.verificatoDa',
        ]);

        $inline = request()->boolean('download') === false;

        $pdf = Pdf::loadView('pdf.gestione-quote', compact('corso'))
            ->setPaper('a4', 'landscape');

        $slug = str($corso->numero_protocollo ?? $corso->nome)->slug();
        $nome = 'quote_' . $slug . '.pdf';
        $disposition = request()->boolean('download') ? 'attachment' : 'inline';

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => $disposition . '; filename="' . $nome . '"',
            'Cache-Control'       => 'private, no-store',
        ]);
    }
}
