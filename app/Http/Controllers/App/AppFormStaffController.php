<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ComitatoLocale;
use App\Models\ComitatoRegionale;
use App\Models\Evento;
use App\Models\IscrizioneStaff;
use App\Models\OpzioneAlimentare;
use App\Models\Presenza;
use App\Models\PrivacyTemplate;
use App\Models\Socio;
use App\Models\SocioEvento;
use App\Services\PreregistrazioneStaffHandler;
use App\Services\PrivacyPlaceholderResolver;
use Illuminate\Http\Request;

/**
 * Gemello PWA del form di preregistrazione staff (`/app/evento/{evento}/form/staff/{iscrizioneStaff}`).
 * Logica in App\Services\PreregistrazioneStaffHandler, condivisa col form pubblico.
 */
class AppFormStaffController extends Controller
{
    public function __construct(private readonly PreregistrazioneStaffHandler $handler)
    {
    }

    private function sessionKey(IscrizioneStaff $iscrizioneStaff): string
    {
        return "app.form-staff.{$iscrizioneStaff->id}.socio_id_verifica";
    }

    private function autorizza(Request $request, Evento $evento, IscrizioneStaff $iscrizioneStaff): void
    {
        $socioId = $request->session()->get('app_socio_id');

        if ($iscrizioneStaff->partecipante_id !== $socioId || $iscrizioneStaff->evento_id !== $evento->id) {
            abort(403);
        }
    }

    public function show(Request $request, Evento $evento, IscrizioneStaff $iscrizioneStaff)
    {
        $iscrizioneStaff->load(['evento', 'partecipante.comitatoRegionale', 'partecipante.comitatoLocale', 'rispostaPreregistrazione']);
        $this->autorizza($request, $evento, $iscrizioneStaff);

        $readonly = $iscrizioneStaff->rispostaPreregistrazione !== null;
        $risposta = $iscrizioneStaff->rispostaPreregistrazione;

        $socioEvento     = SocioEvento::where('partecipante_id', $iscrizioneStaff->partecipante_id)
            ->where('evento_id', $iscrizioneStaff->evento_id)
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
                ->where('partecipante_id', $iscrizioneStaff->partecipante_id)
                ->whereNull('iscrizione_id')
                ->orderBy('data')->orderBy('tipo')
                ->get()
            : collect();

        return view('app.form-staff', [
            'evento'             => $evento,
            'iscrizioneStaff'    => $iscrizioneStaff,
            'socio'              => $iscrizioneStaff->partecipante,
            'slots'              => $this->handler->slot($evento),
            'sezioni'            => collect($this->handler->sezioni($evento)),
            'allergeniList'      => OpzioneAlimentare::allergeni(),
            'intolleranzeList'   => OpzioneAlimentare::intolleranze(),
            'dieteList'          => OpzioneAlimentare::diete(),
            'esclusioniList'     => OpzioneAlimentare::esclusioni(),
            'readonly'           => $readonly,
            'risposta'           => $risposta,
            'rispostaCheckin'    => $rispostaCheckin,
            'emailDaVerificare'  => $this->handler->emailDaVerificare($this->sessionKey($iscrizioneStaff)),
            'comitatiRegionali'  => $comitatiRegionali,
            'comitatiLocaliJson' => $comitatiLocaliJson,
            'privacyInformativa'    => $privacyInformativa,
            'privacyInformativaPdf' => $privacyInformativaPdf,
            'privacyVersione'       => $privacyVersione,
            'presenze'              => $presenze,
        ]);
    }

    public function store(Request $request, Evento $evento, IscrizioneStaff $iscrizioneStaff)
    {
        $iscrizioneStaff->load(['evento', 'partecipante', 'rispostaPreregistrazione']);
        $this->autorizza($request, $evento, $iscrizioneStaff);

        if ($iscrizioneStaff->rispostaPreregistrazione !== null) {
            return redirect()->route('app.evento.form.staff', [$evento, $iscrizioneStaff]);
        }

        $validated = $request->validate(
            $this->handler->regole($iscrizioneStaff, $request),
            $this->handler->messaggi()
        );

        $risultato = $this->handler->salva($iscrizioneStaff, $validated, $request, fonte: 'pwa');

        $this->handler->inviaVerificaEmail($risultato, $this->sessionKey($iscrizioneStaff));

        return redirect()->route('app.evento.form.staff', [$evento, $iscrizioneStaff])
            ->with('success', 'Preregistrazione staff inviata con successo.');
    }

    /**
     * Sostituzione del nullaosta dal ramo readonly del form PWA, finché non verificato.
     * Auth: sessione app_socio_id via autorizza().
     */
    public function sostituisciDocumento(Request $request, Evento $evento, IscrizioneStaff $iscrizioneStaff, string $slot)
    {
        $iscrizioneStaff->load(['evento', 'partecipante', 'rispostaPreregistrazione']);
        $this->autorizza($request, $evento, $iscrizioneStaff);

        $request->validate($this->handler->regoleDocumento($slot));

        $esito = $this->handler->sostituisciDocumento($iscrizioneStaff, $slot, $request->file('documento'));

        $messaggi = [
            'sostituito'    => 'Documento sostituito con successo.',
            'verificato'    => 'Il documento è già stato verificato dalla segreteria: non è più modificabile.',
            'non_compilato' => 'Devi prima completare il form.',
        ];

        return redirect()->route('app.evento.form.staff', [$evento, $iscrizioneStaff])
            ->with($esito['ok'] ? 'success' : 'doc_errore',
                $messaggi[$esito['motivo']] ?? 'Documento non previsto per questa iscrizione.');
    }

    public function verificaEmailCodice(Request $request, Evento $evento, IscrizioneStaff $iscrizioneStaff)
    {
        $this->autorizza($request, $evento, $iscrizioneStaff);

        $sessionKey = $this->sessionKey($iscrizioneStaff);
        $socio      = ($socioId = session($sessionKey)) ? Socio::find($socioId) : null;

        if (! $socio) {
            return redirect()->route('app.evento.form.staff', [$evento, $iscrizioneStaff]);
        }

        $request->validate([
            'codice' => ['required', 'string', 'size:6'],
        ], [
            'codice.size' => 'Il codice deve essere di 6 cifre.',
        ]);

        $risultato = $this->handler->verificaCodice($socio, $request->codice);

        if ($risultato['nessunaVerificaAttiva']) {
            session()->forget($sessionKey);
            return redirect()->route('app.evento.form.staff', [$evento, $iscrizioneStaff]);
        }

        if (! $risultato['ok']) {
            return back()->withErrors(['codice' => $risultato['error']]);
        }

        session()->forget($sessionKey);

        return redirect()->route('app.evento.form.staff', [$evento, $iscrizioneStaff])
            ->with('email_verificata', true);
    }
}
