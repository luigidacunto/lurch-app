<?php

namespace App\Http\Controllers;

use App\Mail\GraduatoriaCandidaturaConfermata;
use App\Models\ComitatoLocale;
use App\Models\ComitatoRegionale;
use App\Models\EmailTemplate;
use App\Models\EmailVerifica;
use App\Models\Graduatoria;
use App\Models\GraduatoriaCandidato;
use App\Models\PrivacyTemplate;
use App\Models\Socio;
use App\Services\EmailVerificaCodiceHandler;
use App\Services\Maschera;
use App\Services\PrivacyPlaceholderResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class GraduatoriaPubblicaController extends Controller
{
    public function mostra(string $slug): View
    {
        $graduatoria = Graduatoria::where('codice_pubblico', $slug)->firstOrFail();

        if (! $graduatoria->isAperta()) {
            return view('graduatoria.chiuso', compact('graduatoria'));
        }

        return view('graduatoria.form-cf', compact('graduatoria'));
    }

    public function verifica(Request $request, string $slug): \Illuminate\Http\RedirectResponse
    {
        $graduatoria = Graduatoria::where('codice_pubblico', $slug)->firstOrFail();

        if (! $graduatoria->isAperta()) {
            return redirect()->route('graduatoria.mostra', $slug);
        }

        $validated = $request->validate([
            'codice_fiscale' => ['required', 'string', 'regex:/^[A-Za-z0-9]{16}$/'],
        ], [
            'codice_fiscale.regex' => 'Il codice fiscale inserito non è valido.',
        ]);

        $cf = strtoupper($validated['codice_fiscale']);

        $giaIscritto = GraduatoriaCandidato::where('graduatoria_id', $graduatoria->id)
            ->where('codice_fiscale', $cf)
            ->exists();

        if ($giaIscritto) {
            return redirect()->route('graduatoria.mostra', $slug)
                ->withErrors(['codice_fiscale' => 'Questo codice fiscale è già iscritto a questa graduatoria.']);
        }

        session(["graduatoria.{$slug}.cf" => $cf]);
        session()->forget("graduatoria.{$slug}.cf_forzato");

        return redirect()->route('graduatoria.completa', $slug);
    }

    public function completa(string $slug): View|\Illuminate\Http\RedirectResponse
    {
        $graduatoria = Graduatoria::where('codice_pubblico', $slug)->firstOrFail();
        $cf          = session("graduatoria.{$slug}.cf");

        if (! $cf || ! $graduatoria->isAperta()) {
            return redirect()->route('graduatoria.mostra', $slug);
        }

        $socio = Socio::where('codice_fiscale', $cf)->first();

        // CF non riconosciuto: prima di sbloccare i campi nome/cognome liberi,
        // chiediamo una conferma esplicita per ridurre gli errori di battitura —
        // vedi forzaInserimento().
        $cfForzato = (bool) session("graduatoria.{$slug}.cf_forzato");
        if (! $socio && ! $cfForzato) {
            return view('graduatoria.cf-non-trovato', compact('graduatoria', 'slug'));
        }

        $comitatiRegionali  = ComitatoRegionale::orderBy('nome')->get(['id', 'nome']);
        $comitatiLocaliJson = ComitatoLocale::orderBy('nome')
            ->get(['id', 'comitato_regionale_id', 'nome'])
            ->groupBy('comitato_regionale_id')
            ->map(fn ($g) => $g->values())
            ->toJson();

        $privacyTemplate = PrivacyTemplate::risolvi('informativa_dati', $graduatoria->comitato_regionale_id);
        $privacyInformativa = $privacyTemplate
            ? PrivacyPlaceholderResolver::sostituisci($privacyTemplate->testo, $graduatoria->comitatoRegionale, $graduatoria->comitatoLocale)
            : null;

        return view('graduatoria.form-completa', [
            'graduatoria'        => $graduatoria,
            'socio'              => $socio,
            'codiceFiscale'      => $cf,
            'comitatiRegionali'  => $comitatiRegionali,
            'comitatiLocaliJson' => $comitatiLocaliJson,
            'emailMascherata'    => $socio ? Maschera::email($socio->email) : null,
            'telefonoMascherato' => $socio ? Maschera::telefono($socio->telefono) : null,
            'privacyInformativa' => $privacyInformativa,
            'privacyInformativaPdf' => $privacyTemplate?->pdf_path,
            'privacyVersione'    => $privacyTemplate?->versione ?? 1,
        ]);
    }

    /**
     * Conferma esplicita che il CF non trovato va comunque inserito manualmente
     * (nuovo volontario non ancora sincronizzato, o candidato realmente esterno
     * all'anagrafica). Sblocca i campi nome/cognome liberi in completa().
     */
    public function forzaInserimento(string $slug): \Illuminate\Http\RedirectResponse
    {
        $graduatoria = Graduatoria::where('codice_pubblico', $slug)->firstOrFail();

        if (! session("graduatoria.{$slug}.cf") || ! $graduatoria->isAperta()) {
            return redirect()->route('graduatoria.mostra', $slug);
        }

        session(["graduatoria.{$slug}.cf_forzato" => true]);

        return redirect()->route('graduatoria.completa', $slug);
    }

    public function salva(Request $request, string $slug): \Illuminate\Http\RedirectResponse
    {
        $graduatoria = Graduatoria::where('codice_pubblico', $slug)->firstOrFail();
        $cf          = session("graduatoria.{$slug}.cf");

        if (! $cf || ! $graduatoria->isAperta()) {
            return redirect()->route('graduatoria.mostra', $slug);
        }

        if (GraduatoriaCandidato::where('graduatoria_id', $graduatoria->id)->where('codice_fiscale', $cf)->exists()) {
            return redirect()->route('graduatoria.mostra', $slug)
                ->withErrors(['codice_fiscale' => 'Questo codice fiscale è già iscritto a questa graduatoria.']);
        }

        $socio = Socio::where('codice_fiscale', $cf)->first();

        $validated = $request->validate([
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
            // La graduatoria richiede sempre un volontario dell'associazione: il
            // consenso all'importazione/consultazione delle qualifiche da GAIA è
            // sempre obbligatorio qui (a differenza della candidatura staff, che
            // ammette anche docenti esterni).
            'consenso_gaia_accettata' => ['accepted'],
            'attestazione_presidente' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'diploma_autodichiarato'  => $graduatoria->richiede_diploma ? ['accepted'] : ['nullable'],
            'autocertificazione'      => $graduatoria->richiede_autocertificazione
                ? ['required', 'file', 'mimes:pdf', 'max:10240']
                : ['nullable'],
        ], [
            'privacy_accettata.accepted' => 'Devi accettare il trattamento dei dati anagrafici per procedere.',
            'consenso_gaia_accettata.accepted' => 'Devi accettare la consultazione delle tue qualifiche da GAIA per procedere.',
            'diploma_autodichiarato.accepted' => 'Devi dichiarare il possesso del titolo di studio richiesto per procedere.',
            'autocertificazione.required' => 'Devi allegare il documento di autocertificazione richiesto per procedere.',
        ]);

        $emailFinale    = filled($validated['email'] ?? null) ? $validated['email'] : $socio?->email;
        $telefonoFinale = filled($validated['telefono'] ?? null) ? $validated['telefono'] : $socio?->telefono;

        if (blank($emailFinale)) {
            return back()->withErrors(['email' => 'Il campo email è obbligatorio.'])->withInput();
        }

        $stato = $socio ? 'confermato' : 'in_attesa';

        $privacyVersione = PrivacyTemplate::risolvi('informativa_dati', $graduatoria->comitato_regionale_id)?->versione ?? 1;

        $candidato = GraduatoriaCandidato::create([
            'graduatoria_id'         => $graduatoria->id,
            'socio_id'               => $socio?->id,
            'codice_fiscale'         => $cf,
            'nome'                   => $socio?->nome ?? $validated['nome'],
            'cognome'                => $socio?->cognome ?? $validated['cognome'],
            'email'                  => $emailFinale,
            'telefono'               => $telefonoFinale,
            'comitato_regionale_id'  => $validated['comitato_regionale_id'],
            'comitato_locale_id'     => $validated['comitato_locale_id'],
            'stato'                  => $stato,
            'privacy_accettata'      => true,
            'consenso_gaia_accettata' => true,
            'privacy_versione_accettata' => $privacyVersione,
            'confermato_at'          => $socio ? now() : null,
            'diploma_autodichiarato' => $graduatoria->richiede_diploma,
        ]);

        if ($graduatoria->richiede_attestazione_presidente && $request->hasFile('attestazione_presidente')) {
            $path = $request->file('attestazione_presidente')->store('graduatorie-attestazioni/' . $candidato->id, 'local');
            $candidato->update(['attestazione_presidente_path' => $path]);
        }

        if ($graduatoria->richiede_autocertificazione && $request->hasFile('autocertificazione')) {
            $path = $request->file('autocertificazione')->store('graduatorie-autocertificazioni/' . $candidato->id, 'local');
            $candidato->update(['autocertificazione_path' => $path]);
        }

        if ($socio) {
            // L'email compilata a mano dal form pubblico (CF noto ma non autenticato) non
            // sovrascrive mai direttamente il contatto esistente: va in "pending" finché
            // non viene confermata via link/codice. Se il socio non aveva ancora un'email
            // non c'è nulla da proteggere: si imposta subito.
            $emailCambiata = filled($validated['email'] ?? null) && $validated['email'] !== $socio->email;

            $datiSocio = [
                'telefono'              => $telefonoFinale,
                'comitato_regionale_id' => $validated['comitato_regionale_id'],
                'comitato_locale_id'    => $validated['comitato_locale_id'],
                'consenso_gaia_accettata'    => true,
                'privacy_versione_accettata' => $privacyVersione,
            ];

            if ($emailCambiata) {
                $datiSocio['email_in_verifica'] = $emailFinale;
            } elseif (blank($socio->email)) {
                $datiSocio['email'] = $emailFinale;
            }

            $socio->update($datiSocio);

            Mail::to($candidato->email)->send(new GraduatoriaCandidaturaConfermata($candidato));
            EmailVerifica::inviaSeNecessario($socio, $emailCambiata ? $emailFinale : null);

            if (! $socio->fresh()->email_verificata_at || $emailCambiata) {
                session(["graduatoria.{$slug}.socio_id_verifica" => $socio->id]);
            }

            $avvisi = $graduatoria->avvisiRequisiti($socio);
            if (! empty($avvisi)) {
                session(["graduatoria.{$slug}.avvisi_requisiti" => $avvisi]);
            }
        } else {
            // Nessun socio agganciato (docente/candidato non ancora in archivio): la
            // verifica avviene direttamente sulla candidatura, riportata sul socio se
            // in futuro un operatore la aggancia (vedi CandidaturaSocioSync::sincronizza()).
            EmailVerifica::inviaSeNecessario($candidato, $candidato->email);
            if (! $candidato->fresh()->email_verificata_at) {
                session(["graduatoria.{$slug}.candidato_id_verifica" => $candidato->id]);
            }
        }

        session()->forget("graduatoria.{$slug}.cf");
        session()->forget("graduatoria.{$slug}.cf_forzato");
        session(["graduatoria.{$slug}.grazie_stato" => $stato]);

        return redirect()->route('graduatoria.grazie', $slug);
    }

    public function grazie(string $slug): View
    {
        $graduatoria = Graduatoria::where('codice_pubblico', $slug)->firstOrFail();
        $stato       = session("graduatoria.{$slug}.grazie_stato", 'confermato');
        $avvisi      = session("graduatoria.{$slug}.avvisi_requisiti", []);

        [$target, $sessionKey] = $this->targetVerificaAttivo($slug);

        $emailDaVerificare  = null;
        $contattoFormazione = null;
        if ($target) {
            $daVerificare = ! $target->email_verificata_at || filled($target->email_in_verifica);
            if ($daVerificare) {
                $emailDaVerificare  = Maschera::email($target->email_in_verifica ?? $target->email);
                $contattoFormazione = $this->contattoFormazione($graduatoria);
            } else {
                session()->forget($sessionKey);
            }
        }

        return view('graduatoria.grazie', compact('graduatoria', 'stato', 'emailDaVerificare', 'contattoFormazione', 'avvisi'));
    }

    /**
     * Indirizzo a cui indirizzare chi non riceve l'email di verifica, preso dal reply-to
     * del template email graduatoria per la regione. Un reply-to "noreply" non è un
     * contatto utile — in quel caso si ricade su un messaggio generico.
     */
    private function contattoFormazione(Graduatoria $graduatoria): ?string
    {
        $replyTo = EmailTemplate::risolvi($graduatoria->comitato_regionale_id, 'graduatoria')?->reply_to;

        if (! $replyTo || str_contains(strtolower($replyTo), 'noreply') || str_contains(strtolower($replyTo), 'no-reply')) {
            return null;
        }

        return $replyTo;
    }

    /**
     * Risolve quale verifica email è in corso per questa sessione: un socio agganciato
     * (CF trovato) o la candidatura stessa (CF non trovato, nessun socio da proteggere).
     * Mai entrambe le chiavi valorizzate nella stessa sottomissione — vedi salva().
     *
     * @return array{0: \Illuminate\Database\Eloquent\Model|null, 1: string|null, 2: string|null}
     */
    private function targetVerificaAttivo(string $slug): array
    {
        if ($socioId = session("graduatoria.{$slug}.socio_id_verifica")) {
            return [Socio::find($socioId), "graduatoria.{$slug}.socio_id_verifica", 'socio_id'];
        }

        if ($candidatoId = session("graduatoria.{$slug}.candidato_id_verifica")) {
            return [GraduatoriaCandidato::find($candidatoId), "graduatoria.{$slug}.candidato_id_verifica", 'graduatoria_candidato_id'];
        }

        return [null, null, null];
    }

    public function verificaEmailCodice(Request $request, string $slug): \Illuminate\Http\RedirectResponse
    {
        [$target, $sessionKey, $colonna] = $this->targetVerificaAttivo($slug);

        if (! $target) {
            return redirect()->route('graduatoria.grazie', $slug);
        }

        $request->validate([
            'codice' => ['required', 'string', 'size:6'],
        ], [
            'codice.size' => 'Il codice deve essere di 6 cifre.',
        ]);

        $risultato = EmailVerificaCodiceHandler::verifica($target, $colonna, $request->codice);

        if ($risultato['nessunaVerificaAttiva']) {
            session()->forget($sessionKey);
            return redirect()->route('graduatoria.grazie', $slug);
        }

        if (! $risultato['ok']) {
            return back()->withErrors(['codice' => $risultato['error']]);
        }

        session()->forget($sessionKey);

        return redirect()->route('graduatoria.grazie', $slug)->with('email_verificata', true);
    }
}
