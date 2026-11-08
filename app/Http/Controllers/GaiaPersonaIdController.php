<?php

namespace App\Http\Controllers;

use App\Filament\Resources\SocioResource;
use App\Models\Graduatoria;
use App\Models\Socio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint chiamati dallo userscript "LURCH - GAIA Bridge" (resources/userscripts/), che gira
 * sulla pagina Candidati e usa GM_xmlhttpRequest per interrogare GAIA (bypassando il CORS
 * che blocca una fetch diretta da qui — verificato: GAIA risponde Access-Control-Allow-Origin
 * '*', incompatibile con richieste con credenziali). Le chiamate a questi due endpoint restano
 * invece same-origin: usano la sessione/permessi dell'utente Lurch già loggato, nessuna
 * autenticazione separata.
 */
class GaiaPersonaIdController extends Controller
{
    public function daCercare(Graduatoria $graduatoria): JsonResponse
    {
        // Soglia di ruolo User::puoUsareGaiaBridge() + scoping territoriale puoGestire().
        // Controllo a monte anche qui (non
        // solo lato Filament) perché lo userscript, una volta
        // installato, potrebbe girare per chiunque abbia il file — "passa parola" a un utente
        // sotto la soglia non deve bastare per usarlo.
        abort_unless(auth()->user()?->puoUsareGaiaBridge() && $graduatoria->puoGestire(auth()->user()), 403);

        return response()->json($graduatoria->candidatiDaCercareSuGaia());
    }

    /**
     * Scrive soci.gaia_persona_id in blocco. Rotta inerente ai soci (non a una graduatoria
     * specifica): l'autorizzazione è per-socio via SocioResource::canEditQualifiche() (il
     * gaia_persona_id è il prerequisito per leggere il curriculum, quindi segue lo stesso
     * perimetro delle qualifiche, estensione inclusa) — così un admin_locale/regionale
     * scoping-limitato non può scrivere su soci fuori dal proprio ambito solo perché passa
     * un socio_id arbitrario nel body.
     */
    public function salva(Request $request): JsonResponse
    {
        abort_unless(auth()->user()?->puoUsareGaiaBridge(), 403);

        $data = $request->validate([
            'risultati'                    => ['required', 'array'],
            'risultati.*.socio_id'         => ['required', 'integer'],
            'risultati.*.gaia_persona_id'  => ['required', 'string', 'max:255'],
        ]);

        $aggiornati = 0;
        foreach ($data['risultati'] as $riga) {
            $socio = Socio::find($riga['socio_id']);
            if (! $socio || ! SocioResource::canEditQualifiche($socio)) {
                continue;
            }
            $socio->update(['gaia_persona_id' => $riga['gaia_persona_id']]);
            $aggiornati++;
        }

        return response()->json(['aggiornati' => $aggiornati]);
    }
}
