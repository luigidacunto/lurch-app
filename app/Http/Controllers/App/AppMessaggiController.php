<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\MessaggioBadge;
use App\Models\SocioEvento;
use Illuminate\Http\Request;

class AppMessaggiController extends Controller
{
    public function index(Request $request, Evento $evento)
    {
        $socioId = $request->session()->get('app_socio_id');

        $primary = $this->resolvePrimary($socioId, $evento);
        abort_if(! $primary, 403);

        $messaggi = MessaggioBadge::where('socio_evento_id', $primary->id)
            ->orderByRaw('letto_at IS NOT NULL, created_at DESC')
            ->get();

        return view('app.messaggi', compact('evento', 'messaggi'));
    }

    public function show(Request $request, Evento $evento, MessaggioBadge $messaggio)
    {
        $socioId = $request->session()->get('app_socio_id');

        $primary = $this->resolvePrimary($socioId, $evento);
        abort_if(! $primary, 403);
        abort_if($messaggio->socio_evento_id !== $primary->id, 403);

        $messaggio->segnaLetto();

        return view('app.messaggio', compact('evento', 'messaggio'));
    }

    private function resolvePrimary(int $socioId, Evento $evento): ?SocioEvento
    {
        return SocioEvento::where('partecipante_id', $socioId)
            ->where('evento_id', $evento->id)
            ->orderBy('id')
            ->first();
    }
}
