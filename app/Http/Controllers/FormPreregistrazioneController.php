<?php

namespace App\Http\Controllers;

use App\Models\ComitatoLocale;
use App\Models\ComitatoRegionale;
use App\Models\Iscrizione;
use App\Models\OpzioneAlimentare;
use App\Models\PrivacyTemplate;
use App\Models\Socio;
use App\Services\PreregistrazioneCorsistaHandler;
use App\Services\PrivacyPlaceholderResolver;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Form di preregistrazione corsista via link email (`/form/{token}`).
 *
 * Tutta la logica (validazione, persistenza, verifica email) vive in
 * App\Services\PreregistrazioneCorsistaHandler, condivisa con il gemello PWA
 * App\Http\Controllers\App\AppFormCorsistController: le due copie NON possono divergere.
 */
class FormPreregistrazioneController extends Controller
{
    public function __construct(private readonly PreregistrazioneCorsistaHandler $handler)
    {
    }

    public function show(string $token): View|\Illuminate\Http\RedirectResponse
    {
        $iscrizione = Iscrizione::with(['corso.evento', 'partecipante.comitatoRegionale', 'partecipante.comitatoLocale', 'rispostaPreregistrazione'])
            ->where('token', $token)
            ->firstOrFail();

        $corso = $iscrizione->corso;

        if (! $corso->form_sections || count($corso->form_sections) === 0) {
            return view('form.non-disponibile', compact('iscrizione', 'corso'));
        }

        $readonly = $iscrizione->rispostaPreregistrazione !== null && ! request()->boolean('preview');

        if ($readonly) {
            return redirect()->route('form.confermata', $token);
        }

        $evento    = $corso->evento;
        $regioneId = $evento->comitato_regionale_id;

        $privacyTemplate = PrivacyTemplate::risolvi('informativa_dati', $regioneId);

        $privacyInformativa = $evento->privacy_override_informativa
            ?? $privacyTemplate?->testo;

        $privacyInformativaPdf = $evento->privacy_override_informativa_pdf
            ?? $privacyTemplate?->pdf_path;

        $privacyVersione = $privacyTemplate?->versione ?? 1;

        if ($privacyInformativa) {
            $privacyInformativa = PrivacyPlaceholderResolver::sostituisci(
                $privacyInformativa,
                $evento->comitatoRegionale,
                $evento->comitatoLocale
            );
        }

        $comitatiRegionali  = ComitatoRegionale::attivo()->orderBy('nome')->get(['id', 'nome']);
        $comitatiLocaliJson = ComitatoLocale::attivo()
            ->orderBy('nome')
            ->get(['id', 'comitato_regionale_id', 'nome'])
            ->groupBy('comitato_regionale_id')
            ->map(fn ($g) => $g->values())
            ->toJson();

        return view('form.preregistrazione', [
            'iscrizione'             => $iscrizione,
            'corso'                  => $corso,
            'evento'                 => $evento,
            'partecipante'           => $iscrizione->partecipante,
            'sezioni'                => $this->handler->sezioniAttive($iscrizione),
            'giaSalvata'             => $iscrizione->rispostaPreregistrazione !== null,
            'risposta'               => $iscrizione->rispostaPreregistrazione,
            'readonly'               => $readonly,
            'preview'                => request()->boolean('preview'),
            'privacyInformativa'     => $privacyInformativa,
            'privacyInformativaPdf'  => $privacyInformativaPdf,
            'privacyVersione'        => $privacyVersione,
            'allergeniList'          => OpzioneAlimentare::allergeni(),
            'intolleranzeList'       => OpzioneAlimentare::intolleranze(),
            'dieteList'              => OpzioneAlimentare::diete(),
            'esclusioniList'         => OpzioneAlimentare::esclusioni(),
            'richiedeNullaosta'      => $this->handler->richiedeNullaosta($iscrizione),
            'comitatiRegionali'      => $comitatiRegionali,
            'comitatiLocaliJson'     => $comitatiLocaliJson,
        ]);
    }

    public function store(Request $request, string $token): \Illuminate\Http\RedirectResponse
    {
        $iscrizione = Iscrizione::with(['corso.evento', 'partecipante', 'rispostaPreregistrazione'])
            ->where('token', $token)
            ->firstOrFail();

        if ($request->boolean('preview')) {
            return back()->with('preview_info', 'Anteprima — il form non è stato salvato.');
        }

        if ($iscrizione->rispostaPreregistrazione !== null) {
            return redirect()->route('form.show', $token);
        }

        $validated = $request->validate(
            $this->handler->regole($iscrizione, $request),
            $this->handler->messaggi()
        );

        $risultato = $this->handler->salva($iscrizione, $validated, $request, fonte: 'pubblico');

        // Email di verifica solo DOPO che il resto è salvato: in caso di rollback l'utente
        // riceverebbe un codice che punta a un record annullato.
        $this->handler->inviaVerificaEmail($risultato, "form.{$token}.socio_id_verifica");

        return redirect()->route('form.confermata', $token);
    }

    public function confermata(string $token): View
    {
        $iscrizione = Iscrizione::with(['corso.evento', 'partecipante.comitatoRegionale', 'rispostaPreregistrazione'])
            ->where('token', $token)
            ->firstOrFail();

        return view('form.confermata', [
            'iscrizione'        => $iscrizione,
            'corso'             => $iscrizione->corso,
            'evento'            => $iscrizione->corso->evento,
            'partecipante'      => $iscrizione->partecipante,
            'risposta'          => $iscrizione->rispostaPreregistrazione,
            'richiedeNullaosta' => $this->handler->richiedeNullaosta($iscrizione),
            'emailDaVerificare' => $this->handler->emailDaVerificare("form.{$token}.socio_id_verifica"),
        ]);
    }

    /**
     * Sostituzione di un documento caricato per errore (ricevuta / nullaosta), finché non
     * verificato. Auth: il token UUID è il segreto, come per tutto il flusso di preregistrazione.
     */
    public function sostituisciDocumento(Request $request, string $token, string $slot): \Illuminate\Http\RedirectResponse
    {
        $iscrizione = Iscrizione::with(['corso.evento', 'partecipante', 'rispostaPreregistrazione'])
            ->where('token', $token)
            ->firstOrFail();

        $request->validate($this->handler->regoleDocumento($slot));

        $esito = $this->handler->sostituisciDocumento($iscrizione, $slot, $request->file('documento'));

        return redirect()->route('form.confermata', $token)
            ->with($esito['ok'] ? 'doc_sostituito' : 'doc_errore', $this->messaggioEsito($esito['motivo']));
    }

    private function messaggioEsito(string $motivo): string
    {
        return match ($motivo) {
            'sostituito'      => 'Documento sostituito con successo.',
            'verificato'      => 'Il documento è già stato verificato dalla segreteria: non è più modificabile.',
            'non_compilato'   => 'Devi prima completare il form.',
            default           => 'Documento non previsto per questa iscrizione.',
        };
    }

    public function verificaEmailCodice(Request $request, string $token): \Illuminate\Http\RedirectResponse
    {
        $sessionKey = "form.{$token}.socio_id_verifica";
        $socio      = ($socioId = session($sessionKey)) ? Socio::find($socioId) : null;

        if (! $socio) {
            return redirect()->route('form.confermata', $token);
        }

        $request->validate([
            'codice' => ['required', 'string', 'size:6'],
        ], [
            'codice.size' => 'Il codice deve essere di 6 cifre.',
        ]);

        $risultato = $this->handler->verificaCodice($socio, $request->codice);

        if ($risultato['nessunaVerificaAttiva']) {
            session()->forget($sessionKey);
            return redirect()->route('form.confermata', $token);
        }

        if (! $risultato['ok']) {
            return back()->withErrors(['codice' => $risultato['error']]);
        }

        session()->forget($sessionKey);

        return redirect()->route('form.confermata', $token)->with('email_verificata', true);
    }
}
