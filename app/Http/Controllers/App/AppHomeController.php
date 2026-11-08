<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Esame;
use App\Models\Iscrizione;
use App\Models\IscrizioneStaff;
use App\Models\Socio;
use Illuminate\Http\Request;

class AppHomeController extends Controller
{
    public function index(Request $request)
    {
        $socioId = $request->session()->get('app_socio_id');
        $socio = Socio::findOrFail($socioId);

        $eventi = [];

        Iscrizione::where('partecipante_id', $socio->id)
            ->with('corso.evento')
            ->get()
            ->filter(fn ($i) => $i->corso && $i->corso->evento)
            ->each(function ($i) use (&$eventi) {
                $id = $i->corso->evento->id;
                if (! isset($eventi[$id])) {
                    $eventi[$id] = [
                        'evento'      => $i->corso->evento,
                        'is_corsista' => true,
                        'is_staff'    => false,
                        'data_inizio' => $i->corso->evento->data_inizio,
                    ];
                } else {
                    $eventi[$id]['is_corsista'] = true;
                }
            });

        IscrizioneStaff::where('partecipante_id', $socio->id)
            ->with('evento')
            ->get()
            ->filter(fn ($i) => $i->evento)
            ->each(function ($i) use (&$eventi) {
                $id = $i->evento->id;
                if (! isset($eventi[$id])) {
                    $eventi[$id] = [
                        'evento'      => $i->evento,
                        'is_corsista' => false,
                        'is_staff'    => true,
                        'data_inizio' => $i->evento->data_inizio,
                    ];
                } else {
                    $eventi[$id]['is_staff'] = true;
                }
            });

        $eventi = collect($eventi)->sortByDesc('data_inizio')->values();

        // Link "I tuoi esami" solo a chi ha un ruolo in un esame attivo.
        $haEsami = Esame::query()->visibiliAlSocio($socio->id)->exists();

        return view('app.home', compact('socio', 'eventi', 'haEsami'));
    }
}
