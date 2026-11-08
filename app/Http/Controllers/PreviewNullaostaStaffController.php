<?php

namespace App\Http\Controllers;

use App\Models\IscrizioneStaff;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class PreviewNullaostaStaffController extends Controller
{
    /**
     * Senza `?raw=1` rende la pagina di anteprima autenticata (documento-preview.blade.php,
     * PDF via pdf.js su canvas, indipendente dalle impostazioni PDF del browser).
     * Con `?raw=1` / `?scarica=1` serve il file vero (`inline` / `attachment`).
     */
    public function __invoke(Request $request, IscrizioneStaff $iscrizioneStaff): Response|View
    {
        abort_unless(auth()->check(), 403);

        $user   = auth()->user();
        $evento = $iscrizioneStaff->evento;

        $hasAccess = $user->isSuperadmin()
            || ($user->isAdmin() && $evento?->comitato_regionale_id === $user->comitato_regionale_id)
            || ($user->isAdminLocale() && $evento?->comitato_locale_id === $user->comitato_locale_id)
            || ($user->isOperatore() && $evento?->operatori->contains($user->id));

        abort_unless($hasAccess, 403);

        $path = $iscrizioneStaff->rispostaPreregistrazione?->nullaosta_path;
        abort_if(! $path, 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        if (! $request->boolean('raw') && ! $request->boolean('scarica')) {
            $nome = trim(($iscrizioneStaff->partecipante?->cognome ?? '') . ' ' . ($iscrizioneStaff->partecipante?->nome ?? ''));
            return view('documento-preview', [
                'titolo'     => 'Nullaosta — ' . $nome,
                'rawUrl'     => route('iscrizioni-staff.nullaosta-preview', [$iscrizioneStaff, 'raw' => 1]),
                'scaricaUrl' => route('iscrizioni-staff.nullaosta-preview', [$iscrizioneStaff, 'scarica' => 1]),
            ]);
        }

        $content     = Storage::disk('local')->get($path);
        $mimeType    = Storage::disk('local')->mimeType($path) ?: 'application/octet-stream';
        $filename    = basename($path);
        $disposition = $request->boolean('scarica') ? 'attachment' : 'inline';

        return response($content, 200, [
            'Content-Type'        => $mimeType,
            'Content-Disposition' => $disposition . '; filename="' . $filename . '"',
            'Cache-Control'       => 'private, no-store',
            'X-Frame-Options'     => 'SAMEORIGIN',
        ]);
    }
}
