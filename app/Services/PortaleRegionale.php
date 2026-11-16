<?php

namespace App\Services;

use App\Models\ComitatoRegionale;

/**
 * Link pubblici con il dominio del portale della regione a cui appartiene il dato
 * (evento, corso, graduatoria), mai con quello da cui arriva la richiesta.
 * Regione senza `url_portale` (o nessuna regione): indirizzo generale del portale, APP_URL.
 */
final class PortaleRegionale
{
    public static function route(?int $comitatoRegionaleId, string $name, mixed $parameters = []): string
    {
        return self::base($comitatoRegionaleId) . route($name, $parameters, false);
    }

    public static function base(?int $comitatoRegionaleId): string
    {
        $generale = rtrim((string) config('app.url'), '/');

        if (! $comitatoRegionaleId) {
            return $generale;
        }

        // ponytail: memo per richiesta/test; un worker di coda già avviato vede un url_portale
        // cambiato solo dopo il riavvio (lo fa il deploy).
        return once(fn () => ComitatoRegionale::whereKey($comitatoRegionaleId)->value('url_portale')) ?: $generale;
    }

    /**
     * Regione del dominio da cui arriva la richiesta, per le pagine non legate a un dato
     * (home, login, /app, /privacy generale). Null sull'indirizzo generale o su un dominio sconosciuto.
     */
    public static function daRichiesta(): ?ComitatoRegionale
    {
        return self::perHost(request()->getHttpHost());
    }

    public static function perHost(string $host): ?ComitatoRegionale
    {
        $host = strtolower($host);

        // url_portale è salvato in minuscolo e senza "/" finale (vedi ComitatoRegionaleResource).
        return once(fn () => ComitatoRegionale::whereIn('url_portale', ["https://{$host}", "http://{$host}"])->first());
    }
}
