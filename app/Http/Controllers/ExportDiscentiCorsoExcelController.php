<?php

namespace App\Http\Controllers;

use App\Exports\DiscentiCorsoExport;
use App\Models\Corso;
use Maatwebsite\Excel\Facades\Excel;

class ExportDiscentiCorsoExcelController extends Controller
{
    public function __invoke(Corso $corso)
    {
        abort_unless(auth()->check(), 403);

        $user   = auth()->user();
        $evento = $corso->evento;

        $hasAccess = $user->isSuperadmin()
            || ($user->isAdmin() && $evento?->comitato_regionale_id === $user->comitato_regionale_id)
            || ($user->isAdminLocale() && $evento?->comitato_locale_id === $user->comitato_locale_id)
            || ($user->isOperatore() && $evento?->operatori->contains($user->id));

        abort_unless($hasAccess, 403);

        $rows = $corso->iscrizioniAttive()
            ->with('partecipante.comitatoLocale', 'partecipante.comitatoRegionale', 'partecipante.comitatoLocaleDipendente', 'partecipante.comitatoRegionaleDipendente')
            ->get()
            ->pluck('partecipante')
            ->filter()
            ->sortBy(fn ($s) => $s->cognome . ' ' . $s->nome)
            ->map(fn ($s) => [
                $s->cognome,
                $s->nome,
                $s->codice_fiscale,
                $s->data_nascita?->format('d/m/Y') ?? '',
                $s->data_nascita ? (int) $s->data_nascita->diffInYears(now()) : '',
                $s->comitatoPrincipaleNome() ?? '',
                $s->tipoSocio(),
                $s->telefono ? trim(($s->prefisso_telefono ?? '+39') . ' ' . $s->telefono) : '',
                $s->email ?? '',
            ])
            ->values()
            ->all();

        $slug = str($corso->numero_protocollo ?? $corso->nome)->slug();

        return Excel::download(new DiscentiCorsoExport($rows), 'discenti_' . $slug . '.xlsx');
    }
}
