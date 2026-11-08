<?php

namespace App\Http\Controllers;

use App\Models\AccessoEvento;
use App\Models\Evento;
use App\Models\OspiteEvento;
use App\Models\RispostaCheckin;
use App\Models\SocioEvento;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CheckinRicercaController extends Controller
{
    public function index(Evento $evento)
    {
        if (! BadgePortaleController::puoGestireCheckin(auth()->user(), $evento)) {
            abort(403);
        }

        return view('checkin.ricerca', compact('evento'));
    }

    public function suggerisci(Request $request, Evento $evento)
    {
        if (! BadgePortaleController::puoGestireCheckin(auth()->user(), $evento)) {
            abort(403);
        }

        $q = strtoupper(trim($request->input('q', '')));
        if (strlen($q) < 6) {
            return response()->json([]);
        }

        $risultati = collect();

        // Soci: una sola entry per partecipante via SocioEvento primary (id minore)
        $primaries = SocioEvento::where('evento_id', $evento->id)
            ->whereIn('id', function ($sub) use ($evento) {
                $sub->selectRaw('MIN(id)')
                    ->from('socio_evento')
                    ->where('evento_id', $evento->id)
                    ->groupBy('partecipante_id');
            })
            ->whereHas('partecipante', fn ($q2) => $q2->where('codice_fiscale', 'ILIKE', $q . '%'))
            ->with(['partecipante.comitatoLocale', 'partecipante.sottosedeAppartenenza'])
            ->limit(8)
            ->get();

        foreach ($primaries as $se) {
            $hasCorsista = $se->iscrizioni()->exists();
            $hasStaff    = $se->iscrizioneStaff()->exists();
            $tipo = ($hasCorsista && $hasStaff) ? 'Corsista + Staff' : ($hasStaff ? 'Staff' : 'Corsista');

            $risultati->push([
                'nome'     => $se->partecipante->nome_completo,
                'cf'       => $se->partecipante->codice_fiscale,
                'comitato' => $se->partecipante->comitato_display,
                'tipo'     => $tipo,
                'url'      => route('badge.portale', ['evento' => $evento->id, 'token' => $se->badge_token]),
            ]);
        }

        // Ospiti
        OspiteEvento::where('evento_id', $evento->id)
            ->where('cf', 'ILIKE', $q . '%')
            ->limit(3)->get()
            ->each(fn ($osp) => $risultati->push([
                'nome'     => $osp->nome_completo,
                'cf'       => $osp->cf,
                'comitato' => null,
                'tipo'     => 'Ospite',
                'url'      => route('checkin.ospite', ['evento' => $evento->id, 'ospite' => $osp->id]),
            ]));

        return response()->json($risultati->values());
    }

    public function cerca(Request $request, Evento $evento)
    {
        if (! BadgePortaleController::puoGestireCheckin(auth()->user(), $evento)) {
            abort(403);
        }

        $cf = strtoupper(trim($request->input('cf', '')));

        if (empty($cf)) {
            if ($request->wantsJson()) return response()->json(['found' => false, 'cf' => '']);
            return back()->with('error', 'Inserisci un codice fiscale.');
        }

        // Cerca SocioEvento primary per CF
        $primary = SocioEvento::where('evento_id', $evento->id)
            ->whereIn('id', function ($sub) use ($evento) {
                $sub->selectRaw('MIN(id)')
                    ->from('socio_evento')
                    ->where('evento_id', $evento->id)
                    ->groupBy('partecipante_id');
            })
            ->whereHas('partecipante', fn ($q) => $q->where('codice_fiscale', $cf))
            ->first();

        if ($primary) {
            $url = route('badge.portale', ['evento' => $evento->id, 'token' => $primary->badge_token]);
            if ($request->wantsJson()) return response()->json(['found' => true, 'url' => $url]);
            return redirect($url);
        }

        // Cerca tra gli ospiti già registrati
        $ospite = OspiteEvento::where('evento_id', $evento->id)->where('cf', $cf)->first();
        if ($ospite) {
            $url = route('checkin.ospite', ['evento' => $evento->id, 'ospite' => $ospite->id]);
            if ($request->wantsJson()) return response()->json(['found' => true, 'url' => $url]);
            return redirect($url);
        }

        // Non trovato
        if ($request->wantsJson()) return response()->json(['found' => false, 'cf' => $cf]);
        return back()->with('cf_non_trovato', $cf);
    }

    public function ospite(Evento $evento, OspiteEvento $ospite)
    {
        if (! BadgePortaleController::puoGestireCheckin(auth()->user(), $evento)) {
            abort(403);
        }

        abort_if($ospite->evento_id !== $evento->id, 404);

        $ospite->load('rispostaCheckin');

        $ultimoAccesso  = $ospite->ultimoAccesso;
        $tipoSuccessivo = ($ultimoAccesso?->tipo === 'entrata') ? 'uscita' : 'entrata';
        $primoCheckin   = $tipoSuccessivo === 'entrata' && $ospite->rispostaCheckin === null;
        $sezioni        = $primoCheckin ? $this->sezioniOspite($evento) : [];
        $datiForm       = $primoCheckin ? CheckinAccessoController::datiForm($evento) : [];

        $storiciOggi = $ospite->accessi()
            ->whereDate('timestamp_accesso', today())
            ->orderBy('timestamp_accesso')
            ->with('operatore')
            ->get();

        return view('checkin.accesso-ospite', compact(
            'evento', 'ospite', 'ultimoAccesso', 'tipoSuccessivo',
            'storiciOggi', 'primoCheckin', 'sezioni', 'datiForm'
        ));
    }

    public function salvaOspite(Request $request, Evento $evento)
    {
        if (! BadgePortaleController::puoGestireCheckin(auth()->user(), $evento)) {
            abort(403);
        }

        $validated = $request->validate([
            'nome'    => ['required', 'string', 'max:100'],
            'cognome' => ['required', 'string', 'max:100'],
            'cf'      => ['required', 'string', 'max:16'],
        ]);

        $cf = strtoupper($validated['cf']);

        // Se il CF è già registrato come ospite (es. precaricato), vai diretto alla scheda
        $ospiteEsistente = OspiteEvento::where('evento_id', $evento->id)->where('cf', $cf)->first();
        if ($ospiteEsistente) {
            return redirect()
                ->route('checkin.ospite', ['evento' => $evento->id, 'ospite' => $ospiteEsistente->id])
                ->with('success', 'Ospite già censito. Registra l\'accesso qui sotto.');
        }

        $ospite = OspiteEvento::create([
            'evento_id' => $evento->id,
            'nome'      => $validated['nome'],
            'cognome'   => $validated['cognome'],
            'cf'        => $cf,
        ]);

        return redirect()
            ->route('checkin.ospite', ['evento' => $evento->id, 'ospite' => $ospite->id])
            ->with('success', 'Ospite registrato. Compila i dati e registra l\'entrata qui sotto.');
    }

    public function accessoOspite(Request $request, Evento $evento, OspiteEvento $ospite)
    {
        if (! BadgePortaleController::puoGestireCheckin(auth()->user(), $evento)) {
            abort(403);
        }

        abort_if($ospite->evento_id !== $evento->id, 404);

        $ospite->load('rispostaCheckin');
        $ultimoAccesso  = $ospite->ultimoAccesso;
        $tipoSuccessivo = ($ultimoAccesso?->tipo === 'entrata') ? 'uscita' : 'entrata';
        $primoCheckin   = $tipoSuccessivo === 'entrata' && $ospite->rispostaCheckin === null;

        $sezioni = $primoCheckin ? $this->sezioniOspite($evento) : [];

        $rules = ['timestamp_accesso' => ['required', 'date']];
        if ($primoCheckin) {
            $rules['privacy_accettata'] = ['required', 'accepted'];
            foreach ($sezioni as $s) {
                $rules[$s['chiave']] = ['nullable', 'boolean'];
            }
        }

        $validated = $request->validate($rules);

        AccessoEvento::create([
            'iscrizioneable_type' => OspiteEvento::class,
            'iscrizioneable_id'   => $ospite->id,
            'tipo'                => $tipoSuccessivo,
            'timestamp_accesso'   => Carbon::parse($validated['timestamp_accesso']),
            'operatore_id'        => auth()->id(),
        ]);

        if ($primoCheckin) {
            $risposte = [
                'privacy_accettata'         => $request->boolean('privacy_accettata'),
                'consenso_salute_accettata' => $request->boolean('consenso_salute_accettata'),
                'foto_video_accettata'      => $request->boolean('foto_video_accettata'),
                'allergeni'                 => $request->input('allergeni', []),
                'diete'                     => $request->input('diete', []),
                'note_extra'                => $request->input('note_extra'),
            ];
            foreach ($sezioni as $s) {
                $risposte[$s['chiave']] = $request->boolean($s['chiave']);
            }

            RispostaCheckin::create([
                'iscrizioneable_type' => OspiteEvento::class,
                'iscrizioneable_id'   => $ospite->id,
                'risposte'            => $risposte,
                'checkin_at'          => now(),
                'operatore_id'        => auth()->id(),
            ]);
        }

        $tipoLabel = $tipoSuccessivo === 'entrata' ? 'Entrata' : 'Uscita';
        return redirect()
            ->route('checkin.ospite', ['evento' => $evento->id, 'ospite' => $ospite->id])
            ->with('success', "{$tipoLabel} registrata.");
    }

    /** Genera sezioni pasto/pernotto per ospiti dall'unione di tutti i corsi dell'evento */
    private function sezioniOspite(Evento $evento): array
    {
        $sezioni = [];
        $viste   = [];

        foreach ($evento->corsi as $corso) {
            foreach ($corso->form_sections ?? [] as $s) {
                if (($s['attivo'] ?? true) === false) continue;
                $chiave = $s['tipo'] . '_' . $s['data'];
                if (isset($viste[$chiave])) continue;
                $viste[$chiave] = true;
                $sezioni[] = ['tipo' => $s['tipo'], 'data' => $s['data'], 'chiave' => $chiave];
            }
        }

        return $sezioni;
    }
}
