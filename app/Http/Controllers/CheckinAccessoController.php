<?php

namespace App\Http\Controllers;

use App\Models\AccessoEvento;
use App\Models\ComitatoLocale;
use App\Models\ComitatoRegionale;
use App\Models\Evento;
use App\Models\Iscrizione;
use App\Models\IscrizioneStaff;
use App\Models\OpzioneAlimentare;
use App\Models\RispostaCheckin;
use App\Models\SocioEvento;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CheckinAccessoController extends Controller
{
    public function store(Request $request, Evento $evento, string $token): RedirectResponse
    {
        $user = auth()->user();

        if (! BadgePortaleController::puoGestireCheckin($user, $evento)) {
            abort(403);
        }

        $primary = BadgePortaleController::resolveToken($evento, $token);

        // Finestra temporale: solo i giorni operativi (giorni reali + giorno prima/dopo di ogni blocco)
        if (! $evento->inGiornoOperativo()) {
            return back()->with('error', 'Il check-in non è disponibile al di fuori dei giorni dell\'evento.');
        }

        // Tipo automatico basato sull'ultimo accesso del SocioEvento
        $ultimoAccesso  = $primary->ultimoAccesso;
        $tipoSuccessivo = ($ultimoAccesso?->tipo === 'entrata') ? 'uscita' : 'entrata';

        // Sezioni pasti validate e salvate nel primo checkin
        $sezioni = $tipoSuccessivo === 'entrata' && $primary->rispostaCheckin === null
            ? $this->sezioniPerCheckin($primary, $evento)
            : [];

        $rules = [
            'timestamp_accesso' => [
                'required',
                'date',
                function ($attribute, $value, $fail) {
                    if (\Carbon\Carbon::parse($value)->isAfter(now()->addMinutes(30))) {
                        $fail('La data e ora non può essere più di 30 minuti nel futuro.');
                    }
                },
            ],
            'note' => ['nullable', 'string', 'max:500'],
        ];
        foreach ($sezioni as $s) {
            $rules[$s['chiave']] = ['nullable', 'boolean'];
        }

        $validated = $request->validate($rules);

        // Registra accesso morfato su SocioEvento
        AccessoEvento::create([
            'iscrizioneable_type' => SocioEvento::class,
            'iscrizioneable_id'   => $primary->id,
            'tipo'                => $tipoSuccessivo,
            'timestamp_accesso'   => Carbon::parse($validated['timestamp_accesso']),
            'operatore_id'        => $user->id,
            'note'                => $validated['note'] ?? null,
        ]);

        // Al primo checkin: salva UN'unica RispostaCheckin su SocioEvento
        if ($tipoSuccessivo === 'entrata' && $primary->rispostaCheckin === null) {
            $risposte = $this->estraiRisposteForm($request, $primary, $evento, $sezioni);

            RispostaCheckin::create([
                'iscrizioneable_type' => SocioEvento::class,
                'iscrizioneable_id'   => $primary->id,
                'risposte'            => $risposte,
                'checkin_at'          => now(),
                'operatore_id'        => $user->id,
            ]);

            // Aggiorna stato su TUTTE le iscrizioni corsista + staff del partecipante
            Iscrizione::where('partecipante_id', $primary->partecipante_id)
                ->whereHas('corso', fn ($q) => $q->where('evento_id', $evento->id))
                ->whereIn('stato', ['pending', 'form_completato'])
                ->update(['stato' => 'checkin_fatto']);

            IscrizioneStaff::where('partecipante_id', $primary->partecipante_id)
                ->where('evento_id', $evento->id)
                ->whereIn('stato', ['pending', 'form_completato'])
                ->update(['stato' => 'checkin_fatto']);
        }

        $tipoLabel = $tipoSuccessivo === 'entrata' ? 'Entrata' : 'Uscita';
        return redirect()
            ->route('badge.portale', ['evento' => $evento->id, 'token' => $token])
            ->with('success', "{$tipoLabel} registrata correttamente.");
    }

    /** Estrae e valida le risposte del form di primo checkin */
    private function estraiRisposteForm(Request $request, SocioEvento $primary, Evento $evento, array $sezioni): array
    {
        $isStaff = IscrizioneStaff::where('partecipante_id', $primary->partecipante_id)
            ->where('evento_id', $evento->id)
            ->exists();

        $risposte = [
            'comitato_regionale_id'     => $request->input('comitato_regionale_id'),
            'comitato_locale_id'        => $request->input('comitato_locale_id'),
            'email'                     => $request->input('email'),
            'prefisso_telefono'         => $request->input('prefisso_telefono', '+39'),
            'telefono'                  => $request->input('telefono'),
            'privacy_accettata'         => $request->boolean('privacy_accettata'),
            'consenso_salute_accettata' => $request->boolean('consenso_salute_accettata'),
            'foto_video_accettata'      => $request->boolean('foto_video_accettata'),
            'allergeni'                 => $request->input('allergeni', []),
            'diete'                     => $request->input('diete', []),
            'note_extra'                => $request->input('note_extra'),
        ];

        if ($isStaff) {
            // Disponibilità staff: raccoglie slot selezionati come array [{data,porzione,tipo}]
            $disponibilita = [];
            foreach ($request->all() as $key => $val) {
                // Formato chiave: YYYY-MM-DD_mattina/pomeriggio_formazione/logistica
                if (preg_match('/^(\d{4}-\d{2}-\d{2})_(mattina|pomeriggio)_(formazione|logistica)$/', $key, $m) && $request->boolean($key)) {
                    $disponibilita[] = ['data' => $m[1], 'porzione' => $m[2], 'tipo' => $m[3]];
                }
            }
            $risposte['disponibilita'] = $disponibilita;
        }

        foreach ($sezioni as $s) {
            $risposte[$s['chiave']] = $request->boolean($s['chiave']);
        }

        return $risposte;
    }

    /** Genera le sezioni pasto/pernotto da mostrare nel primo checkin (merge corsi + staff) */
    private function sezioniPerCheckin(SocioEvento $primary, Evento $evento): array
    {
        $sezioni = [];
        $viste   = [];

        // Sezioni dai corsi
        $iscrizioni = Iscrizione::where('partecipante_id', $primary->partecipante_id)
            ->whereHas('corso', fn ($q) => $q->where('evento_id', $evento->id))
            ->with('corso')
            ->get();

        foreach ($iscrizioni as $isc) {
            foreach ($isc->corso->form_sections ?? [] as $s) {
                if (($s['attivo'] ?? true) === false) continue;
                $chiave = $s['tipo'] . '_' . $s['data'];
                if (isset($viste[$chiave])) continue;
                $viste[$chiave] = true;
                $sezioni[] = ['tipo' => $s['tipo'], 'data' => $s['data'], 'chiave' => $chiave];
            }
        }

        // Sezioni staff (se anche staff)
        $isStaff = IscrizioneStaff::where('partecipante_id', $primary->partecipante_id)
            ->where('evento_id', $evento->id)
            ->exists();

        if ($isStaff) {
            foreach (\App\Services\EventoSezioniGenerator::perGiorni($evento, bypassResidenziale: true, includeColazione: true) as $s) {
                if (isset($viste[$s['chiave']])) continue;
                $viste[$s['chiave']] = true;
                $sezioni[] = $s;
            }
        }

        return $sezioni;
    }

    /** Dati necessari per la view del primo check-in */
    public static function datiForm(Evento $evento): array
    {
        $comitatiRegionali  = ComitatoRegionale::attivo()->orderBy('nome')->get(['id', 'nome']);
        $comitatiLocaliJson = ComitatoLocale::attivo()
            ->orderBy('nome')
            ->get(['id', 'comitato_regionale_id', 'nome'])
            ->groupBy('comitato_regionale_id')
            ->map(fn ($g) => $g->values())
            ->toJson();

        return [
            'comitatiRegionali'  => $comitatiRegionali,
            'comitatiLocaliJson' => $comitatiLocaliJson,
            'allergeniList'      => OpzioneAlimentare::allergeni(),
            'intolleranzeList'   => OpzioneAlimentare::intolleranze(),
            'dieteList'          => OpzioneAlimentare::diete(),
            'esclusioniList'     => OpzioneAlimentare::esclusioni(),
        ];
    }
}
