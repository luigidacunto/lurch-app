<?php

namespace App\Http\Middleware;

use App\Models\Socio;
use Closure;
use Illuminate\Http\Request;

class AppSocioAuth
{
    public function handle(Request $request, Closure $next)
    {
        $socioId = $request->session()->get('app_socio_id');

        // Sessione scaduta ma cookie remember presente → ripristina sessione
        if (! $socioId) {
            $remembered = $request->cookie('app_remember');
            if ($remembered && Socio::find((int) $remembered)) {
                $socioId = (int) $remembered;
                $request->session()->put('app_socio_id', $socioId);
            }
        }

        if (! $socioId || ! Socio::find($socioId)) {
            $request->session()->forget('app_socio_id');
            return redirect()->route('app.login');
        }

        return $next($request);
    }
}
