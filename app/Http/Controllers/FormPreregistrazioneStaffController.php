<?php

namespace App\Http\Controllers;

use App\Models\ComitatoLocale;
use App\Models\ComitatoRegionale;
use App\Models\IscrizioneStaff;
use App\Models\OpzioneAlimentare;
use App\Models\PrivacyTemplate;
use App\Models\Socio;
use App\Services\PreregistrazioneStaffHandler;
use App\Services\PrivacyPlaceholderResolver;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Form di preregistrazione staff via link email (`/form-staff/{token}`).
 *
 * Logica in App\Services\PreregistrazioneStaffHandler, condivisa con il gemello PWA
 * App\Http\Controllers\App\AppFormStaffController.
 */
class FormPreregistrazioneStaffController extends Controller
{
    public function __construct(private readonly PreregistrazioneStaffHandler $handler)
    {
    }

    public function show(string $token): View|\Illuminate\Http\RedirectResponse
    {
        $iscrizione = IscrizioneStaff::with(['evento', 'partecipante.comitatoRegionale', 'partecipante.comitatoLocale', 'rispostaPreregistrazione', 'candidaturaStaff'])
            ->where('token', $token)
            ->firstOrFail();

        $evento = $iscrizione->evento;

        if (! $evento->form_staff_aperto_at && ! request()->boolean('preview')) {
            return view('form.non-disponibile-staff', compact('evento', 'iscrizione'));
        }

        $readonly = $iscrizione->rispostaPreregistrazione !== null && ! request()->boolean('preview');

        if ($readonly) {
            return redirect()->route('form-staff.confermata', $token);
        }

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

        // Prefill disponibilità dalla candidatura online se questo staff arriva da lì
        // (CandidaturaStaff usa 'docente', qui 'formazione' — mappatura al volo).
        $dispPrefill = [];
        if (! $iscrizione->rispostaPreregistrazione && $iscrizione->candidaturaStaff) {
            $dispPrefill = collect($iscrizione->candidaturaStaff->disponibilita ?? [])
                ->map(fn (array $d) => [
                    'data'     => $d['data'],
                    'porzione' => $d['porzione'],
                    'tipo'     => $d['tipo'] === 'docente' ? 'formazione' : 'logistica',
                ])->all();
        }

        $comitatiRegionali  = ComitatoRegionale::attivo()->orderBy('nome')->get(['id', 'nome']);
        $comitatiLocaliJson = ComitatoLocale::attivo()
            ->orderBy('nome')
            ->get(['id', 'comitato_regionale_id', 'nome'])
            ->groupBy('comitato_regionale_id')
            ->map(fn ($g) => $g->values())
            ->toJson();

        return view('form.preregistrazione-staff', [
            'iscrizione'            => $iscrizione,
            'evento'                => $evento,
            'partecipante'          => $iscrizione->partecipante,
            'slots'                 => $this->handler->slot($evento),
            'sezioni'               => collect($this->handler->sezioni($evento)),
            'dispPrefill'           => $dispPrefill,
            'allergeniList'         => OpzioneAlimentare::allergeni(),
            'intolleranzeList'      => OpzioneAlimentare::intolleranze(),
            'dieteList'             => OpzioneAlimentare::diete(),
            'esclusioniList'        => OpzioneAlimentare::esclusioni(),
            'giaSalvata'            => $iscrizione->rispostaPreregistrazione !== null,
            'risposta'              => $iscrizione->rispostaPreregistrazione,
            'readonly'              => $readonly,
            'preview'               => request()->boolean('preview'),
            'privacyInformativa'    => $privacyInformativa,
            'privacyInformativaPdf' => $privacyInformativaPdf,
            'privacyVersione'       => $privacyVersione,
            'comitatiRegionali'     => $comitatiRegionali,
            'comitatiLocaliJson'    => $comitatiLocaliJson,
        ]);
    }

    public function store(Request $request, string $token): \Illuminate\Http\RedirectResponse
    {
        $iscrizione = IscrizioneStaff::with(['evento', 'partecipante', 'rispostaPreregistrazione'])
            ->where('token', $token)
            ->firstOrFail();

        if ($request->boolean('preview')) {
            return back()->with('preview_info', 'Anteprima — il form non è stato salvato.');
        }

        if ($iscrizione->rispostaPreregistrazione !== null) {
            return redirect()->route('form-staff.show', $token);
        }

        $validated = $request->validate(
            $this->handler->regole($iscrizione, $request),
            $this->handler->messaggi()
        );

        $risultato = $this->handler->salva($iscrizione, $validated, $request, fonte: 'pubblico');

        $this->handler->inviaVerificaEmail($risultato, "form-staff.{$token}.socio_id_verifica");

        return redirect()->route('form-staff.confermata', $token);
    }

    public function confermata(string $token): View
    {
        $iscrizione = IscrizioneStaff::with(['evento', 'partecipante', 'rispostaPreregistrazione'])
            ->where('token', $token)
            ->firstOrFail();

        return view('form.confermata-staff', [
            'iscrizione'        => $iscrizione,
            'evento'            => $iscrizione->evento,
            'partecipante'      => $iscrizione->partecipante,
            'risposta'          => $iscrizione->rispostaPreregistrazione,
            'emailDaVerificare' => $this->handler->emailDaVerificare("form-staff.{$token}.socio_id_verifica"),
        ]);
    }

    /**
     * Sostituzione del nullaosta caricato per errore, finché non verificato. Auth: token UUID.
     */
    public function sostituisciDocumento(Request $request, string $token, string $slot): \Illuminate\Http\RedirectResponse
    {
        $iscrizione = IscrizioneStaff::with(['evento', 'partecipante', 'rispostaPreregistrazione'])
            ->where('token', $token)
            ->firstOrFail();

        $request->validate($this->handler->regoleDocumento($slot));

        $esito = $this->handler->sostituisciDocumento($iscrizione, $slot, $request->file('documento'));

        $messaggi = [
            'sostituito'    => 'Documento sostituito con successo.',
            'verificato'    => 'Il documento è già stato verificato dalla segreteria: non è più modificabile.',
            'non_compilato' => 'Devi prima completare il form.',
        ];

        return redirect()->route('form-staff.confermata', $token)
            ->with($esito['ok'] ? 'doc_sostituito' : 'doc_errore',
                $messaggi[$esito['motivo']] ?? 'Documento non previsto per questa iscrizione.');
    }

    public function verificaEmailCodice(Request $request, string $token): \Illuminate\Http\RedirectResponse
    {
        $sessionKey = "form-staff.{$token}.socio_id_verifica";
        $socio      = ($socioId = session($sessionKey)) ? Socio::find($socioId) : null;

        if (! $socio) {
            return redirect()->route('form-staff.confermata', $token);
        }

        $request->validate([
            'codice' => ['required', 'string', 'size:6'],
        ], [
            'codice.size' => 'Il codice deve essere di 6 cifre.',
        ]);

        $risultato = $this->handler->verificaCodice($socio, $request->codice);

        if ($risultato['nessunaVerificaAttiva']) {
            session()->forget($sessionKey);
            return redirect()->route('form-staff.confermata', $token);
        }

        if (! $risultato['ok']) {
            return back()->withErrors(['codice' => $risultato['error']]);
        }

        session()->forget($sessionKey);

        return redirect()->route('form-staff.confermata', $token)->with('email_verificata', true);
    }
}
