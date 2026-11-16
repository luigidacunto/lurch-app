<?php

namespace App\Http\Controllers;

use App\Exports\QuoteExport;
use App\Models\Corso;
use Maatwebsite\Excel\Facades\Excel;

class ExportQuoteExcelController extends Controller
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

        $slug = str($corso->numero_protocollo ?? $corso->nome)->slug();
        $nome = 'quote_' . $slug . '.xlsx';

        return Excel::download(new QuoteExport($corso), $nome);
    }
}
