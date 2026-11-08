<?php

namespace App\Http\Controllers;

use App\Models\GraduatoriaCandidato;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class PreviewAutocertificazioneController extends Controller
{
    /**
     * Senza `?raw=1` rende una pagina di anteprima autenticata e senza chrome del pannello
     * (documento-preview.blade.php), che renderizza il PDF con pdf.js su canvas — anteprima
     * indipendente dalle impostazioni PDF del browser. Con `?raw=1` (o `?scarica=1`) serve il
     * file vero: `inline` (sorgente per pdf.js / fallback iframe), `attachment` per il download.
     */
    public function __invoke(Request $request, GraduatoriaCandidato $candidato): Response|View
    {
        abort_unless(auth()->check(), 403);
        abort_unless($candidato->graduatoria->puoGestire(auth()->user()), 403);

        $path = $candidato->autocertificazione_path;
        abort_if(! $path, 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        if (! $request->boolean('raw') && ! $request->boolean('scarica')) {
            return view('documento-preview', [
                'titolo'     => 'Autocertificazione — ' . $candidato->cognome . ' ' . $candidato->nome,
                'rawUrl'     => route('graduatoria.candidato.autocertificazione', [$candidato, 'raw' => 1]),
                'scaricaUrl' => route('graduatoria.candidato.autocertificazione', [$candidato, 'scarica' => 1]),
            ]);
        }

        $content  = Storage::disk('local')->get($path);
        $mimeType = Storage::disk('local')->mimeType($path) ?: 'application/octet-stream';
        $filename = basename($path);
        $disposition = $request->boolean('scarica') ? 'attachment' : 'inline';

        return response($content, 200, [
            'Content-Type'        => $mimeType,
            'Content-Disposition' => $disposition . '; filename="' . $filename . '"',
            'Cache-Control'       => 'private, no-store',
            'X-Frame-Options'     => 'SAMEORIGIN',
        ]);
    }
}
