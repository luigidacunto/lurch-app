<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ComitatoLocale;
use App\Models\ComitatoRegionale;
use App\Models\Evento;
use App\Models\Iscrizione;
use App\Models\OpzioneAlimentare;
use App\Models\Presenza;
use App\Models\PrivacyTemplate;
use App\Models\Socio;
use App\Models\SocioEvento;
use App\Services\PreregistrazioneCorsistaHandler;
use App\Services\PrivacyPlaceholderResolver;
use Illuminate\Http\Request;

/**
 * Gemello PWA del form di preregistrazione corsista (`/app/evento/{evento}/form/corsista/{iscrizione}`).
 *
 * Stessa logica del form pubblico (App\Services\PreregistrazioneCorsistaHandler): pagamento,
 * nullaosta, email obbligatoria e verifica email sono identici — non possono divergere.
 * Qui cambia solo il contesto: auth via sessione `app_socio_id`, redirect dentro la PWA.
 */
class AppFormCorsistController extends Controller
{
    public function __construct(private readonly PreregistrazioneCorsistaHandler $handler)
    {
    }

    private function sessionKey(Iscrizione $iscrizione): string
    {
        return "app.form-corsista.{$iscrizione->id}.socio_id_verifica";
    }

    private function autorizza(Request $request, Evento $evento, Iscrizione $iscrizione): void
    {
        $socioId = $request->session()->get('app_socio_id');

        if ($iscrizione->partecipante_id !== $socioId || $iscrizione->corso->evento_id !== $evento->id) {
            abort(403);
        }
    }

    public function show(Request $request, Evento $evento, Iscrizione $iscrizione)
    {
        $iscrizione->load(['corso.evento', 'partecipante.comitatoRegionale', 'partecipante.comitatoLocale', 'rispostaPreregistrazione']);
        $this->autorizza($request, $evento, $iscrizione);

        $corso    = $iscrizione->corso;
        $readonly = $iscrizione->rispostaPreregistrazione !== null;

        $socioEvento    = SocioEvento::where('partecipante_id', $iscrizione->partecipante_id)
            ->where('evento_id', $corso->evento_id)
            ->orderBy('id')
            ->first();
        $rispostaCheckin = $socioEvento?->rispostaCheckin;

        $regioneId             = $evento->comitato_regionale_id;
        $template              = PrivacyTemplate::risolvi('informativa_dati', $regioneId);
        $privacyInformativa    = $evento->privacy_override_informativa    ?? $template?->testo;
        $privacyInformativaPdf = $evento->privacy_override_informativa_pdf ?? $template?->pdf_path;
        $privacyVersione       = $template?->versione ?? 1;

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

        $presenze = $readonly
            ? Presenza::where('evento_id', $evento->id)
                ->where('iscrizione_id', $iscrizione->id)
                ->orderBy('data')->orderBy('tipo')
                ->get()
            : collect();

        return view('app.form-corsista', [
            'evento'             => $evento,
            'iscrizione'         => $iscrizione,
            'corso'              => $corso,
            'socio'              => $iscrizione->partecipante,
            'sezioni'            => $this->handler->sezioniAttive($iscrizione),
            'readonly'           => $readonly,
            'risposta'           => $iscrizione->rispostaPreregistrazione,
            'rispostaCheckin'    => $rispostaCheckin,
            'richiedeNullaosta'  => $this->handler->richiedeNullaosta($iscrizione),
            'emailDaVerificare'  => $this->handler->emailDaVerificare($this->sessionKey($iscrizione)),
            'allergeniList'      => OpzioneAlimentare::allergeni(),
            'intolleranzeList'   => OpzioneAlimentare::intolleranze(),
            'dieteList'          => OpzioneAlimentare::diete(),
            'esclusioniList'     => OpzioneAlimentare::esclusioni(),
            'comitatiRegionali'  => $comitatiRegionali,
            'comitatiLocaliJson' => $comitatiLocaliJson,
            'privacyInformativa'    => $privacyInformativa,
            'privacyInformativaPdf' => $privacyInformativaPdf,
            'privacyVersione'       => $privacyVersione,
            'presenze'              => $presenze,
        ]);
    }

    public function store(Request $request, Evento $evento, Iscrizione $iscrizione)
    {
        $iscrizione->load(['corso.evento', 'partecipante', 'rispostaPreregistrazione']);
        $this->autorizza($request, $evento, $iscrizione);

        if ($iscrizione->rispostaPreregistrazione !== null) {
            return redirect()->route('app.evento.form.corsista', [$evento, $iscrizione]);
        }

        $validated = $request->validate(
            $this->handler->regole($iscrizione, $request),
            $this->handler->messaggi()
        );

        $risultato = $this->handler->salva($iscrizione, $validated, $request, fonte: 'pwa');

        $this->handler->inviaVerificaEmail($risultato, $this->sessionKey($iscrizione));

        return redirect()->route('app.evento.form.corsista', [$evento, $iscrizione])
            ->with('success', 'Preregistrazione inviata con successo.');
    }

    /**
     * Sostituzione documento (ricevuta / nullaosta) dal ramo readonly del form PWA, finché
     * non verificato. Auth: sessione app_socio_id via autorizza() — solo il proprio form.
     */
    public function sostituisciDocumento(Request $request, Evento $evento, Iscrizione $iscrizione, string $slot)
    {
        $iscrizione->load(['corso.evento', 'partecipante', 'rispostaPreregistrazione']);
        $this->autorizza($request, $evento, $iscrizione);

        $request->validate($this->handler->regoleDocumento($slot));

        $esito = $this->handler->sostituisciDocumento($iscrizione, $slot, $request->file('documento'));

        $messaggi = [
            'sostituito'    => 'Documento sostituito con successo.',
            'verificato'    => 'Il documento è già stato verificato dalla segreteria: non è più modificabile.',
            'non_compilato' => 'Devi prima completare il form.',
        ];

        return redirect()->route('app.evento.form.corsista', [$evento, $iscrizione])
            ->with($esito['ok'] ? 'success' : 'doc_errore',
                $messaggi[$esito['motivo']] ?? 'Documento non previsto per questa iscrizione.');
    }

    public function verificaEmailCodice(Request $request, Evento $evento, Iscrizione $iscrizione)
    {
        $iscrizione->load('corso');
        $this->autorizza($request, $evento, $iscrizione);

        $sessionKey = $this->sessionKey($iscrizione);
        $socio      = ($socioId = session($sessionKey)) ? Socio::find($socioId) : null;

        if (! $socio) {
            return redirect()->route('app.evento.form.corsista', [$evento, $iscrizione]);
        }

        $request->validate([
            'codice' => ['required', 'string', 'size:6'],
        ], [
            'codice.size' => 'Il codice deve essere di 6 cifre.',
        ]);

        $risultato = $this->handler->verificaCodice($socio, $request->codice);

        if ($risultato['nessunaVerificaAttiva']) {
            session()->forget($sessionKey);
            return redirect()->route('app.evento.form.corsista', [$evento, $iscrizione]);
        }

        if (! $risultato['ok']) {
            return back()->withErrors(['codice' => $risultato['error']]);
        }

        session()->forget($sessionKey);

        return redirect()->route('app.evento.form.corsista', [$evento, $iscrizione])
            ->with('email_verificata', true);
    }
}
