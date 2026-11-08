<?php

namespace App\Services;

use App\Models\IstruzionePagamento;
use App\Models\Iscrizione;
use App\Models\IscrizioneStaff;
use App\Models\Corso;
use App\Models\Evento;
use App\Models\Graduatoria;
use App\Models\GraduatoriaCandidato;

/**
 * Gestisce la sostituzione dei segnaposto nei template email.
 *
 * SEGNAPOSTO DISPONIBILI (nel corpo email, nell'oggetto e nelle istruzioni di pagamento)
 * ──────────────────────────────────────────────────────────────────────────────────────
 * {nome}                Nome dell'iscritto          ← partecipante.nome
 * {cognome}             Cognome dell'iscritto        ← partecipante.cognome
 * {nome_corso}          Nome del corso               ← corso.nome
 * {protocollo}          Numero di protocollo         ← corso.numero_protocollo (vuoto se assente)
 * {comitato_regionale}  Comitato regionale           ← evento.comitatoRegionale.nome
 * {comitato_locale}     Comitato locale              ← evento.comitatoLocale.nome (vuoto se assente)
 * {quota}               Quota di partecipazione      ← corso.quota_partecipazione → "€ X,XX" (vuoto se assente)
 * {link_form}           Pulsante + URL form          ← route('form.show', token) — reso come HTML
 * {scadenza}            Scadenza compilazione        ← data invio + 7 giorni in formato gg/mm/YYYY
 * {istruzioni_pagamento} Testo istruzioni bonifico   ← IstruzionePagamento::risolvi(comitato_regionale_id).testo
 *                                                       I segnaposto sopra funzionano anche dentro questo testo
 *                                                       (es. causale "Corso {protocollo} — {cognome} {nome}")
 * {referenti}           Admin regionali di riferimento ← evento.referenti (lista HTML nome + email); vuoto se nessuno
 * {sezione_referenti}   Blocco contatti referenti      ← titolo + tabella nome/email; vuoto se nessun referente assegnato
 * {sezione_pagamento}   Blocco pagamento completo    ← automatico: visibile solo se corso.pagamento_previsto = true;
 *                                                       contiene quota + istruzioni_pagamento risolte; vuoto altrimenti
 * ──────────────────────────────────────────────────────────────────────────────────────
 */
class TemplatePlaceholders
{
    /**
     * Sostituisce i segnaposto usando i dati reali di un'iscrizione.
     * $scadenza: se fornita viene usata as-is (es. per solleciti: data invio massivo + 7gg);
     *            se null default a now()+7.
     */
    public static function risolvi(string $testo, Iscrizione $iscrizione, ?string $scadenza = null): string
    {
        $corso  = $iscrizione->corso;
        $evento = $corso->evento;
        $link   = route('form.show', $iscrizione->token);

        $base = self::buildBaseValues(
            corso:             $corso,
            nome:              $iscrizione->partecipante?->nome    ?? '',
            cognome:           $iscrizione->partecipante?->cognome ?? '',
            link:              $link,
            scadenza:          $scadenza ?? now()->addDays(7)->format('d/m/Y'),
            comitatoRegionale: $evento->comitatoRegionale?->nome ?? '',
            comitatoLocale:    $evento->comitatoLocale?->nome    ?? '',
        );

        return str_replace(array_keys($base), array_values($base), $testo);
    }

    /**
     * Genera l'anteprima usando i dati del corso (per la pagina Gestione Corso).
     * Per {nome}/{cognome} usa il primo iscritto se presente, altrimenti segnaposto visivi generici.
     * $giorniScadenza: 7 per il form iniziale, 1 per il sollecito (vedi risolvi()).
     */
    public static function anteprimaPerCorso(string $testo, Corso $corso, int $giorniScadenza = 7): string
    {
        $evento          = $corso->evento;
        $primaIscrizione = $corso->iscrizioni->first();
        $partecipante    = $primaIscrizione?->partecipante;
        $link            = $primaIscrizione ? route('form.show', $primaIscrizione->token) : '#anteprima';

        $base = self::buildBaseValues(
            corso:             $corso,
            nome:              $partecipante?->nome    ?? '<em style="color:#9ca3af">Nome</em>',
            cognome:           $partecipante?->cognome ?? '<em style="color:#9ca3af">Cognome</em>',
            link:              $link,
            scadenza:          now()->addDays($giorniScadenza)->format('d/m/Y'),
            comitatoRegionale: $evento->comitatoRegionale?->nome ?? '',
            comitatoLocale:    $evento->comitatoLocale?->nome    ?? '',
        );

        return str_replace(array_keys($base), array_values($base), $testo);
    }

    /**
     * Sostituisce i segnaposto usando i dati reali di un'iscrizione staff.
     * $scadenza: se fornita viene usata as-is (es. per solleciti: data invio massivo + 7gg);
     *            se null default a now()+7.
     */
    public static function risolviPerStaff(string $testo, IscrizioneStaff $iscrizione, ?string $scadenza = null): string
    {
        $evento = $iscrizione->evento;
        $link   = route('form-staff.show', $iscrizione->token);

        $values = self::buildStaffValues(
            evento:   $evento,
            nome:     $iscrizione->partecipante?->nome    ?? '',
            cognome:  $iscrizione->partecipante?->cognome ?? '',
            link:     $link,
            scadenza: $scadenza ?? now()->addDays(7)->format('d/m/Y'),
        );

        return str_replace(array_keys($values), array_values($values), $testo);
    }

    /**
     * Anteprima per GestioneStaff: usa il primo membro staff se disponibile.
     * $giorniScadenza: 7 per il form iniziale, 1 per il sollecito (vedi risolviPerStaff()).
     */
    public static function anteprimaPerEvento(string $testo, Evento $evento, int $giorniScadenza = 7): string
    {
        $primoStaff   = $evento->staff->first();
        $partecipante = $primoStaff?->partecipante;
        $link         = $primoStaff ? route('form-staff.show', $primoStaff->token) : '#anteprima';

        $values = self::buildStaffValues(
            evento:   $evento,
            nome:     $partecipante?->nome    ?? '<em style="color:#9ca3af">Nome</em>',
            cognome:  $partecipante?->cognome ?? '<em style="color:#9ca3af">Cognome</em>',
            link:     $link,
            scadenza: now()->addDays($giorniScadenza)->format('d/m/Y'),
        );

        return str_replace(array_keys($values), array_values($values), $testo);
    }

    /**
     * Sostituisce i segnaposto usando i dati reali di una candidatura a graduatoria.
     */
    public static function risolviPerGraduatoria(string $testo, GraduatoriaCandidato $candidato): string
    {
        $values = self::buildGraduatoriaValues(
            graduatoria: $candidato->graduatoria,
            nome:        $candidato->nome,
            cognome:     $candidato->cognome,
        );

        return str_replace(array_keys($values), array_values($values), $testo);
    }

    /**
     * Anteprima per la pagina Gestione Graduatoria: usa il primo candidato se presente.
     */
    public static function anteprimaPerGraduatoria(string $testo, Graduatoria $graduatoria): string
    {
        $primoCandidato = $graduatoria->candidati()->first();

        $values = self::buildGraduatoriaValues(
            graduatoria: $graduatoria,
            nome:        $primoCandidato?->nome    ?? '<em style="color:#9ca3af">Nome</em>',
            cognome:     $primoCandidato?->cognome ?? '<em style="color:#9ca3af">Cognome</em>',
        );

        return str_replace(array_keys($values), array_values($values), $testo);
    }

    // ── Internals ────────────────────────────────────────────────────────────

    private static function buildBaseValues(
        Corso  $corso,
        string $nome,
        string $cognome,
        string $link,
        string $scadenza,
        string $comitatoRegionale,
        string $comitatoLocale,
    ): array {
        $evento        = $corso->evento;
        $evento->loadMissing('referenti');
        $comitatoRegId = $evento->comitato_regionale_id;

        $quota = $corso->quota_partecipazione
            ? '€ ' . number_format((float) $corso->quota_partecipazione, 2, ',', '.')
            : '';

        // Referenti regionali dell'evento
        $referentiHtml = $evento->referenti->isNotEmpty()
            ? $evento->referenti->map(fn ($u) =>
                '<strong>' . e($u->name) . '</strong> — <a href="mailto:' . e($u->email) . '" style="color:#2563eb;">' . e($u->email) . '</a>'
              )->join('<br>')
            : '';

        // Mappa base — usata anche per risolvere i segnaposto dentro le istruzioni di pagamento
        $base = [
            '{nome}'               => $nome,
            '{cognome}'            => $cognome,
            '{nome_corso}'         => $corso->nome,
            '{protocollo}'         => $corso->numero_protocollo ?? '',
            '{comitato_regionale}' => $comitatoRegionale,
            '{comitato_locale}'    => $comitatoLocale,
            '{quota}'              => $quota,
            '{scadenza}'           => $scadenza,
            '{referenti}'          => $referentiHtml,
        ];

        // Risolve i segnaposto anche dentro il testo delle istruzioni di pagamento
        $istruzioniRaw = IstruzionePagamento::risolvi($comitatoRegId)?->testo ?? '';
        $istruzioniPag = $istruzioniRaw
            ? str_replace(array_keys($base), array_values($base), $istruzioniRaw)
            : '';

        $sezionePagamento = $corso->pagamento_previsto
            ? self::buildSezionePagamento($quota, $istruzioniPag)
            : '';

        $sezioneReferenti = $evento->referenti->isNotEmpty()
            ? self::buildSezioneReferenti($evento->referenti)
            : '';

        return array_merge($base, [
            '{link_form}'             => self::buildLinkHtml($link),
            '{istruzioni_pagamento}'  => $istruzioniPag,
            '{sezione_pagamento}'     => $sezionePagamento,
            '{sezione_referenti}'     => $sezioneReferenti,
        ]);
    }

    private static function buildStaffValues(
        Evento $evento,
        string $nome,
        string $cognome,
        string $link,
        string $scadenza = '',
    ): array {
        $evento->loadMissing('referenti');
        $comitatoRegionale = $evento->comitatoRegionale?->nome ?? '';
        $comitatoLocale    = $evento->comitatoLocale?->nome    ?? '';

        $referentiHtml = $evento->referenti->isNotEmpty()
            ? $evento->referenti->map(fn ($u) =>
                '<strong>' . e($u->name) . '</strong> — <a href="mailto:' . e($u->email) . '" style="color:#2563eb;">' . e($u->email) . '</a>'
              )->join('<br>')
            : '';

        $sezioneReferenti = $evento->referenti->isNotEmpty()
            ? self::buildSezioneReferenti($evento->referenti)
            : '';

        return [
            '{nome}'               => $nome,
            '{cognome}'            => $cognome,
            '{nome_corso}'         => $evento->nome,
            '{nome_evento}'        => $evento->nome,
            '{comitato_regionale}' => $comitatoRegionale,
            '{comitato_locale}'    => $comitatoLocale,
            '{referenti}'          => $referentiHtml,
            '{link_form}'          => self::buildLinkHtml($link),
            '{sezione_referenti}'  => $sezioneReferenti,
            '{protocollo}'         => '',
            '{quota}'              => '',
            '{scadenza}'           => $scadenza,
            '{istruzioni_pagamento}' => '',
            '{sezione_pagamento}'  => '',
        ];
    }

    private static function buildLinkHtml(string $url): string
    {
        return '
<div style="text-align:center; margin:28px 0;">
  <a href="' . $url . '" style="display:inline-block; background:#dc2626; color:#ffffff; padding:13px 32px; border-radius:8px; text-decoration:none; font-weight:700; font-size:15px; letter-spacing:.01em;">
    Compila il form di preregistrazione
  </a>
</div>
<p style="text-align:center; font-size:12px; color:#6b7280; margin-top:4px;">
  Se il pulsante non funziona, copia e incolla nel browser:<br>
  <a href="' . $url . '" style="color:#2563eb;">' . $url . '</a>
</p>';
    }

    private static function buildSezioneReferenti(\Illuminate\Support\Collection $referenti): string
    {
        $righe = $referenti->map(fn ($u) =>
            '<tr>' .
            '<td style="padding:6px 14px; border-bottom:1px solid #e2e8f0; font-weight:600; color:#1e40af;">' . e($u->name) . '</td>' .
            '<td style="padding:6px 14px; border-bottom:1px solid #e2e8f0;"><a href="mailto:' . e($u->email) . '" style="color:#2563eb;">' . e($u->email) . '</a></td>' .
            '</tr>'
        )->join('');

        return '
<div style="border-left:4px solid #2563eb; padding:0 0 0 16px; margin:24px 0;">
  <h3 style="margin-top:0; margin-bottom:10px;">Contatti per informazioni</h3>
  <p style="margin:0 0 10px;">Per informazioni o comunicazioni urgenti sull\'evento, contatta i referenti:</p>
  <table style="width:100%; border-collapse:collapse;">
    <tbody>' . $righe . '</tbody>
  </table>
</div>
';
    }

    private static function buildSezionePagamento(string $quota, string $istruzioniHtml): string
    {
        $quotaRiga = $quota
            ? '<p>La quota di partecipazione da versare è <strong>' . $quota . '</strong>.</p>'
            : '';

        $istruzioni = $istruzioniHtml
            ?: '<p><em>Contatta il tuo comitato per ricevere le istruzioni di pagamento.</em></p>';

        return '
<div style="border-left:4px solid #dc2626; padding:0 0 0 16px; margin:20px 0 24px;">
  <h3 style="margin-top:0; margin-bottom:10px;">1. Pagamento della quota di iscrizione</h3>
  ' . $quotaRiga . '
  ' . $istruzioni . '
  <p style="margin-bottom:0;"><em>Ricorda di allegare la ricevuta del pagamento durante la compilazione del modulo.</em></p>
</div>
';
    }

    private static function buildGraduatoriaValues(Graduatoria $graduatoria, string $nome, string $cognome): array
    {
        return [
            '{nome}'                => $nome,
            '{cognome}'             => $cognome,
            '{titolo_graduatoria}'  => $graduatoria->titolo,
            '{codice_protocollo}'   => $graduatoria->codice_protocollo,
            '{comitato_regionale}'  => $graduatoria->comitatoRegionale?->nome ?? '',
            '{comitato_locale}'     => $graduatoria->comitatoLocale?->nome    ?? '',
            '{testo_graduatoria}'   => filled($graduatoria->testo_email_conferma)
                ? '<p>' . nl2br(e($graduatoria->testo_email_conferma)) . '</p>'
                : '',
        ];
    }
}
