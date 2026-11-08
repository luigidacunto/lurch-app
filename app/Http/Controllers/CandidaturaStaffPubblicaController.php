<?php

namespace App\Http\Controllers;

use App\Models\CandidaturaStaff;
use App\Models\ComitatoLocale;
use App\Models\ComitatoRegionale;
use App\Models\EmailVerifica;
use App\Models\Evento;
use App\Models\PrivacyTemplate;
use App\Models\Socio;
use App\Services\EmailVerificaCodiceHandler;
use App\Services\EventoSezioniGenerator;
use App\Services\Maschera;
use App\Services\PrivacyPlaceholderResolver;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CandidaturaStaffPubblicaController extends Controller
{
    public function mostra(string $uuid): View
    {
        $evento = Evento::where('candidature_staff_uuid', $uuid)->firstOrFail();

        if (! $evento->candidatureStaffAperte()) {
            return view('candidatura-staff.chiuso', compact('evento'));
        }

        return view('candidatura-staff.form-cf', compact('evento'));
    }

    public function verifica(Request $request, string $uuid): \Illuminate\Http\RedirectResponse
    {
        $evento = Evento::where('candidature_staff_uuid', $uuid)->firstOrFail();

        if (! $evento->candidatureStaffAperte()) {
            return redirect()->route('candidatura-staff.mostra', $uuid);
        }

        $validated = $request->validate([
            'codice_fiscale' => ['required', 'string', 'regex:/^[A-Za-z0-9]{16}$/'],
        ], [
            'codice_fiscale.regex' => 'Il codice fiscale inserito non è valido.',
        ]);

        $cf = strtoupper($validated['codice_fiscale']);

        $giaCandidato = CandidaturaStaff::where('evento_id', $evento->id)
            ->where('codice_fiscale', $cf)
            ->exists();

        if ($giaCandidato) {
            return redirect()->route('candidatura-staff.mostra', $uuid)
                ->withErrors(['codice_fiscale' => 'Questo codice fiscale ha già presentato una candidatura per questo evento.']);
        }

        session(["candidatura-staff.{$uuid}.cf" => $cf]);
        session(["candidatura-staff.{$uuid}.dichiarazione_esterno" => $request->boolean('dichiarazione_esterno')]);
        session()->forget("candidatura-staff.{$uuid}.cf_forzato");

        return redirect()->route('candidatura-staff.completa', $uuid);
    }

    public function completa(string $uuid): View|\Illuminate\Http\RedirectResponse
    {
        $evento = Evento::where('candidature_staff_uuid', $uuid)->firstOrFail();
        $cf     = session("candidatura-staff.{$uuid}.cf");

        if (! $cf || ! $evento->candidatureStaffAperte()) {
            return redirect()->route('candidatura-staff.mostra', $uuid);
        }

        $socio = Socio::where('codice_fiscale', $cf)->first();

        $dichiarazioneEsterno = (bool) session("candidatura-staff.{$uuid}.dichiarazione_esterno");

        // CF non riconosciuto e nessuna dichiarazione di esterno: prima di sbloccare i
        // campi nome/cognome liberi, chiediamo una conferma esplicita per ridurre gli
        // errori di battitura — vedi forzaInserimento().
        $cfForzato = (bool) session("candidatura-staff.{$uuid}.cf_forzato");
        if (! $socio && ! $dichiarazioneEsterno && ! $cfForzato) {
            return view('candidatura-staff.cf-non-trovato', compact('evento', 'uuid'));
        }

        $comitatiRegionali  = ComitatoRegionale::orderBy('nome')->get(['id', 'nome']);
        $comitatiLocaliJson = ComitatoLocale::orderBy('nome')
            ->get(['id', 'comitato_regionale_id', 'nome'])
            ->groupBy('comitato_regionale_id')
            ->map(fn ($g) => $g->values())
            ->toJson();

        $regioneId       = $evento->comitato_regionale_id;
        $privacyTemplate = PrivacyTemplate::risolvi('informativa_dati', $regioneId);
        $privacy         = $evento->privacy_override_informativa ?? $privacyTemplate?->testo;
        if ($privacy) {
            $privacy = PrivacyPlaceholderResolver::sostituisci($privacy, $evento->comitatoRegionale, $evento->comitatoLocale);
        }

        $giorniReali = $evento->giorniEvento()->map(fn ($d) => $d->format('Y-m-d'))->all();
        $slotDisponibilita = collect(EventoSezioniGenerator::slotDisponibilitaStaff($evento))
            ->map(fn (array $slot) => $slot + ['extra' => ! in_array($slot['data'], $giorniReali)])
            ->all();

        return view('candidatura-staff.form-completa', [
            'evento'             => $evento,
            'socio'              => $socio,
            'codiceFiscale'      => $cf,
            'dichiarazioneEsterno' => $dichiarazioneEsterno,
            'slotDisponibilita'  => $slotDisponibilita,
            'corsi'              => $evento->corsi()->orderBy('nome')->get(['id', 'nome', 'numero_protocollo']),
            'comitatiRegionali'  => $comitatiRegionali,
            'comitatiLocaliJson' => $comitatiLocaliJson,
            'emailMascherata'    => $socio ? Maschera::email($socio->email) : null,
            'telefonoMascherato' => $socio ? Maschera::telefono($socio->telefono) : null,
            'privacyInformativa' => $privacy,
            'privacyVersione'    => $privacyTemplate?->versione ?? 1,
        ]);
    }

    /**
     * Conferma esplicita che il CF non trovato va comunque inserito manualmente
     * (nuovo volontario non ancora sincronizzato). Sblocca i campi nome/cognome
     * liberi in completa(). Non necessaria se è già stata dichiarata l'esternità.
     */
    public function forzaInserimento(string $uuid): \Illuminate\Http\RedirectResponse
    {
        $evento = Evento::where('candidature_staff_uuid', $uuid)->firstOrFail();

        if (! session("candidatura-staff.{$uuid}.cf") || ! $evento->candidatureStaffAperte()) {
            return redirect()->route('candidatura-staff.mostra', $uuid);
        }

        session(["candidatura-staff.{$uuid}.cf_forzato" => true]);

        return redirect()->route('candidatura-staff.completa', $uuid);
    }

    public function salva(Request $request, string $uuid): \Illuminate\Http\RedirectResponse
    {
        $evento = Evento::where('candidature_staff_uuid', $uuid)->firstOrFail();
        $cf     = session("candidatura-staff.{$uuid}.cf");

        if (! $cf || ! $evento->candidatureStaffAperte()) {
            return redirect()->route('candidatura-staff.mostra', $uuid);
        }

        if (CandidaturaStaff::where('evento_id', $evento->id)->where('codice_fiscale', $cf)->exists()) {
            return redirect()->route('candidatura-staff.mostra', $uuid)
                ->withErrors(['codice_fiscale' => 'Questo codice fiscale ha già presentato una candidatura per questo evento.']);
        }

        $socio = Socio::where('codice_fiscale', $cf)->first();
        $dichiarazioneEsterno = (bool) session("candidatura-staff.{$uuid}.dichiarazione_esterno");

        $slots       = collect(EventoSezioniGenerator::slotDisponibilitaStaff($evento));
        $giorniReali = $evento->giorniEvento()->map(fn ($d) => $d->format('Y-m-d'))->all();
        $slotReali   = $slots->filter(fn ($s) => in_array($s['data'], $giorniReali))->pluck('chiave')->all();
        $slotExtra   = $slots->filter(fn ($s) => ! in_array($s['data'], $giorniReali))->pluck('chiave')->all();
        $corsiValidi = $evento->corsi()->pluck('id')->all();

        // Ogni slot è una scelta singola (docente O logistica, mai entrambe): niente doppia
        // checkbox per evitare l'ambiguità nel pratico. Gli slot extra montaggio/smontaggio
        // ammettono solo logistica. Pre-check sul raw input solo per decidere se richiedere i
        // corsi: il valore viene comunque validato rigidamente dalla regola 'in:' sotto.
        $disponibilitaInput = $request->input('disponibilita', []);
        $haDocenteInput = collect($slotReali)->contains(fn ($chiave) => ($disponibilitaInput[$chiave] ?? null) === 'docente');

        $rules = [
            'nome'                  => $socio ? ['nullable'] : ['required', 'string', 'max:255'],
            'cognome'               => $socio ? ['nullable'] : ['required', 'string', 'max:255'],
            // 'nullable' qui: quando il campo resta oscurato/bloccato in UI il browser
            // non lo invia affatto (input disabled) — in quel caso usiamo il valore già
            // in archivio, mai un valore mascherato "AB****@C****.it" tornato dal client.
            'email'                 => ['nullable', 'email', 'max:255'],
            'telefono'              => ['nullable', 'string', 'max:50'],
            'comitato_regionale_id' => ['required', 'integer', 'exists:comitati_regionali,id'],
            'comitato_locale_id'    => ['required', 'integer', 'exists:comitati_locali,id'],
            'privacy_accettata'     => ['accepted'],
            // Nessun consenso GAIA per chi si è dichiarato esterno all'associazione:
            // non ha qualifiche importate da consultare.
            'consenso_gaia_accettata' => $dichiarazioneEsterno ? ['nullable', 'boolean'] : ['accepted'],
            'corsi'                 => $haDocenteInput ? ['required', 'array', 'min:1'] : ['nullable', 'array'],
            'corsi.*'               => ['integer', 'in:' . implode(',', $corsiValidi ?: [0])],
        ];
        foreach ($slotReali as $chiave) {
            $rules["disponibilita.$chiave"] = ['nullable', 'in:docente,logistica'];
        }
        foreach ($slotExtra as $chiave) {
            $rules["disponibilita.$chiave"] = ['nullable', 'in:logistica'];
        }

        $validated = $request->validate($rules, [
            'privacy_accettata.accepted'       => 'Devi accettare il trattamento dei dati anagrafici per procedere.',
            'consenso_gaia_accettata.accepted' => 'Devi accettare la consultazione delle tue qualifiche da GAIA per procedere.',
            'corsi.required'                   => 'Seleziona almeno un corso in cui farai docenza.',
        ]);

        // Le chiavi sono "Y-m-d_porzione": le riportiamo alla forma {data,porzione,tipo}
        // salvata a DB (stessa shape di RispostaPreregistrazione.risposte['disponibilita'],
        // ma con vocabolario 'docente'/'logistica' invece di 'formazione'/'logistica' —
        // tabella nuova, nessun dato storico da rispettare).
        $disponibilita = collect($validated['disponibilita'] ?? [])
            ->map(function (?string $tipo, string $chiave) {
                if (! $tipo) return null;
                [$data, $porzione] = [substr($chiave, 0, 10), substr($chiave, 11)];
                return ['data' => $data, 'porzione' => $porzione, 'tipo' => $tipo];
            })
            ->filter()
            ->values();

        if ($disponibilita->isEmpty()) {
            return back()->withErrors(['disponibilita' => 'Seleziona almeno uno slot di disponibilità.'])->withInput();
        }

        $emailFinale    = filled($validated['email'] ?? null) ? $validated['email'] : $socio?->email;
        $telefonoFinale = filled($validated['telefono'] ?? null) ? $validated['telefono'] : $socio?->telefono;

        if (blank($emailFinale)) {
            return back()->withErrors(['email' => 'Il campo email è obbligatorio.'])->withInput();
        }

        $privacyVersione = PrivacyTemplate::risolvi('informativa_dati', $evento->comitato_regionale_id)?->versione ?? 1;

        $candidatura = CandidaturaStaff::create([
            'evento_id'              => $evento->id,
            'socio_id'               => $socio?->id,
            'codice_fiscale'         => $cf,
            'nome'                   => $socio?->nome ?? $validated['nome'],
            'cognome'                => $socio?->cognome ?? $validated['cognome'],
            'email'                  => $emailFinale,
            'telefono'               => $telefonoFinale,
            'comitato_regionale_id'  => $validated['comitato_regionale_id'],
            'comitato_locale_id'     => $validated['comitato_locale_id'],
            'disponibilita'          => $disponibilita->all(),
            'stato'                  => 'in_attesa',
            'privacy_accettata'      => true,
            'consenso_gaia_accettata' => ! $dichiarazioneEsterno,
            'privacy_versione_accettata' => $privacyVersione,
            'dichiarazione_esterno'  => $dichiarazioneEsterno,
        ]);

        if ($candidatura->haDisponibilitaDocente()) {
            $candidatura->corsi()->sync($validated['corsi']);
        }

        if ($socio) {
            // Stesso staging del form pubblico graduatorie: non sovrascrive mai
            // direttamente il contatto esistente, va in "pending" finché non viene
            // confermata via link/codice. Se il socio non aveva ancora un'email non
            // c'è nulla da proteggere: si imposta subito.
            $emailCambiata = filled($validated['email'] ?? null) && $validated['email'] !== $socio->email;

            $datiSocio = [
                'telefono'              => $telefonoFinale,
                'comitato_regionale_id' => $validated['comitato_regionale_id'],
                'comitato_locale_id'    => $validated['comitato_locale_id'],
            ];

            if (! $dichiarazioneEsterno) {
                $datiSocio['consenso_gaia_accettata']    = true;
                $datiSocio['privacy_versione_accettata'] = $privacyVersione;
            }

            if ($emailCambiata) {
                $datiSocio['email_in_verifica'] = $emailFinale;
            } elseif (blank($socio->email)) {
                $datiSocio['email'] = $emailFinale;
            }

            $socio->update($datiSocio);

            EmailVerifica::inviaSeNecessario($socio, $emailCambiata ? $emailFinale : null);

            if (! $socio->fresh()->email_verificata_at || $emailCambiata) {
                session(["candidatura-staff.{$uuid}.socio_id_verifica" => $socio->id]);
            }
        } else {
            // Nessun socio agganciato (CF non trovato — es. docente esterno
            // all'associazione): la verifica avviene direttamente sulla candidatura,
            // riportata sul socio se in futuro un operatore la aggancia (vedi
            // CandidaturaSocioSync::sincronizza()).
            EmailVerifica::inviaSeNecessario($candidatura, $candidatura->email);
            if (! $candidatura->fresh()->email_verificata_at) {
                session(["candidatura-staff.{$uuid}.candidatura_id_verifica" => $candidatura->id]);
            }
        }

        session()->forget("candidatura-staff.{$uuid}.cf");
        session()->forget("candidatura-staff.{$uuid}.cf_forzato");
        session()->forget("candidatura-staff.{$uuid}.dichiarazione_esterno");

        return redirect()->route('candidatura-staff.grazie', $uuid);
    }

    public function grazie(string $uuid): View
    {
        $evento = Evento::where('candidature_staff_uuid', $uuid)->firstOrFail();

        [$target, $sessionKey] = $this->targetVerificaAttivo($uuid);

        $emailDaVerificare = null;
        if ($target) {
            $daVerificare = ! $target->email_verificata_at || filled($target->email_in_verifica);
            if ($daVerificare) {
                $emailDaVerificare = Maschera::email($target->email_in_verifica ?? $target->email);
            } else {
                session()->forget($sessionKey);
            }
        }

        return view('candidatura-staff.grazie', compact('evento', 'emailDaVerificare'));
    }

    /**
     * Risolve quale verifica email è in corso per questa sessione: un socio agganciato
     * (CF trovato) o la candidatura stessa (CF non trovato, nessun socio da proteggere).
     * Mai entrambe le chiavi valorizzate nella stessa sottomissione — vedi salva().
     *
     * @return array{0: \Illuminate\Database\Eloquent\Model|null, 1: string|null, 2: string|null}
     */
    private function targetVerificaAttivo(string $uuid): array
    {
        if ($socioId = session("candidatura-staff.{$uuid}.socio_id_verifica")) {
            return [Socio::find($socioId), "candidatura-staff.{$uuid}.socio_id_verifica", 'socio_id'];
        }

        if ($candidaturaId = session("candidatura-staff.{$uuid}.candidatura_id_verifica")) {
            return [CandidaturaStaff::find($candidaturaId), "candidatura-staff.{$uuid}.candidatura_id_verifica", 'candidatura_staff_id'];
        }

        return [null, null, null];
    }

    public function verificaEmailCodice(Request $request, string $uuid): \Illuminate\Http\RedirectResponse
    {
        [$target, $sessionKey, $colonna] = $this->targetVerificaAttivo($uuid);

        if (! $target) {
            return redirect()->route('candidatura-staff.grazie', $uuid);
        }

        $request->validate([
            'codice' => ['required', 'string', 'size:6'],
        ], [
            'codice.size' => 'Il codice deve essere di 6 cifre.',
        ]);

        $risultato = EmailVerificaCodiceHandler::verifica($target, $colonna, $request->codice);

        if ($risultato['nessunaVerificaAttiva']) {
            session()->forget($sessionKey);
            return redirect()->route('candidatura-staff.grazie', $uuid);
        }

        if (! $risultato['ok']) {
            return back()->withErrors(['codice' => $risultato['error']]);
        }

        session()->forget($sessionKey);

        return redirect()->route('candidatura-staff.grazie', $uuid)->with('email_verificata', true);
    }
}
