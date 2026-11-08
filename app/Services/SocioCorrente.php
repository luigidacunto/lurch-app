<?php

namespace App\Services;

use App\Models\User;

/**
 * Punto unico "quale socio sta operando" (esami). Ogni permesso del modulo esami parte da qui:
 * se un giorno gli utenti del pannello diventano soci, cambia solo questa classe.
 *
 * ⚠️ La sessione è condivisa tra pannello e PWA: nello stesso browser si può essere loggati nel pannello
 * come un utente e nella PWA come un altro socio. Per questo decide il contesto (rotta app.*), non
 * "la prima chiave che trovo".
 */
class SocioCorrente
{
    public static function id(): ?int
    {
        if (request()->routeIs('app.*')) {
            // Il middleware app.auth ha già validato il socio e ripristinato la sessione dal cookie.
            $id = session('app_socio_id');

            return $id ? (int) $id : null;
        }

        return static::perUtente(auth()->user());
    }

    /** Socio collegato a un utente del pannello (users.socio_id). */
    public static function perUtente(?User $user): ?int
    {
        return $user?->socio_id;
    }
}
