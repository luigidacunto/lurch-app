<?php

namespace App\Http\Controllers;

use App\Models\Graduatoria;

class PreviewGraduatoriaCandidatiHtmlController extends Controller
{
    public function __invoke(Graduatoria $graduatoria)
    {
        abort_unless(auth()->check(), 403);
        abort_unless($graduatoria->puoGestire(auth()->user()), 403);

        $candidati = $graduatoria->candidatiClassificati();
        $esclusi = $graduatoria->candidatiEsclusiConMotivo();

        return view('graduatorie.candidati-preview-html', compact('graduatoria', 'candidati', 'esclusi'));
    }
}
