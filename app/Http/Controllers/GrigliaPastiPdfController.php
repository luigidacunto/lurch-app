<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Services\GrigliaPastiBuilder;
use Barryvdh\DomPDF\Facade\Pdf;

class GrigliaPastiPdfController extends Controller
{
    public function __invoke(Evento $evento, GrigliaPastiBuilder $builder)
    {
        abort_unless(auth()->check(), 403);

        $user = auth()->user();
        $hasAccess = $user->isSuperadmin()
            || ($user->isAdmin()       && $evento->comitato_regionale_id === $user->comitato_regionale_id)
            || ($user->isAdminLocale() && $evento->comitato_locale_id    === $user->comitato_locale_id)
            || ($user->isOperatore()   && $evento->operatori->contains($user->id));

        abort_unless($hasAccess, 403);

        $evento->load(['comitatoRegionale', 'comitatoLocale', 'corsi.iscrizioni.rispostaPreregistrazione', 'staff.rispostaPreregistrazione']);

        $dati = $builder->build($evento);

        $pdf = Pdf::loadView('pdf.pasti-griglia', [
            'evento'        => $evento,
            'righePresenze' => $dati['righePresenze'],
            'totali'        => $dati['totali'],
        ])->setPaper('a4', 'landscape');

        $slug        = str($evento->nome)->slug();
        $disposition = request()->boolean('download') ? 'attachment' : 'inline';

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => $disposition . '; filename="pasti_griglia_' . $slug . '.pdf"',
            'Cache-Control'       => 'private, no-store',
        ]);
    }
}
