<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Template globale di default ───────────────────────────────────
        EmailTemplate::updateOrCreate(
            ['tipo' => 'iscritti', 'comitato_regionale_id' => null],
            [
                'soggetto' => 'Iscrizione confermata — {nome_corso}',
                'attivo'   => true,
                'corpo'    => <<<'HTML'
<p>Gentile <strong>{cognome} {nome}</strong>,</p>
<p>la tua iscrizione al corso <strong>{nome_corso}</strong> {protocollo} organizzato da <strong>{comitato_regionale}</strong> è stata accettata.</p>
<p>⚠️ Questa comunicazione non verrà trasmessa al tuo Comitato di appartenenza. Sarà tua responsabilità informarlo e attivarti per completare la procedura.</p>
<h3>Azioni richieste</h3>
{sezione_pagamento}
<h3>Compilazione del modulo online</h3>
<p>Accedi al link seguente e compila il modulo di preregistrazione:</p>
{link_form}
<p>Il link è <strong>personale</strong> — non condividerlo con altri partecipanti.</p>
<h3>Scadenza</h3>
<p>Tutta la documentazione dovrà essere inviata entro e non oltre il <strong>{scadenza}</strong>.</p>
<p>In caso di mancato invio o invio incompleto entro la scadenza, l'iscrizione sarà annullata senza ulteriori comunicazioni.</p>
{sezione_referenti}<p>Per qualsiasi dubbio o chiarimento, rimaniamo a disposizione.</p>
<p>Cordiali saluti,<br><strong>{comitato_regionale}</strong></p>
HTML,
            ]
        );

        // ── 2. Template globale sollecito iscritti ────────────────────────────
        EmailTemplate::updateOrCreate(
            ['tipo' => 'sollecito_iscritti', 'comitato_regionale_id' => null],
            [
                'soggetto' => 'Sollecito: compila il modulo — {nome_corso}',
                'attivo'   => true,
                'corpo'    => <<<'HTML'
<p>Gentile <strong>{cognome} {nome}</strong>,</p>
<p>ti ricordiamo che non risulta ancora pervenuta la tua preregistrazione al corso <strong>{nome_corso}</strong> organizzato da <strong>{comitato_regionale}</strong>.</p>
<p>Per completare l'iscrizione è necessario compilare il modulo online entro il <strong>{scadenza}</strong>.</p>
<h3>Compila ora il modulo</h3>
{link_form}
<p>Il link è <strong>personale</strong> — non condividerlo con altri partecipanti.</p>
<p>Se non hai ricevuto la comunicazione originale con le istruzioni complete o hai smarrito il link, scrivi a {referenti} e ti verrà reinviato tutto il necessario.</p>
<p>In caso di mancata compilazione entro la scadenza, l'iscrizione potrà essere annullata senza ulteriori comunicazioni.</p>
{sezione_referenti}<p>Cordiali saluti,<br><strong>{comitato_regionale}</strong></p>
HTML,
            ]
        );

        // ── 3. Template globale sollecito staff ───────────────────────────────
        EmailTemplate::updateOrCreate(
            ['tipo' => 'sollecito_staff', 'comitato_regionale_id' => null],
            [
                'soggetto' => 'Sollecito: compila il modulo staff — {nome_evento}',
                'attivo'   => true,
                'corpo'    => <<<'HTML'
<p>Gentile <strong>{cognome} {nome}</strong>,</p>
<p>ti ricordiamo che non risulta ancora pervenuta la tua preregistrazione come membro staff dell'evento <strong>{nome_evento}</strong> organizzato da <strong>{comitato_regionale}</strong>.</p>
<p>Per completare la procedura è necessario compilare il modulo online entro il <strong>{scadenza}</strong>.</p>
<h3>Compila ora il modulo</h3>
{link_form}
<p>Il link è <strong>personale</strong> — non condividerlo con altri membri.</p>
<p>Se non hai ricevuto la comunicazione originale o hai smarrito il link, scrivi ai referenti indicati di seguito e ti verrà reinviato tutto il necessario.</p>
{sezione_referenti}<p>Cordiali saluti,<br><strong>{comitato_regionale}</strong></p>
HTML,
            ]
        );

        // ── 4. Template globale conferma candidatura graduatoria ─────────────
        EmailTemplate::updateOrCreate(
            ['tipo' => 'graduatoria', 'comitato_regionale_id' => null],
            [
                'soggetto' => 'Candidatura confermata — {titolo_graduatoria}',
                'attivo'   => true,
                'corpo'    => <<<'HTML'
<p>Gentile <strong>{cognome} {nome}</strong>,</p>
<p>la tua candidatura alla graduatoria <strong>{titolo_graduatoria}</strong> è stata <strong>confermata</strong>.</p>
<p>Protocollo: <strong>{codice_protocollo}</strong></p>
{testo_graduatoria}
<p>Cordiali saluti,<br><strong>{comitato_regionale}</strong></p>
HTML,
            ]
        );

        // ── 5. Override regionale — Comitato Regionale Toscana (ID 3) ────────
        $toscanaId = \App\Models\ComitatoRegionale::where('nome', 'like', '%Toscana%')->value('id');

        if ($toscanaId) {
            EmailTemplate::updateOrCreate(
                ['comitato_regionale_id' => $toscanaId],
                [
                    'soggetto' => 'Iscrizione confermata: {nome_corso} — {protocollo}',
                    'attivo'   => true,
                    'corpo'    => <<<'HTML'
<p>Buongiorno,</p>
<p>La tua richiesta di iscrizione al corso <strong>{nome_corso} {protocollo}</strong> <em>{comitato_regionale}</em> è stata accettata.</p>
<p>Ti invitiamo pertanto a procedere con le seguenti azioni, necessarie per completare l'iscrizione.</p>
<p>⚠️ <strong>Attenzione:</strong> Questa comunicazione non verrà trasmessa al tuo Comitato di appartenenza. Sarà tua responsabilità informarlo e attivarti per completare la procedura.</p>
<p><strong>IN CASO DI MANCATO PAGAMENTO O MANCATO INVIO DEL MODULO NEI TEMPI PREVISTI L'ISCRIZIONE SARÀ AUTOMATICAMENTE CANCELLATA E SUBENTRERANNO LE ISCRIZIONI SUCCESSIVE. NON CI SARANNO ALTRE COMUNICAZIONI.</strong></p>
<h3>✅ Azioni richieste</h3>
{sezione_pagamento}
<h3>Compilazione del modulo online</h3>
<p>Accedi al seguente link e compila il modulo di iscrizione:</p>
{link_form}
<h3>Scadenza invio documentazione</h3>
<p>Tutta la documentazione dovrà essere inviata entro e non oltre il <strong>{scadenza}</strong>, in modo completo e conforme alle indicazioni sopra riportate.</p>
<h3>Mancato invio o documentazione incompleta</h3>
<p>In caso di mancato invio o invio incompleto della documentazione entro la scadenza indicata, l'iscrizione sarà annullata senza ulteriori comunicazioni e verrà ammesso il successivo candidato in ordine di iscrizione.</p>
{sezione_referenti}<p>Per qualsiasi dubbio o necessità di chiarimento, rimaniamo a disposizione.</p>
<p>Cordiali saluti,<br><strong>Formazione {comitato_regionale}</strong></p>
HTML,
                ]
            );
        }
    }
}
