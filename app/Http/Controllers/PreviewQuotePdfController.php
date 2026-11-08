<?php

namespace App\Http\Controllers;

use App\Models\Corso;
use Barryvdh\DomPDF\Facade\Pdf;

class PreviewQuotePdfController extends Controller
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
