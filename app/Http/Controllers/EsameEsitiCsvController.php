<?php

namespace App\Http\Controllers;

use App\Exports\EsitiEsameExport;
use App\Models\Esame;
use Maatwebsite\Excel\Excel as TipoExcel;
use Maatwebsite\Excel\Facades\Excel;

/** CSV degli esiti dell'esame: stessa regola di lettura del PDF; provvisorio se non chiuso. */
class EsameEsitiCsvController extends Controller
{
    public function __invoke(Esame $esame)
    {
        abort_unless($esame->puoVedereRiepilogoItinere(auth()->user()), 403);

        $nome = 'esiti_'.str($esame->titolo)->slug().($esame->isChiuso() ? '' : '_provvisorio').'.csv';

        return Excel::download(new EsitiEsameExport($esame), $nome, TipoExcel::CSV, ['Cache-Control' => 'private, no-store']);
    }
}
