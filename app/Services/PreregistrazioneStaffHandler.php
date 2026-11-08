<?php

namespace App\Services;

use App\Models\EmailVerifica;
use App\Models\Evento;
use App\Models\IscrizioneStaff;
use App\Models\Presenza;
use App\Models\PrivacyTemplate;
use App\Models\RispostaPreregistrazione;
use App\Models\Socio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Logica UNICA della preregistrazione staff, condivisa fra il form pubblico
 * (FormPreregistrazioneStaffController, link email) e quello PWA (App\AppFormStaffController).
 * Stesso principio di PreregistrazioneCorsistaHandler: i controller restano sottili.
 */
class PreregistrazioneStaffHandler
{
    /**
     * Slot mattina/pomeriggio: giorni evento + pomeriggio prima / mattina dopo di ogni
     * blocco (montaggio/smontaggio). 'extra' marca questi ultimi (fuori dai giorni reali):
     * lì è ammessa solo la logistica, mai la formazione.
     */
    public function slot(Evento $evento): array
    {
        $giorniReali = $evento->giorniEvento()->map(fn ($d) => $d->format('Y-m-d'))->all();

        return collect(EventoSezioniGenerator::slotDisponibilitaStaff($evento))
            ->map(fn (array $slot) => $slot + ['extra' => ! in_array($slot['data'], $giorniReali)])
            ->all();
    }

    public function sezioni(Evento $evento): array
    {
        return EventoSezioniGenerator::perGiorni($evento);
    }

    /**
     * Sostituzione del nullaosta già caricato, da form pubblico o PWA, consentita solo finché
     * non è stato verificato.
     *
     * @return array{ok: bool, motivo: string} motivo: sostituito | verificato | non_applicabile | non_compilato
     */
    public function sostituisciDocumento(IscrizioneStaff $iscrizione, string $slot, \Illuminate\Http\UploadedFile $file): array
    {
        $risposta = $iscrizione->rispostaPreregistrazione;

        if (! $risposta) {
            return ['ok' => false, 'motivo' => 'non_compilato'];
        }

        // Lo staff ha un solo documento: il nullaosta, e solo se ha indicato disponibilità Logistica.
        $hasLogistica = collect($risposta->risposte['disponibilita'] ?? [])
            ->pluck('tipo')->contains('logistica');

        if ($slot !== 'nullaosta' || ! $hasLogistica) {
            return ['ok' => false, 'motivo' => 'non_applicabile'];
        }

        $path = $file->store('nullaosta-staff/' . $iscrizione->id, 'local');

        $ok = app(SostituzioneDocumento::class)->sostituisci($risposta, 'nullaosta', $path);

        return ['ok' => $ok, 'motivo' => $ok ? 'sostituito' : 'verificato'];
    }

    public function regoleDocumento(string $slot): array
    {
        return ['documento' => ['required', 'file', 'mimes:pdf,jpg,jpeg', 'max:10240']];
    }

    public function regole(IscrizioneStaff $iscrizione, Request $request): array
    {
        $evento  = $iscrizione->evento;
        $slots   = $this->slot($evento);
        $sezioni = $this->sezioni($evento);

        $hasPasto = collect($sezioni)
            ->contains(fn ($s) => $request->boolean($s['tipo'] . '_' . $s['data']));

        // Il nullaosta serve solo a chi ha indicato almeno uno slot come Logistica.
        $hasLogistica = in_array('logistica', $request->input('disponibilita', []), true);

        $rules = [
            'comitato_regionale_id'     => ['required', 'integer', 'exists:comitati_regionali,id'],
            'comitato_locale_id'        => ['required', 'integer', 'exists:comitati_locali,id'],
            'email'                     => ['required', 'email', 'max:255'],
            'prefisso_telefono'         => ['nullable', 'string', 'max:10'],
            'telefono'                  => ['nullable', 'string', 'regex:/^[0-9\s\-]{5,20}$/'],
            'disponibilita'             => ['nullable', 'array'],
            'allergeni'                 => ['nullable', 'array'],
            'allergeni.*'               => ['string', 'max:100'],
            'diete'                     => ['nullable', 'array'],
            'diete.*'                   => ['string', 'max:100'],
            'note_extra'                => ['nullable', 'string', 'max:50'],
            'privacy_accettata'         => ['accepted'],
            'foto_video_accettata'      => ['accepted'],
            'consenso_salute_accettata' => $hasPasto ? ['accepted'] : ['nullable', 'boolean'],
            'nullaosta'                 => [$hasLogistica ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg', 'max:10240'],
        ];

        foreach ($slots as $slot) {
            $rules['disponibilita.' . $slot['chiave']] = $slot['extra']
                ? ['nullable', 'in:logistica']
                : ['nullable', 'in:formazione,logistica'];
        }
        foreach ($sezioni as $sezione) {
            $rules[$sezione['tipo'] . '_' . $sezione['data']] = ['nullable', 'boolean'];
        }

        return $rules;
    }

    public function messaggi(): array
    {
        return [
            'comitato_regionale_id.required' => 'Devi selezionare il comitato regionale.',
            'comitato_regionale_id.exists'   => 'Comitato regionale non valido.',
            'comitato_locale_id.required'    => 'Devi selezionare il comitato locale.',
            'comitato_locale_id.exists'      => 'Comitato locale non valido.',
            'email.required' => 'L\'indirizzo email è obbligatorio: serve per inviarti informazioni e comunicazioni relative all\'evento.',
            'privacy_accettata.accepted'         => 'Devi accettare l\'informativa sulla privacy per procedere.',
            'consenso_salute_accettata.accepted' => 'Hai selezionato dei pasti: devi prestare il consenso al trattamento dei dati sulla salute.',
            'foto_video_accettata.accepted'      => 'Devi accettare il consenso a foto e video per procedere.',
            'nullaosta.required' => 'Hai indicato disponibilità come Logistica: devi allegare il nullaosta del Presidente del tuo Comitato Locale.',
            'nullaosta.mimes'    => 'Il nullaosta deve essere un PDF o un JPG.',
            'nullaosta.max'      => 'Il nullaosta non può superare i 10 MB.',
        ];
    }

    /**
     * @param string $fonte 'pubblico' | 'pwa'
     * @return array{socio: Socio, emailCambiata: bool, nuovaEmail: string}
     */
    public function salva(IscrizioneStaff $iscrizione, array $validated, Request $request, string $fonte): array
    {
        $evento  = $iscrizione->evento;
        $sezioni = $this->sezioni($evento);
        $socio   = $iscrizione->partecipante;

        $consensoSalute = $request->boolean('consenso_salute_accettata');

        $nuovoPrefisso = $request->input('prefisso_telefono', '+39');
        $nuovoTelefono = $request->input('telefono')
            ? preg_replace('/[\s\-]/', '', $request->input('telefono'))
            : null;
        $nuovaEmail = strtolower(trim($validated['email']));

        $emailCambiata = $nuovaEmail !== $socio->email;

        $aggiornaAnagrafica = [
            'comitato_regionale_id' => $validated['comitato_regionale_id'],
            'comitato_locale_id'    => $validated['comitato_locale_id'],
        ];
        if ($nuovoTelefono && (
            $nuovoTelefono !== $socio->telefono ||
            $nuovoPrefisso !== ($socio->prefisso_telefono ?? '+39')
        )) {
            $aggiornaAnagrafica['telefono']          = $nuovoTelefono;
            $aggiornaAnagrafica['prefisso_telefono'] = $nuovoPrefisso;
        }
        if ($emailCambiata) {
            $aggiornaAnagrafica['email_in_verifica'] = $nuovaEmail;
        } elseif (blank($socio->email)) {
            $aggiornaAnagrafica['email'] = $nuovaEmail;
        }

        // Disponibilità operative: una sola tipologia per slot (radio lato form).
        $disponibilita = collect($validated['disponibilita'] ?? [])
            ->map(function (?string $tipo, string $chiave) {
                if (! $tipo) return null;
                [$data, $porzione] = [substr($chiave, 0, 10), substr($chiave, 11)];
                return ['data' => $data, 'porzione' => $porzione, 'tipo' => $tipo];
            })
            ->filter()
            ->values()
            ->all();

        $giorniRiepilogo = collect($disponibilita)->groupBy('data')->keys()->values()->toArray();

        $risposte = ['disponibilita' => $disponibilita];
        if ($nuovaEmail)    $risposte['email']             = $nuovaEmail;
        if ($nuovoTelefono) $risposte['prefisso_telefono'] = $nuovoPrefisso;
        if ($nuovoTelefono) $risposte['telefono']          = $nuovoTelefono;

        if ($consensoSalute) {
            $allergeni = $request->input('allergeni', []);
            $diete     = $request->input('diete', []);
            $noteExtra = $this->sanitizzaNoteExtra($request->input('note_extra'));

            $risposte['allergeni']  = $allergeni;
            $risposte['diete']      = $diete;
            $risposte['note_extra'] = $noteExtra;

            $aggiornaAnagrafica['allergeni']       = $allergeni;
            $aggiornaAnagrafica['diete']           = $diete;
            $aggiornaAnagrafica['note_alimentari'] = $noteExtra;
        }

        foreach ($sezioni as $sezione) {
            $chiave            = $sezione['tipo'] . '_' . $sezione['data'];
            $risposte[$chiave] = $request->boolean($chiave);
        }

        // Upload FUORI dalla transazione
        $nullaostaPath = $request->hasFile('nullaosta')
            ? $request->file('nullaosta')->store('nullaosta-staff/' . $iscrizione->id, 'local')
            : null;

        DB::transaction(function () use (
            $iscrizione, $socio, $aggiornaAnagrafica, $giorniRiepilogo, $risposte,
            $consensoSalute, $request, $sezioni, $nullaostaPath, $evento, $fonte
        ) {
            $iscrizione->update(['giorni_disponibili' => $giorniRiepilogo]);
            $socio->update($aggiornaAnagrafica);

            RispostaPreregistrazione::updateOrCreate(
                [
                    'iscrizioneable_type' => IscrizioneStaff::class,
                    'iscrizioneable_id'   => $iscrizione->id,
                ],
                [
                    'risposte'                    => $risposte,
                    'privacy_accettata'           => true,
                    'foto_video_accettata'        => $request->boolean('foto_video_accettata'),
                    'consenso_salute_accettata'   => $consensoSalute,
                    'privacy_versione_accettata'  => PrivacyTemplate::risolvi('informativa_dati', $evento->comitato_regionale_id)?->versione ?? 1,
                    'compiled_at'                 => now(),
                    'nullaosta_path'              => $nullaostaPath,
                    'fonte_compilazione'          => $fonte,
                ]
            );

            Presenza::where('evento_id', $iscrizione->evento_id)
                ->where('partecipante_id', $iscrizione->partecipante_id)
                ->whereNull('iscrizione_id')
                ->where('fonte', 'form')
                ->delete();

            foreach ($sezioni as $sezione) {
                $chiave = $sezione['tipo'] . '_' . $sezione['data'];
                if (! $request->boolean($chiave)) continue;

                // Ricerca senza iscrizione_id: il vincolo univoco reale su presenze_evento è
                // (evento_id, partecipante_id, tipo, data).
                Presenza::firstOrCreate(
                    [
                        'evento_id'       => $iscrizione->evento_id,
                        'partecipante_id' => $iscrizione->partecipante_id,
                        'tipo'            => $sezione['tipo'],
                        'data'            => $sezione['data'],
                    ],
                    ['iscrizione_id' => null, 'fonte' => 'form']
                );
            }

            $iscrizione->update(['stato' => 'form_completato']);
        });

        return [
            'socio'         => $socio,
            'emailCambiata' => $emailCambiata,
            'nuovaEmail'    => $nuovaEmail,
        ];
    }

    public function inviaVerificaEmail(array $risultatoSalva, string $sessionKey): void
    {
        /** @var Socio $socio */
        $socio         = $risultatoSalva['socio'];
        $emailCambiata = $risultatoSalva['emailCambiata'];

        EmailVerifica::inviaSeNecessario($socio, $emailCambiata ? $risultatoSalva['nuovaEmail'] : null);

        if (! $socio->fresh()->email_verificata_at || $emailCambiata) {
            session([$sessionKey => $socio->id]);
        }
    }

    public function emailDaVerificare(string $sessionKey): ?string
    {
        $socioId = session($sessionKey);
        if (! $socioId) {
            return null;
        }

        $socio = Socio::find($socioId);
        if ($socio && (! $socio->email_verificata_at || filled($socio->email_in_verifica))) {
            return $socio->email_in_verifica ?? $socio->email;
        }

        session()->forget($sessionKey);
        return null;
    }

    public function verificaCodice(Socio $socio, string $codice): array
    {
        return EmailVerificaCodiceHandler::verifica($socio, 'socio_id', $codice);
    }

    private function sanitizzaNoteExtra(?string $testo): ?string
    {
        if (! $testo || trim($testo) === '') return null;

        $t = trim($testo);

        $pattern = '/^\s*(nessun[aeo]?|niente|nientissimo|nulla|nullo|nulle|niente\s+di\s+niente|'
            . 'non\s+s[oaò]|non\s+sapre[io]|non\s+ho|no|n\/a|nop|nope|-+|\.+)\s*[!?.]*\s*$/iu';

        if (preg_match($pattern, $t)) return null;

        return $t;
    }
}
