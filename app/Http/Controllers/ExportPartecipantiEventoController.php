<?php

namespace App\Http\Controllers;

use App\Exports\PartecipantiEventoExport;
use App\Models\Evento;
use App\Models\Socio;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ExportPartecipantiEventoController extends Controller
{
    public function __invoke(Evento $evento, Request $request)
    {
        abort_unless(auth()->check(), 403);

        $user      = auth()->user();
        $hasAccess = $user->isSuperadmin()
            || ($user->isAdmin()       && $evento->comitato_regionale_id === $user->comitato_regionale_id)
            || ($user->isAdminLocale() && $evento->comitato_locale_id    === $user->comitato_locale_id)
            || ($user->isOperatore()   && $evento->operatori->contains($user->id));

        abort_unless($hasAccess, 403);

        $evento->load([
            'corsi.iscrizioni.partecipante.comitatoRegionale',
            'corsi.iscrizioni.partecipante.comitatoLocale',
            'corsi.iscrizioni.partecipante.sottosedeAppartenenza',
            'staff.partecipante.comitatoRegionale',
            'staff.partecipante.comitatoLocale',
            'staff.partecipante.sottosedeAppartenenza',
            'ospiti',
        ]);

        $search = mb_strtolower(trim($request->input('search', '')));

        $mappa = [];

        foreach ($evento->corsi as $corso) {
            foreach ($corso->iscrizioni as $iscr) {
                $pid = $iscr->partecipante_id;
                $p   = $iscr->partecipante;
                if (! $p) continue;

                if (! isset($mappa[$pid])) {
                    $mappa[$pid] = $this->baseEntry($p);
                }
                $mappa[$pid]['tipo_iscritto'] = true;

                $protocollo = $corso->numero_protocollo;
                if ($protocollo && ! in_array($protocollo, $mappa[$pid]['corsi_list'], true)) {
                    $mappa[$pid]['corsi_list'][] = $protocollo;
                }
            }
        }

        foreach ($evento->staff as $iscr) {
            $pid = $iscr->partecipante_id;
            $p   = $iscr->partecipante;
            if (! $p) continue;

            if (! isset($mappa[$pid])) {
                $mappa[$pid] = $this->baseEntry($p);
            }
            $mappa[$pid]['tipo_staff'] = true;
        }

        foreach ($evento->ospiti as $ospite) {
            $key = 'ospite_' . $ospite->id;
            $mappa[$key] = [
                'cognome'            => $ospite->cognome ?? '',
                'nome'               => $ospite->nome ?? '',
                'codice_fiscale'     => $ospite->cf ?? '',
                'email'              => '',
                'telefono'           => '',
                'comitato_regionale' => '',
                'comitato_display'   => '',
                'tipo_iscritto'      => false,
                'tipo_staff'         => false,
                'tipo_ospite'        => true,
                'corsi_list'         => [],
                '_search'            => mb_strtolower(($ospite->cognome ?? '') . ' ' . ($ospite->nome ?? '') . ' ' . ($ospite->cf ?? '')),
            ];
        }

        $tipoLabel = [
            'iscritto' => 'Iscritto',
            'staff'    => 'Staff',
            'entrambi' => 'Iscritto + Staff',
            'ospite'   => 'Ospite',
        ];

        $rows = collect($mappa)
            ->when($search !== '', fn ($c) => $c->filter(
                fn ($e) => str_contains($e['_search'], $search)
            ))
            ->sortBy(fn ($e) => $e['cognome'] . ' ' . $e['nome'])
            ->map(function ($entry) use ($tipoLabel) {
                $tipo = match (true) {
                    $entry['tipo_ospite'] ?? false                  => 'ospite',
                    $entry['tipo_iscritto'] && $entry['tipo_staff'] => 'entrambi',
                    $entry['tipo_staff']                            => 'staff',
                    default                                         => 'iscritto',
                };
                return [
                    $entry['cognome'],
                    $entry['nome'],
                    $entry['codice_fiscale'],
                    $entry['email'],
                    $entry['telefono'],
                    $entry['comitato_regionale'],
                    $entry['comitato_display'],
                    implode(', ', $entry['corsi_list']),
                    $tipoLabel[$tipo],
                ];
            })
            ->values()
            ->toArray();

        $filename = 'partecipanti_' . Str::slug($evento->nome) . '_' . now()->format('Ymd') . '.xlsx';

        return Excel::download(new PartecipantiEventoExport($rows), $filename);
    }

    private function baseEntry(Socio $p): array
    {
        return [
            'cognome'            => $p->cognome ?? '',
            'nome'               => $p->nome ?? '',
            'codice_fiscale'     => $p->codice_fiscale ?? '',
            'email'              => $p->email ?? '',
            'telefono'           => trim(($p->prefisso_telefono ?? '') . ' ' . ($p->telefono ?? '')),
            'comitato_regionale' => $p->comitatoRegionale?->nome ?? '',
            'comitato_display'   => $p->comitato_display,
            'tipo_iscritto'      => false,
            'tipo_staff'         => false,
            'corsi_list'         => [],
            '_search'            => mb_strtolower(
                ($p->cognome ?? '') . ' ' . ($p->nome ?? '') . ' ' .
                ($p->codice_fiscale ?? '') . ' ' .
                ($p->comitatoRegionale?->nome ?? '') . ' ' .
                ($p->comitatoLocale?->nome ?? '') . ' ' .
                ($p->sottosedeAppartenenza?->nome ?? '')
            ),
        ];
    }
}
