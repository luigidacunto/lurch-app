<?php

namespace App\Http\Controllers;

use App\Exports\GraduatoriaCandidatiExport;
use App\Models\Graduatoria;
use Maatwebsite\Excel\Facades\Excel;

class ExportGraduatoriaCandidatiExcelController extends Controller
{
    public function __invoke(Graduatoria $graduatoria)
    {
        abort_unless(auth()->check(), 403);
        abort_unless($graduatoria->puoGestire(auth()->user()), 403);
        abort_if($graduatoria->haCandidatiNonCollegati(), 422, 'Ci sono candidati non ancora collegati a un socio: completare i collegamenti prima di esportare la lista.');

        $slug = str($graduatoria->codice_protocollo ?? $graduatoria->titolo)->slug();

        return Excel::download(new GraduatoriaCandidatiExport($graduatoria), "graduatoria_{$slug}.xlsx");
    }
}
