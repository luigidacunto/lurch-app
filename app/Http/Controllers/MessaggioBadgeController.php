<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\MessaggioBadge;
use App\Services\HtmlMessaggioBadge;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

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

        // Endpoint pubblico: l'HTML può arrivare da chiunque, non solo dall'editor.
        $corpo = HtmlMessaggioBadge::pulisci($request->input('corpo'));
        if (HtmlMessaggioBadge::vuoto($corpo)) {
            throw ValidationException::withMessages(['corpo' => 'Il messaggio è vuoto.']);
        }

        $primary = BadgePortaleController::resolveToken($evento, $token);

        MessaggioBadge::create([
            'socio_evento_id' => $primary->id,
            'corpo'           => $corpo,
        ]);

        $socio = $primary->partecipante;

        return view('badge.messaggio-inviato', compact('socio', 'evento'));
    }
}
