<?php

namespace App\Http\Controllers;

use App\Exports\ElencoPastiExport;
use App\Models\Evento;
use App\Services\GrigliaPastiBuilder;
use Maatwebsite\Excel\Facades\Excel;

class ElencoPastiExcelController extends Controller
{
    public function __invoke(Evento $evento, GrigliaPastiBuilder $builder)
    {
        abort_unless(auth()->check(), 403);
        // Stessa regola della pagina Gestione evento da cui parte il link.
        abort_unless(\App\Filament\Resources\EventoResource::canEdit($evento), 403);

        // Un foglio per pasto, solo i pasti con almeno una persona prevista
        $pasti = array_values(array_filter(
            $builder->build($evento)['righePresenze'],
            fn ($r) => ! empty($r['persone'])
        ));

        abort_if(empty($pasti), 404, 'Nessun pasto con persone previste.');

        return Excel::download(new ElencoPastiExport($pasti), 'pasti_elenco_' . str($evento->nome)->slug() . '.xlsx');
    }
}
