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

        $user   = auth()->user();
        $evento = $corso->evento;

        $hasAccess = $user->isSuperadmin()
            || ($user->isAdmin() && $evento?->comitato_regionale_id === $user->comitato_regionale_id)
            || ($user->isAdminLocale() && $evento?->comitato_locale_id === $user->comitato_locale_id)
            || ($user->isOperatore() && $evento?->operatori->contains($user->id));

        abort_unless($hasAccess, 403);
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
