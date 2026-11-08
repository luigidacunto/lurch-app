<?php

namespace App\Http\Controllers;

use App\Esami\RisultatiEsame;
use App\Models\Esame;
use App\Models\Socio;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * PDF dell'esito di UN discente. Chi legge l'Itinere può scaricarlo. Esame chiuso: i numeri vengono solo
 * dallo snapshot (RisultatiEsame::per); non chiuso: stesso foglio con la dicitura PROVVISORIO. Dati personali ammessi:
 * nome, cognome e codice fiscale; mai email, nascita, motivazioni o impressioni.
 */
class EsameEsitoPdfController extends Controller
{
    public function __invoke(Esame $esame, Socio $discente)
    {
        abort_unless($esame->puoVedereRiepilogoItinere(auth()->user()), 403);
        $riga = collect(RisultatiEsame::per($esame))->first(fn ($r) => $r['socio']->id === $discente->id);
        abort_unless($riga, 404);
        abort_if($riga['stato'] === 'incompleto' && $esame->isChiuso(), 404);

        $pdf = Pdf::loadView('pdf.esito-esame', [
            'esame' => $esame->load('corso.direttore', 'chiusoDa'),
            'riga' => $riga,
            'provvisorio' => ! $esame->isChiuso(),
            'pesi' => $esame->modello_snapshot['pesi'] ?? [],
        ])->setPaper('a4');

        $nome = 'esito_'.str($discente->cognome.' '.$discente->nome)->slug().($esame->isChiuso() ? '' : '_provvisorio').'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nome.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
