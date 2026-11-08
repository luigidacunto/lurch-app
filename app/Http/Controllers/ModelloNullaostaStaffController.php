<?php

namespace App\Http\Controllers;

use App\Models\IscrizioneStaff;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * Modello "Presa visione del Presidente" (nullaosta staff Logistica).
 *
 * - Con {token} (rotta form-staff.modello-nullaosta): precompilato coi dati del form
 *   staff. Stesse condizioni di accesso del form staff stesso (vedi
 *   FormPreregistrazioneStaffController::show): token valido, form ancora aperto,
 *   preregistrazione non ancora inviata. Se l'iscrizione è stata rimossa
 *   (StaffRimozione fa hard-delete) il token non risolve → 404.
 * - Senza token (rotta modelli.presa-visione-staff): modello generale vuoto, pubblico.
 *
 * `?scarica=1` = download invece di anteprima inline.
 */
class ModelloNullaostaStaffController extends Controller
{
    public function __invoke(?string $token = null): Response
    {
        $iscrizione = $token
            ? IscrizioneStaff::with(['evento', 'partecipante.comitatoLocale', 'rispostaPreregistrazione'])
                ->where('token', $token)
                ->firstOrFail()
            : null;

        if ($iscrizione) {
            // Form chiuso o preregistrazione già inviata → il modello precompilato non serve più.
            abort_unless($iscrizione->evento?->form_staff_aperto_at, 404);
            abort_unless($iscrizione->rispostaPreregistrazione === null, 404);
        }

        $pdf = Pdf::loadView('pdf.modello-nullaosta-staff', compact('iscrizione'));

        $cognome     = str($iscrizione?->partecipante?->cognome ?? '')->slug();
        $filename    = $cognome ? "presa-visione-staff_{$cognome}.pdf" : 'presa-visione-staff.pdf';
        $disposition = request()->boolean('scarica') ? 'attachment' : 'inline';

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => $disposition . '; filename="' . $filename . '"',
            'Cache-Control'       => 'private, no-store',
        ]);
    }
}
