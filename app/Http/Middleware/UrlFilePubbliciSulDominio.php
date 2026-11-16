<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Più domini sullo stesso sito (dominio generale + domini delle regioni): l'indirizzo dei file del
 * disco «public» usa il dominio della richiesta invece di APP_URL. Altrimenti il browser blocca
 * (CORS) le letture fatte dalla pagina verso un altro dominio, come l'anteprima dei campi di
 * caricamento di Filament, che resta su «In attesa della dimensione».
 */
class UrlFilePubbliciSulDominio
{
    public function handle(Request $request, Closure $next): Response
    {
        config(['filesystems.disks.public.url' => $request->getSchemeAndHttpHost() . '/storage']);
        Storage::forgetDisk('public');

        return $next($request);
    }
}
