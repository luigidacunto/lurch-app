<?php

namespace App\Http\Controllers;

use App\Exports\SociExport;
use App\Filament\Resources\SocioResource;
use App\Models\ComitatoLocale;
use App\Models\ComitatoRegionale;
use App\Services\SociQueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;

/** Stesso pattern sincrono di ExportQualificheSociExcelController. Filtri delegati a
 *  SociQueryBuilder (stessa logica usata da ListSoci), niente duplicazione qui. */
class ExportSociExcelController extends Controller
{
    public function __invoke()
    {
        abort_unless(auth()->check(), 403);
        abort_unless(SocioResource::canViewAny(), 403);

        $regioneId         = request('regione') ? (int) request('regione') : null;
        $localeId          = request('locale')  ? (int) request('locale')  : null;
        $ruolo             = request('ruolo') ?: null;
        $includiEstensioni = (bool) request('estensioni', false);
        $includiDipendenti = (bool) request('dipendenti', false);
        $filtroGiovani     = in_array(request('giovani'), ['attuale', 'ingresso']) ? request('giovani') : null;
        $statoAssociativo  = in_array(request('stato'), ['dimessi', 'tutti']) ? request('stato') : null;
        $cestinati         = in_array(request('cestinati'), ['con', 'solo']) ? request('cestinati') : null;

        $query = (new SociQueryBuilder())->build(
            user: auth()->user(),
            filtroRegione: $regioneId,
            filtroLocale: $localeId,
            filtroRuolo: $ruolo,
            includiEstensioni: $includiEstensioni,
            includiDipendenti: $includiDipendenti,
            filtroGiovani: $filtroGiovani,
            filtroStatoAssociativo: $statoAssociativo,
            filtroCestinati: $cestinati,
        );

        $sortCol = request('sort', 'cognome');
        $sortDir = in_array(request('dir'), ['asc', 'desc']) ? request('dir') : 'asc';
        $this->applySort($query, $sortCol, $sortDir);

        $soci = $query->with(['comitatoLocaleEstensione', 'comitatoLocaleDipendente', 'comitatoRegionaleDipendente'])->get();

        $regione = $regioneId ? ComitatoRegionale::find($regioneId) : null;
        $locale  = $localeId  ? ComitatoLocale::find($localeId)     : null;

        $export = new SociExport(
            soci: $soci,
            regione: $regione,
            locale: $locale,
            ruolo: $ruolo,
            includiEstensioni: $includiEstensioni,
            includiDipendenti: $includiDipendenti,
            statoAssociativo: $statoAssociativo,
            cestinati: $cestinati,
            filtroGiovani: $filtroGiovani,
        );

        $filename = $this->buildFilename($regione, $locale);

        return Excel::download($export, $filename);
    }

    private function applySort(Builder $query, string $col, string $dir): void
    {
        match ($col) {
            'nome' => $query->orderBy('nome', $dir)->orderBy('cognome'),
            'comitato_display' => $query->orderByRaw(
                "(SELECT nome FROM comitati_locali WHERE comitati_locali.id = soci.comitato_locale_id LIMIT 1) {$dir} NULLS LAST"
            ),
            'data_nascita', 'eta_attuale' => $query->orderBy('data_nascita', $col === 'eta_attuale' ? ($dir === 'asc' ? 'desc' : 'asc') : $dir),
            default => $query->orderBy($col, $dir)->orderBy('cognome'),
        };
    }

    private function buildFilename($regione, $locale): string
    {
        $parts = ['soci'];
        if ($regione) $parts[] = str($regione->nome)->slug('_');
        if ($locale)  $parts[] = str($locale->nome)->slug('_');
        $parts[] = now()->format('Ymd_Hi');
        return implode('_', $parts) . '.xlsx';
    }
}
