<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Evento;
use App\Models\Iscrizione;
use App\Models\IscrizioneStaff;
use App\Models\MessaggioBadge;
use App\Models\SocioEvento;
use Illuminate\Http\Request;

class AppEventoController extends Controller
{
    public function show(Request $request, Evento $evento)
    {
        $socioId = $request->session()->get('app_socio_id');

        $iscrizioni = Iscrizione::with(['corso', 'rispostaPreregistrazione'])
            ->where('partecipante_id', $socioId)
            ->whereHas('corso', fn ($q) => $q->where('evento_id', $evento->id))
            ->get();

        $iscrizioneStaff = IscrizioneStaff::with(['rispostaPreregistrazione'])
            ->where('partecipante_id', $socioId)
            ->where('evento_id', $evento->id)
            ->first();

        if ($iscrizioni->isEmpty() && ! $iscrizioneStaff) {
            abort(403);
        }

        $intestazioneSrc = null;
        $intestazione    = $evento->getFirstMedia('intestazione_app');
        if ($intestazione && file_exists($intestazione->getPath())) {
            $mime            = $intestazione->mime_type;
            $b64             = base64_encode(file_get_contents($intestazione->getPath()));
            $intestazioneSrc = "data:{$mime};base64,{$b64}";
        }

        $isCorsista = $iscrizioni->isNotEmpty();
        $isStaff    = $iscrizioneStaff !== null;

        $primary = SocioEvento::where('partecipante_id', $socioId)
            ->where('evento_id', $evento->id)
            ->orderBy('id')
            ->first();

        $messaggiNonLetti = 0;
        $messaggiTotali   = 0;
        if ($primary) {
            $messaggiNonLetti = MessaggioBadge::where('socio_evento_id', $primary->id)->whereNull('letto_at')->count();
            $messaggiTotali   = MessaggioBadge::where('socio_evento_id', $primary->id)->count();
        }

        return view('app.evento', compact(
            'evento', 'intestazioneSrc',
            'isCorsista', 'isStaff',
            'iscrizioni', 'iscrizioneStaff',
            'messaggiNonLetti', 'messaggiTotali'
        ));
    }
}
