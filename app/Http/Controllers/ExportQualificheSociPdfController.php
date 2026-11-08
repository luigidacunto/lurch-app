<?php

namespace App\Http\Controllers;

use App\Models\CatalogoCorso;
use App\Models\ComitatoLocale;
use App\Models\ComitatoRegionale;
use App\Services\QualificheSociQueryBuilder;
use Barryvdh\DomPDF\Facade\Pdf;

class ExportQualificheSociPdfController extends Controller
{
    public function __invoke()
    {
        abort_unless(auth()->check(), 403);

        $user = auth()->user();

        $hasAccess = $user->isSuperadmin() || $user->isAdmin()
            || $user->isAdminLocale() || $user->isOperatore();
        abort_unless($hasAccess, 403);

        $qualificheRichieste = array_map('intval', (array) request('richieste', []));
        $qualificheEscluse   = array_map('intval', (array) request('escluse', []));
        $operatore           = in_array(request('op'), ['or', 'and']) ? request('op') : 'and';
        $regioneId           = request('regione') ? (int) request('regione') : null;
        $localeId            = request('locale')  ? (int) request('locale')  : null;
        $includiEstensioni   = (bool) request('estensioni', false);
        $includiDipendenti   = (bool) request('dipendenti', false);
        $filtroGiovani       = in_array(request('giovani'), ['attuale', 'ingresso']) ? request('giovani') : null;

        $builder = new QualificheSociQueryBuilder();
        $query = $builder->build(
            user: $user,
            qualificheRichieste: $qualificheRichieste,
            qualificheEscluse: $qualificheEscluse,
            operatoreRichieste: $operatore,
            filtroRegione: $regioneId,
            filtroLocale: $localeId,
            includiEstensioni: $includiEstensioni,
            includiDipendenti: $includiDipendenti,
            filtroGiovani: $filtroGiovani,
            with: ['sottosedeAppartenenza'],
        );

        $sortCol = request('sort', 'cognome');
        $sortDir = in_array(request('dir'), ['asc', 'desc']) ? request('dir') : 'asc';
        $this->applySort($query, $sortCol, $sortDir, $qualificheRichieste);
        $soci = $query->get();

        $regione = $regioneId ? ComitatoRegionale::find($regioneId) : null;
        $locale  = $localeId  ? ComitatoLocale::find($localeId)     : null;

        // Pre-carica dati qualifiche per il template
        $corsiRichiesti = ! empty($qualificheRichieste)
            ? CatalogoCorso::whereIn('id', $qualificheRichieste)->get()->keyBy('id')
            : collect();

        $corsiEsclusi = ! empty($qualificheEscluse)
            ? CatalogoCorso::whereIn('id', $qualificheEscluse)->get()
            : collect();

        $qualificheIds = [];
        foreach ($qualificheRichieste as $id) {
            $qualificheIds[$id] = CatalogoCorso::idsConEquivalenti($id);
        }

        $pdf = Pdf::loadView('pdf.qualifiche-soci', compact(
            'soci', 'qualificheRichieste', 'qualificheEscluse',
            'corsiRichiesti', 'corsiEsclusi', 'qualificheIds',
            'operatore', 'regione', 'locale', 'localeId',
            'includiEstensioni', 'includiDipendenti', 'filtroGiovani',
        ))->setPaper('a4', 'landscape');

        $nome        = $this->buildFilename($qualificheRichieste, $qualificheEscluse, $regione, $locale);
        $disposition = request()->boolean('download') ? 'attachment' : 'inline';

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => $disposition . '; filename="' . $nome . '"',
            'Cache-Control'       => 'private, no-store',
        ]);
    }

    private function applySort(\Illuminate\Database\Eloquent\Builder $query, string $col, string $dir, array $qualificheRichieste): void
    {
        match ($col) {
            'nome' => $query->orderBy('nome', $dir)->orderBy('cognome'),
            'comitato_display' => $query->orderByRaw(
                "(SELECT nome FROM comitati_locali WHERE comitati_locali.id = soci.comitato_locale_id LIMIT 1) {$dir} NULLS LAST"
            ),
            'scadenze_trovate' => $this->applySortScadenza($query, $dir, $qualificheRichieste),
            default => $query->orderBy('cognome', $dir)->orderBy('nome'),
        };
    }

    private function applySortScadenza(\Illuminate\Database\Eloquent\Builder $query, string $dir, array $qualificheRichieste): void
    {
        if (count($qualificheRichieste) !== 1) {
            $query->orderBy('cognome')->orderBy('nome');
            return;
        }
        $ids          = CatalogoCorso::idsConEquivalenti($qualificheRichieste[0]);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $query->orderByRaw(
            "(SELECT scadenza_calcolata FROM qualifiche_soci
              WHERE qualifiche_soci.socio_id = soci.id
                AND qualifiche_soci.catalogo_corso_id IN ({$placeholders})
              LIMIT 1) {$dir} NULLS LAST",
            $ids
        )->orderBy('cognome');
    }

    private function buildFilename(array $qualificheRichieste, array $qualificheEscluse, $regione, $locale): string
    {
        $parts = ['qualifiche'];

        if (! empty($qualificheRichieste)) {
            $parts[] = CatalogoCorso::whereIn('id', $qualificheRichieste)->pluck('codice')->join('_');
        }

        if (! empty($qualificheEscluse)) {
            $parts[] = 'esclusioni';
            $parts[] = CatalogoCorso::whereIn('id', $qualificheEscluse)->pluck('codice')->join('_');
        }

        if ($regione) {
            $parts[] = str($regione->nome)->slug('_');
        }
        if ($locale) {
            $parts[] = str($locale->nome)->slug('_');
        }

        $parts[] = now()->format('Ymd_Hi');

        return implode('_', $parts) . '.pdf';
    }
}
