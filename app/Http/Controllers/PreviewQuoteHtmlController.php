<?php

namespace App\Http\Controllers;

use App\Models\Corso;

class PreviewQuoteHtmlController extends Controller
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

        return view('quote.preview-html', compact('corso'));
    }
}
