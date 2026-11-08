<?php

namespace App\Http\Controllers;

use App\Exports\PernottatiCheckinExport;
use App\Models\Evento;
use App\Models\OspiteEvento;
use App\Models\RispostaCheckin;
use App\Models\SocioEvento;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportPernottatiCheckinController extends Controller
{
    public function __invoke(Evento $evento): BinaryFileResponse
    {
        abort_unless(auth()->check(), 403);

        $righe    = self::buildRighe($evento);
        $filename = 'pernottanti_checkin_' . Str::slug($evento->nome) . '_' . now()->format('Ymd') . '.xlsx';

        return Excel::download(new PernottatiCheckinExport($righe), $filename);
    }

    public static function buildRighe(Evento $evento): array
    {
        $allSeIds  = SocioEvento::where('evento_id', $evento->id)->pluck('id');
        $allOspIds = OspiteEvento::where('evento_id', $evento->id)->pluck('id');

        $lista = [];

        RispostaCheckin::where('iscrizioneable_type', SocioEvento::class)
            ->whereIn('iscrizioneable_id', $allSeIds)
            ->with('iscrizioneable.partecipante:id,nome,cognome,codice_fiscale,data_nascita,luogo_nascita')
            ->get()
            ->each(function ($r) use (&$lista) {
                $count = collect($r->risposte ?? [])
                    ->filter(fn ($v, $k) => str_starts_with($k, 'pernotto_') && $v === true)
                    ->count();
                if ($count === 0) return;
                $p = $r->iscrizioneable?->partecipante;
                $lista[] = [
                    'cognome'       => $p?->cognome ?? '',
                    'nome'          => $p?->nome ?? '',
                    'cf'            => $p?->codice_fiscale ?? '',
                    'data_nascita'  => $p?->data_nascita?->format('d/m/Y') ?? '',
                    'luogo_nascita' => $p?->luogo_nascita ?? '',
                    'pernotti'      => $count,
                ];
            });

        RispostaCheckin::where('iscrizioneable_type', OspiteEvento::class)
            ->whereIn('iscrizioneable_id', $allOspIds)
            ->with('iscrizioneable')
            ->get()
            ->each(function ($r) use (&$lista) {
                $count = collect($r->risposte ?? [])
                    ->filter(fn ($v, $k) => str_starts_with($k, 'pernotto_') && $v === true)
                    ->count();
                if ($count === 0) return;
                $lista[] = [
                    'cognome'       => $r->iscrizioneable?->cognome ?? '',
                    'nome'          => $r->iscrizioneable?->nome ?? '',
                    'cf'            => $r->iscrizioneable?->cf ?? '',
                    'data_nascita'  => '',
                    'luogo_nascita' => '',
                    'pernotti'      => $count,
                ];
            });

        usort($lista, fn ($a, $b) => strcmp($a['cognome'] . $a['nome'], $b['cognome'] . $b['nome']));

        return $lista;
    }
}
