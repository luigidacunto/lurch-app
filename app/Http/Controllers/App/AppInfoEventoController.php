<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\Iscrizione;
use App\Models\IscrizioneStaff;
use Illuminate\Http\Request;

class AppInfoEventoController extends Controller
{
    public function show(Request $request, Evento $evento)
    {
        $socioId = $request->session()->get('app_socio_id');

        $isCorsista = Iscrizione::where('partecipante_id', $socioId)
            ->whereHas('corso', fn ($q) => $q->where('evento_id', $evento->id))
            ->exists();

        $isStaff = IscrizioneStaff::where('partecipante_id', $socioId)
            ->where('evento_id', $evento->id)
            ->exists();

        if (! $isCorsista && ! $isStaff) {
            abort(403);
        }

        $documenti = $evento->getMedia('documenti');

        $intestazioneSrc = null;
        $intestazione    = $evento->getFirstMedia('intestazione_app');
        if ($intestazione && file_exists($intestazione->getPath())) {
            $mime            = $intestazione->mime_type;
            $b64             = base64_encode(file_get_contents($intestazione->getPath()));
            $intestazioneSrc = "data:{$mime};base64,{$b64}";
        }

        return view('app.info-evento', compact('evento', 'documenti', 'intestazioneSrc', 'isCorsista', 'isStaff'));
    }
}
