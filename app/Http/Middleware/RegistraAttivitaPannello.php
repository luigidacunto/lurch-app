<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Ultima attività degli utenti del pannello (users.last_activity_at). Registrato come auth
 * middleware non persistente: contano caricamenti, refresh e navigazione tra le pagine, non le
 * richieste Livewire interne (es. il polling di Presenze live su una scheda lasciata aperta).
 */
class RegistraAttivitaPannello
{
    public function handle(Request $request, Closure $next)
    {
        $request->user()?->registraAttivita();

        return $next($request);
    }
}
