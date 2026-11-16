<?php

namespace App\Http\Controllers;

use App\Models\Corso;

class PreviewQuoteHtmlController extends Controller
{
    public function __invoke(Corso $corso)
    {
        abort_unless(auth()->check(), 403);

        // Stessa regola della pagina Gestione corso da cui parte il link.
        abort_unless(\App\Filament\Resources\CorsoResource::puoGestireCorso($corso), 403);
        abort_unless($corso->pagamento_previsto, 404);

        $corso->load([
            'evento',
            'iscrizioni.partecipante.comitatoLocale',
            'iscrizioni.partecipante.comitatoRegionale',
            'iscrizioni.rispostaPreregistrazione',
        ]);

        return view('quote.preview-html', compact('corso'));
    }
}
