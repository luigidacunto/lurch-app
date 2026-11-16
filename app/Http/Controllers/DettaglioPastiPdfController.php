<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Services\GrigliaPastiBuilder;
use Barryvdh\DomPDF\Facade\Pdf;

class DettaglioPastiPdfController extends Controller
{
    public function __invoke(Evento $evento, GrigliaPastiBuilder $builder)
    {
        abort_unless(auth()->check(), 403);
        // Stessa regola della pagina Gestione evento da cui parte il link.
        abort_unless(\App\Filament\Resources\EventoResource::canEdit($evento), 403);

        $evento->load(['comitatoRegionale', 'comitatoLocale', 'corsi.iscrizioni.rispostaPreregistrazione', 'staff.rispostaPreregistrazione']);

        $dati = $builder->build($evento);

        // Solo i pasti con almeno una persona con esigenze
        $pastiConEsigenze = array_values(array_filter(
            $dati['righePresenze'],
            fn ($r) => ! empty($r['personeConEsigenze'])
        ));

        $pdf = Pdf::loadView('pdf.pasti-dettaglio', [
            'evento'           => $evento,
            'pastiConEsigenze' => $pastiConEsigenze,
        ])->setPaper('a4', 'portrait');

        $slug        = str($evento->nome)->slug();
        $disposition = request()->boolean('download') ? 'attachment' : 'inline';

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => $disposition . '; filename="pasti_dettaglio_' . $slug . '.pdf"',
            'Cache-Control'       => 'private, no-store',
        ]);
    }
}
