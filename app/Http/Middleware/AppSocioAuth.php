<?php

namespace App\Http\Middleware;

use App\Models\Socio;
use Closure;
use Illuminate\Http\Request;

class AppSocioAuth
{
    public function handle(Request $request, Closure $next)
    {
        $socioId  = $request->session()->get('app_socio_id');
        $emessaIl = $request->session()->get('app_login_at');

        // Sessione scaduta ma cookie remember presente → ripristina sessione.
        // Formato "id.timestamp"; i cookie emessi prima del reset sessioni contengono solo "id".
        if (! $socioId) {
            $remembered = (string) $request->cookie('app_remember');
            if (preg_match('/^(\d+)(?:\.(\d+))?$/', $remembered, $m)) {
                $socioId  = (int) $m[1];
                $emessaIl = isset($m[2]) ? (int) $m[2] : null;
            }
        }

        $socio = $socioId ? Socio::find($socioId) : null;

        if (! $socio || ! $socio->sessioneValida($emessaIl)) {
            $request->session()->forget(['app_socio_id', 'app_login_at']);
            return redirect()->route('app.login')
                ->withCookie(cookie()->forget('app_remember'));
        }

        $request->session()->put('app_socio_id', $socio->id);
        $request->session()->put('app_login_at', $emessaIl);
        $socio->registraAttivita();

        return $next($request);
    }
}
