<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\MessaggioBadge;
use Illuminate\Http\Request;

class MessaggioBadgeController extends Controller
{
    public function store(Request $request, Evento $evento, string $token)
    {
        // QR code disabilitato 24 ore dopo la chiusura dell'evento
        if ($evento->finestraOperativaScaduta()) {
            return view('badge.evento-concluso', compact('evento'));
        }

        $request->validate([
            'corpo' => ['required', 'string', 'max:20000'],
        ]);

        $primary = BadgePortaleController::resolveToken($evento, $token);

        MessaggioBadge::create([
            'socio_evento_id' => $primary->id,
            'corpo'           => $request->input('corpo'),
        ]);

        $socio = $primary->partecipante;

        return view('badge.messaggio-inviato', compact('socio', 'evento'));
    }
}
