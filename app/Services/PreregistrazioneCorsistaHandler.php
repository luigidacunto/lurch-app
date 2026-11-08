<?php

namespace App\Services;

use App\Models\EmailVerifica;
use App\Models\Iscrizione;
use App\Models\Presenza;
use App\Models\PrivacyTemplate;
use App\Models\RispostaPreregistrazione;
use App\Models\Socio;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Logica UNICA della preregistrazione corsista, condivisa fra il form pubblico
 * (FormPreregistrazioneController, link email) e quello PWA (App\AppFormCorsistController).
 *
 * I due controller si limitano a: risolvere l'Iscrizione, controllare l'autorizzazione
 * (token vs sessione app_socio_id), gestire il redirect. Tutto il resto — regole di
 * validazione, visibilità condizionale dei documenti, cosa viene persistito, verifica
 * email — vive qui, così le due copie non possono divergere.
 */
class PreregistrazioneCorsistaHandler
{
    /** Sezioni pasto attive del corso di questa iscrizione. */
    public function sezioniAttive(Iscrizione $iscrizione): Collection
    {
        return collect($iscrizione->corso->form_sections ?? [])
            ->reject(fn ($s) => ($s['attivo'] ?? true) === false)
            ->values();
    }

    /**
     * Il nullaosta del Presidente del Comitato Locale serve se NON siamo certi che il
     * partecipante appartenga alla stessa regione organizzatrice del corso.
     */
    public function richiedeNullaosta(Iscrizione $iscrizione): bool
    {
        $partecipanteRegioneId = $iscrizione->partecipante->comitato_regionale_id;
        $regioneEvento         = $iscrizione->corso->evento->comitato_regionale_id;

        return $partecipanteRegioneId === null || $partecipanteRegioneId !== $regioneEvento;
    }

    /**
     * Sostituzione di un documento già caricato (ricevuta o nullaosta), da form pubblico o PWA,
     * consentita solo finché il documento non è stato verificato da un operatore. Punto unico
     * condiviso.
     *
     * Vale anche per il PRIMO caricamento del documento aggiuntivo quando il corso lo richiede
     * dopo che l'iscritto ha già compilato il form (il file non c'è ancora: sostituisci() lo crea).
     *
     * @param string $slot 'ricevuta' | 'nullaosta' | 'documento_aggiuntivo'
     * @return array{ok: bool, motivo: string} motivo: sostituito | verificato | non_applicabile | non_compilato
     */
    public function sostituisciDocumento(Iscrizione $iscrizione, string $slot, \Illuminate\Http\UploadedFile $file): array
    {
        $risposta = $iscrizione->rispostaPreregistrazione;

        if (! $risposta) {
            return ['ok' => false, 'motivo' => 'non_compilato'];
        }

        $applicabile = match ($slot) {
            'ricevuta'  => (bool) $iscrizione->corso->pagamento_previsto,
            'nullaosta' => $this->richiedeNullaosta($iscrizione),
            'documento_aggiuntivo' => (bool) $iscrizione->corso->richiede_documento_aggiuntivo,
            default     => false,
        };

        if (! $applicabile) {
            return ['ok' => false, 'motivo' => 'non_applicabile'];
        }

        $dir  = match ($slot) {
            'ricevuta'  => 'ricevute/',
            'nullaosta' => 'nullaosta/',
            default     => 'documenti-aggiuntivi/',
        } . $iscrizione->id;
        $path = $file->store($dir, 'local');

        $ok = app(SostituzioneDocumento::class)->sostituisci($risposta, $slot, $path);

        return ['ok' => $ok, 'motivo' => $ok ? 'sostituito' : 'verificato'];
    }

    /** Regola di validazione del singolo file per l'endpoint di sostituzione. */
    public function regoleDocumento(string $slot): array
    {
        return [
            'documento' => $slot === 'ricevuta'
                ? ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240']
                : ['required', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    /** Regole di validazione per il POST del form. */
    public function regole(Iscrizione $iscrizione, Request $request): array
    {
        $corso    = $iscrizione->corso;
        $risposta = $iscrizione->rispostaPreregistrazione;

        $hasPasto = $this->sezioniAttive($iscrizione)
            ->contains(fn ($s) => $request->boolean($s['tipo'] . '_' . $s['data']));

        $esisteRicevuta   = $risposta?->ricevuta_path !== null;
        $esisteNullaosta  = $risposta?->nullaosta_path !== null;
        $richiedeNullaosta = $this->richiedeNullaosta($iscrizione);
        $esisteDocAgg     = $risposta?->documento_aggiuntivo_path !== null;

        return [
            'comitato_regionale_id'     => ['required', 'integer', 'exists:comitati_regionali,id'],
            'comitato_locale_id'        => ['required', 'integer', 'exists:comitati_locali,id'],
            'email'                     => ['required', 'email', 'max:255'],
            'prefisso_telefono'         => ['nullable', 'string', 'max:10'],
            'telefono'                  => ['nullable', 'string', 'regex:/^[0-9\s\-]{5,20}$/'],
            'privacy_accettata'         => ['accepted'],
            'consenso_gaia_accettata'   => ['accepted'],
            'consenso_salute_accettata' => $hasPasto ? ['accepted'] : ['nullable', 'boolean'],
            'foto_video_accettata'      => ['accepted'],
            'allergeni'                 => ['nullable', 'array'],
            'allergeni.*'               => ['string', 'max:100'],
            'diete'                     => ['nullable', 'array'],
            'diete.*'                   => ['string', 'max:100'],
            'note_extra'                => ['nullable', 'string', 'max:50'],
            'data_pagamento' => $corso->pagamento_previsto
                ? ['required', 'date', 'before_or_equal:today']
                : ['nullable', 'date', 'before_or_equal:today'],
            'ricevuta'   => ($corso->pagamento_previsto && ! $esisteRicevuta)
                ? ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240']
                : ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'nullaosta'  => ($richiedeNullaosta && ! $esisteNullaosta)
                ? ['required', 'file', 'mimes:pdf', 'max:10240']
                : ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'documento_aggiuntivo' => ($corso->richiede_documento_aggiuntivo && ! $esisteDocAgg)
                ? ['required', 'file', 'mimes:pdf', 'max:10240']
                : ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ];
    }

    public function messaggi(): array
    {
        return [
            'comitato_regionale_id.required' => 'Devi selezionare il comitato regionale.',
            'comitato_regionale_id.exists'   => 'Comitato regionale non valido.',
            'comitato_locale_id.required'    => 'Devi selezionare il comitato locale.',
            'comitato_locale_id.exists'      => 'Comitato locale non valido.',
            'email.required' => 'L\'indirizzo email è obbligatorio: serve per inviarti informazioni e comunicazioni relative all\'evento/corso.',
            'privacy_accettata.accepted'         => 'Devi accettare l\'informativa sulla privacy per procedere.',
            'consenso_gaia_accettata.accepted'   => 'Devi accettare la consultazione delle tue qualifiche da GAIA per procedere.',
            'consenso_salute_accettata.accepted' => 'Hai selezionato dei pasti: devi prestare il consenso al trattamento dei dati sulla salute.',
            'foto_video_accettata.accepted'      => 'Devi accettare il consenso a foto e video per procedere.',
            'data_pagamento.required'        => 'Devi inserire la data del pagamento.',
            'data_pagamento.date'            => 'La data del pagamento non è valida.',
            'data_pagamento.before_or_equal' => 'La data del pagamento non può essere nel futuro.',
            'ricevuta.required'  => 'Devi allegare la ricevuta di pagamento per procedere.',
            'ricevuta.mimes'     => 'La ricevuta deve essere un\'immagine (JPG, PNG) o un PDF.',
            'ricevuta.max'       => 'La ricevuta non può superare i 10 MB.',
            'ricevuta.uploaded'  => 'Il file della ricevuta è troppo grande o non è stato caricato correttamente. Verifica che non superi i 10 MB.',
            'nullaosta.required' => 'Sei iscritto a un comitato di una regione diversa: devi allegare il nullaosta del Presidente del tuo Comitato Locale (PDF).',
            'nullaosta.mimes'    => 'Il nullaosta deve essere un PDF.',
            'nullaosta.max'      => 'Il nullaosta non può superare i 10 MB.',
            'nullaosta.uploaded' => 'Il file del nullaosta è troppo grande o non è stato caricato correttamente. Verifica che non superi i 10 MB.',
            'documento_aggiuntivo.required' => 'Devi allegare il documento richiesto (PDF) per procedere.',
            'documento_aggiuntivo.mimes'    => 'Il documento richiesto deve essere un PDF.',
            'documento_aggiuntivo.max'      => 'Il documento richiesto non può superare i 10 MB.',
            'documento_aggiuntivo.uploaded' => 'Il file del documento è troppo grande o non è stato caricato correttamente. Verifica che non superi i 10 MB.',
        ];
    }

    /**
     * Persiste la preregistrazione. Va chiamato DOPO $request->validate(regole(), messaggi()).
     *
     * @param string $fonte 'pubblico' | 'pwa'
     * @return array{socio: Socio, emailCambiata: bool, nuovaEmail: string}
     */
    public function salva(Iscrizione $iscrizione, array $validated, Request $request, string $fonte): array
    {
        $corso         = $iscrizione->corso;
        $sezioniAttive = $this->sezioniAttive($iscrizione);
        $socio         = $iscrizione->partecipante;

        $consensoSalute = $request->boolean('consenso_salute_accettata');

        $nuovoPrefisso = $request->input('prefisso_telefono', '+39');
        $nuovoTelefono = $request->input('telefono')
            ? preg_replace('/[\s\-]/', '', $request->input('telefono'))
            : null;
        $nuovaEmail = strtolower(trim($validated['email']));

        // L'email non sovrascrive mai direttamente il contatto esistente se cambiata: va in
        // "pending" (email_in_verifica) finché non viene confermata via link/codice.
        $emailCambiata = $nuovaEmail !== $socio->email;

        $aggiornaAnagrafica = [
            'comitato_regionale_id'   => $validated['comitato_regionale_id'],
            'comitato_locale_id'      => $validated['comitato_locale_id'],
            'consenso_gaia_accettata' => true,
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

        // Risposte alle sezioni pasto + contatti
        $risposte = [];
        foreach ($sezioniAttive as $sezione) {
            $chiave            = $sezione['tipo'] . '_' . $sezione['data'];
            $risposte[$chiave] = $request->boolean($chiave);
        }
        if ($nuovaEmail)    $risposte['email']             = $nuovaEmail;
        if ($nuovoTelefono) $risposte['prefisso_telefono'] = $nuovoPrefisso;
        if ($nuovoTelefono) $risposte['telefono']          = $nuovoTelefono;

        // Dati salute solo se consenso prestato
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

        // Upload file FUORI dalla transazione
        $ricevutaPath = $iscrizione->rispostaPreregistrazione?->ricevuta_path;
        if ($request->hasFile('ricevuta')) {
            if ($ricevutaPath) {
                Storage::disk('local')->delete($ricevutaPath);
            }
            $ricevutaPath = $request->file('ricevuta')->store('ricevute/' . $iscrizione->id, 'local');
        }

        $nullaostaPath = $iscrizione->rispostaPreregistrazione?->nullaosta_path;
        if ($request->hasFile('nullaosta')) {
            if ($nullaostaPath) {
                Storage::disk('local')->delete($nullaostaPath);
            }
            $nullaostaPath = $request->file('nullaosta')->store('nullaosta/' . $iscrizione->id, 'local');
        }

        $docAggPath = $iscrizione->rispostaPreregistrazione?->documento_aggiuntivo_path;
        if ($request->hasFile('documento_aggiuntivo')) {
            if ($docAggPath) {
                Storage::disk('local')->delete($docAggPath);
            }
            $docAggPath = $request->file('documento_aggiuntivo')->store('documenti-aggiuntivi/' . $iscrizione->id, 'local');
        }

        $updateData = [
            'risposte'                  => $risposte,
            'privacy_accettata'           => true,
            'consenso_gaia_accettata'     => true,
            'consenso_salute_accettata'   => $consensoSalute,
            'foto_video_accettata'        => $request->boolean('foto_video_accettata'),
            'privacy_versione_accettata'  => PrivacyTemplate::risolvi('informativa_dati', $corso->evento->comitato_regionale_id)?->versione ?? 1,
            'compiled_at'                 => now(),
            'fonte_compilazione'          => $fonte,
        ];
        if ($ricevutaPath !== null) {
            $updateData['ricevuta_path'] = $ricevutaPath;
        }
        if ($nullaostaPath !== null) {
            $updateData['nullaosta_path'] = $nullaostaPath;
        }
        if ($docAggPath !== null) {
            $updateData['documento_aggiuntivo_path'] = $docAggPath;
        }
        if ($corso->pagamento_previsto && ($validated['data_pagamento'] ?? null)) {
            $updateData['data_pagamento'] = $validated['data_pagamento'];
        }

        DB::transaction(function () use ($iscrizione, $socio, $aggiornaAnagrafica, $updateData, $sezioniAttive, $request, $corso) {
            $socio->update($aggiornaAnagrafica);

            RispostaPreregistrazione::updateOrCreate(
                [
                    'iscrizioneable_type' => Iscrizione::class,
                    'iscrizioneable_id'   => $iscrizione->id,
                ],
                $updateData
            );

            Presenza::where('iscrizione_id', $iscrizione->id)->where('fonte', 'form')->delete();

            foreach ($sezioniAttive as $sezione) {
                $chiave = $sezione['tipo'] . '_' . $sezione['data'];
                if (! $request->boolean($chiave)) continue;

                Presenza::updateOrCreate(
                    [
                        'evento_id'       => $corso->evento_id,
                        'partecipante_id' => $iscrizione->partecipante_id,
                        'tipo'            => $sezione['tipo'],
                        'data'            => $sezione['data'],
                    ],
                    ['iscrizione_id' => $iscrizione->id, 'fonte' => 'form']
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

    /**
     * Invio email di verifica + memorizzazione in sessione — da chiamare DOPO salva(),
     * fuori da qualsiasi transazione. $sessionKey differisce fra pubblico
     * ("form.{token}.socio_id_verifica") e PWA ("app.form-corsista.{id}.socio_id_verifica").
     */
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

    /** Email ancora da verificare per la vista di conferma, o null. */
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

    /**
     * Verifica il codice a 6 cifre. Ritorna l'array di EmailVerificaCodiceHandler::verifica
     * ('ok', 'error', 'nessunaVerificaAttiva'). Il chiamante gestisce sessione e redirect.
     */
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
