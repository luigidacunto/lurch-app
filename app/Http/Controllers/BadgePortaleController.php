<?php

namespace App\Http\Controllers;

use App\Models\Evento;
use App\Models\Iscrizione;
use App\Models\IscrizioneStaff;
use App\Models\SocioEvento;
use App\Models\User;

class BadgePortaleController extends Controller
{
    public function __invoke(Evento $evento, string $token)
    {
        // QR code disabilitato 24 ore dopo la chiusura dell'evento
        if ($evento->finestraOperativaScaduta()) {
            return view('badge.evento-concluso', compact('evento'));
        }

        $primary = self::resolveToken($evento, $token);
        $socio   = $primary->partecipante;

        if (! auth()->check() || ! $this->puoGestireCheckin(auth()->user(), $evento)) {
            return view('badge.portale', compact('socio', 'evento', 'token'));
        }

        $ultimoAccesso  = $primary->ultimoAccesso;
        $tipoSuccessivo = ($ultimoAccesso?->tipo === 'entrata') ? 'uscita' : 'entrata';
        $primoCheckin   = $primary->rispostaCheckin === null;

        $storiciOggi = $primary->accessi()
            ->whereDate('timestamp_accesso', today())
            ->orderBy('timestamp_accesso')
            ->with('operatore')
            ->get();

        // Determina tipo per la UI
        $iscrizioni    = $primary->iscrizioni()->with('corso')->get();
        $hasIscrizioni = $iscrizioni->isNotEmpty();
        $hasStaff      = $primary->iscrizioneStaff()->exists();
        $tipoIscrizione = ($hasIscrizioni && $hasStaff) ? 'corsista+staff' : ($hasStaff ? 'staff' : 'corsista');

        $datiForm   = $primoCheckin ? CheckinAccessoController::datiForm($evento) : [];
        $sezioni    = $primoCheckin ? $this->sezioniMerged($primary, $evento) : [];
        $prefill    = $primoCheckin ? $this->prefillMerged($primary) : [];
        $slotsStaff = ($primoCheckin && $hasStaff) ? $this->generaSlotsStaff($evento) : [];

        return view('checkin.accesso', [
            'socio'          => $socio,
            'evento'         => $evento,
            'iscrizioneObj'  => $primary,
            'tipoIscrizione' => $tipoIscrizione,
            'iscrizioni'     => $iscrizioni,
            'ultimoAccesso'  => $ultimoAccesso,
            'tipoSuccessivo' => $tipoSuccessivo,
            'primoCheckin'   => $primoCheckin,
            'storiciOggi'    => $storiciOggi,
            'datiForm'       => $datiForm,
            'prefill'        => $prefill,
            'sezioni'        => $sezioni,
            'slotsStaff'     => $slotsStaff,
        ]);
    }

    /**
     * Risolve qualsiasi token (canonico, legacy, o da Iscrizione/IscrizioneStaff)
     * al SocioEvento primary (id minore) per quel partecipante×evento.
     */
    public static function resolveToken(Evento $evento, string $token): SocioEvento
    {
        // 1. Match diretto sul badge_token canonico
        $se = SocioEvento::where('evento_id', $evento->id)
            ->where('badge_token', $token)
            ->first();

        // 2. Match nel campo legacy (JSONB contains)
        $se ??= SocioEvento::where('evento_id', $evento->id)
            ->whereJsonContains('badge_tokens_legacy', $token)
            ->first();

        // 3. Fallback: cerca direttamente nelle tabelle Iscrizione/IscrizioneStaff
        if (! $se) {
            $partId = Iscrizione::where('badge_token', $token)
                ->whereHas('corso', fn ($q) => $q->where('evento_id', $evento->id))
                ->value('partecipante_id')
                ?? IscrizioneStaff::where('badge_token', $token)
                    ->where('evento_id', $evento->id)
                    ->value('partecipante_id');

            abort_if(! $partId, 404);

            $se = SocioEvento::where('partecipante_id', $partId)
                ->where('evento_id', $evento->id)
                ->orderBy('id')
                ->firstOrFail();
        }

        // Garantisce di restituire sempre il primary (id minore) per quel partecipante×evento
        $minId = SocioEvento::where('partecipante_id', $se->partecipante_id)
            ->where('evento_id', $evento->id)
            ->min('id');

        if ($se->id !== $minId) {
            $se = SocioEvento::where('partecipante_id', $se->partecipante_id)
                ->where('evento_id', $evento->id)
                ->orderBy('id')
                ->first();
        }

        return $se->load(['partecipante', 'ultimoAccesso', 'rispostaCheckin']);
    }

    /** Merge di tutte le form_sections dei corsi + sezioni staff, dedup per tipo+data */
    private function sezioniMerged(SocioEvento $primary, Evento $evento): array
    {
        $sezioni = [];
        $viste   = [];

        // Sezioni dai corsi (corsisti)
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
                $sezioni[] = [
                    'tipo'   => $s['tipo'],
                    'data'   => $s['data'],
                    'chiave' => $chiave,
                    'label'  => ucfirst($s['tipo']) . ' · ' . \Carbon\Carbon::parse($s['data'])->translatedFormat('l j F'),
                ];
            }
        }

        // Sezioni staff (se anche staff): genera da date evento
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

        // Ordina per data, poi per tipo canonico
        $ordTipo = ['colazione' => 0, 'pranzo' => 1, 'cena' => 2, 'pernotto' => 3];
        usort($sezioni, fn ($a, $b) =>
            $a['data'] <=> $b['data'] ?: ($ordTipo[$a['tipo']] ?? 9) <=> ($ordTipo[$b['tipo']] ?? 9)
        );

        return $sezioni;
    }

    /** Merge dei prefill da tutte le RispostaPreregistrazione del partecipante per l'evento */
    private function prefillMerged(SocioEvento $primary): array
    {
        $prefill = [];

        $risposte = collect();

        // Raccogli tutte le risposte preregistrazione del partecipante per l'evento
        $iscrizioni = Iscrizione::where('partecipante_id', $primary->partecipante_id)
            ->whereHas('corso', fn ($q) => $q->where('evento_id', $primary->evento_id))
            ->with('rispostaPreregistrazione')
            ->get();

        foreach ($iscrizioni as $isc) {
            if ($isc->rispostaPreregistrazione) {
                $risposte->push($isc->rispostaPreregistrazione);
            }
        }

        $staffIscrizione = IscrizioneStaff::where('partecipante_id', $primary->partecipante_id)
            ->where('evento_id', $primary->evento_id)
            ->with('rispostaPreregistrazione')
            ->first();

        if ($staffIscrizione?->rispostaPreregistrazione) {
            $risposte->push($staffIscrizione->rispostaPreregistrazione);
        }

        if ($risposte->isEmpty()) {
            return [];
        }

        // Prende la prima risposta come base (dati anagrafici, consensi)
        $prima = $risposte->first();
        $prefill = $prima->risposte ?? [];
        $prefill['privacy_accettata']         = $prima->privacy_accettata;
        $prefill['consenso_salute_accettata'] = $prima->consenso_salute_accettata;
        $prefill['foto_video_accettata']      = $prima->foto_video_accettata;

        // Merge dei pasti: union di tutti (chiave = tipo_data)
        foreach ($risposte as $risposta) {
            foreach ($risposta->risposte ?? [] as $key => $val) {
                // Chiavi pasto: pattern tipo_YYYY-MM-DD
                if (preg_match('/^(pranzo|cena|colazione|pernotto)_\d{4}-\d{2}-\d{2}$/', $key)) {
                    if ($val) {
                        $prefill[$key] = true;
                    } elseif (! isset($prefill[$key])) {
                        $prefill[$key] = false;
                    }
                }
            }
        }

        // Prefill disponibilità staff: converte array [{data,porzione,tipo}] in chiavi flat
        if ($staffIscrizione?->rispostaPreregistrazione) {
            $disp = $staffIscrizione->rispostaPreregistrazione->risposte['disponibilita'] ?? [];
            foreach ($disp as $d) {
                $chiave = ($d['data'] ?? '') . '_' . ($d['porzione'] ?? '') . '_' . ($d['tipo'] ?? '');
                $prefill[$chiave] = true;
            }
        }

        return $prefill;
    }

    /** Genera gli slot disponibilità staff: giorno prima + giorni evento + giorno dopo */
    private function generaSlotsStaff(Evento $evento): array
    {
        $slots  = [];
        $ordine = 0;

        $giorniAttivi = $evento->giorniEvento();

        $giorni = collect();
        $giorni->push(
            $giorniAttivi->isNotEmpty()
                ? $giorniAttivi->first()->copy()->subDay()
                : \Carbon\Carbon::parse($evento->data_inizio)->subDay()
        ); // giorno prima
        foreach ($giorniAttivi as $g) {
            $giorni->push($g->copy());
        }
        $giorni->push(
            $giorniAttivi->isNotEmpty()
                ? $giorniAttivi->last()->copy()->addDay()
                : \Carbon\Carbon::parse($evento->data_fine)->addDay()
        ); // giorno dopo

        foreach ($giorni as $giorno) {
            foreach (['mattina', 'pomeriggio'] as $porzione) {
                $data    = $giorno->format('Y-m-d');
                $label   = $giorno->translatedFormat('l j F') . ' — ' . ($porzione === 'mattina' ? 'Mattina' : 'Pomeriggio');
                $slots[] = [
                    'chiave'   => $data . '_' . $porzione,
                    'data'     => $data,
                    'porzione' => $porzione,
                    'label'    => $label,
                    'ordine'   => $ordine++,
                ];
            }
        }

        return $slots;
    }

    public static function puoGestireCheckin(User $user, Evento $evento): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        if ($user->isAdmin()) {
            return $evento->comitato_regionale_id === $user->comitato_regionale_id;
        }

        if ($user->isAdminLocale()) {
            return $evento->comitato_locale_id === $user->comitato_locale_id
                || $evento->comitato_regionale_id === $user->comitato_regionale_id;
        }

        if ($user->isOperatore()) {
            return $user->can('checkin.manage')
                && $evento->operatori->contains('id', $user->id);
        }

        return false;
    }
}
