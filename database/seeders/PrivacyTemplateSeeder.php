<?php

namespace Database\Seeders;

use App\Models\PrivacyTemplate;
use Illuminate\Database\Seeder;

class PrivacyTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $informativa = <<<'EOT'
INFORMATIVA SUL TRATTAMENTO DEI DATI PERSONALI
ai sensi degli artt. 13-14 del Regolamento UE 2016/679 (GDPR)

Questa informativa riunisce in un unico testo tutti i trattamenti di dati personali effettuati tramite il portale gestionale LURCH. Ogni modulo di iscrizione o candidatura richiede il consenso solo ai punti effettivamente pertinenti ai dati che raccoglie.

1. TITOLARE DEL TRATTAMENTO
Il Titolare del trattamento è {titolare}, con sede in {indirizzo}, contattabile all'indirizzo e-mail: {email}. Il Rappresentante Legale è {rappresentante_legale}.

2. RESPONSABILE DEL TRATTAMENTO
LURCH è un portale gestionale sviluppato e reso disponibile da {responsabile}, come volontario e cittadino privato, ai Comitati che scelgono di utilizzarlo. {responsabile} tratta i dati in qualità di responsabile del trattamento (art. 28 GDPR), in quanto fornisce e gestisce tecnicamente il portale (sviluppo software e infrastruttura server) su incarico del Comitato Titolare.
La creazione degli account con permessi regionali o locali sul portale è eseguita dal responsabile esclusivamente su indicazione del Comitato competente, che ne decide autonomamente titolarità e ambito: il responsabile non individua né assegna in autonomia chi ha accesso a quali dati.

3. CATEGORIE DI DATI TRATTATI E FINALITÀ

3.1 Dati anagrafici e di contatto
Nome, cognome, codice fiscale, e-mail, telefono, comitato di appartenenza. Trattati per la gestione amministrativa e organizzativa dell'iscrizione, della candidatura e della partecipazione ad attività, corsi ed eventi, e per l'invio di comunicazioni organizzative correlate.

3.2 Qualifiche e attestati
Le qualifiche e gli attestati di formazione conseguiti all'interno dell'associazione sono importati e consultati a partire da GAIA, l'unico sistema ufficiale dell'associazione per questo dato. LURCH non sostituisce GAIA né ne costituisce una fonte alternativa: ne consulta e ne conserva una copia operativa al fine di verificare i requisiti per l'ammissione a graduatorie, corsi ed eventi. Questi dati sono consultati dal personale già abilitato alla consultazione su GAIA e, limitatamente agli aspetti logistici, dagli operatori assegnati allo specifico evento a cui si partecipa.

3.3 Dati relativi ad allergie, intolleranze ed esigenze alimentari
Dati particolari relativi alla salute (art. 9 GDPR). Raccolti, quando indicati facoltativamente, per la gestione dei pasti negli eventi a cui si partecipa e conservati per non doverli reinserire ad ogni evento successivo. Trattati esclusivamente dal personale organizzativo incaricato della logistica pasti di quell'evento.

3.4 Immagini fotografiche e video
Durante gli eventi potranno essere effettuate riprese fotografiche e video a fini documentali, istituzionali e comunicativi della Croce Rossa Italiana. Le immagini potranno essere pubblicate sui canali istituzionali (sito web, social media, materiali informativi) del Titolare.

4. BASE GIURIDICA DEL TRATTAMENTO
Dati anagrafici e di contatto (3.1): art. 6, par. 1, lett. b) GDPR (esecuzione del rapporto associativo/organizzativo).
Qualifiche e attestati (3.2): consenso esplicito dell'interessato (art. 6, par. 1, lett. a) GDPR).
Dati relativi ad allergie/salute (3.3): consenso esplicito ai sensi dell'art. 9, par. 2, lett. a) GDPR. Il conferimento è facoltativo; in assenza, non sarà possibile garantire la preparazione di pasti personalizzati.
Immagini fotografiche e video (3.4): consenso dell'interessato ai sensi dell'art. 6, par. 1, lett. a) GDPR. Facoltativo.

5. MODALITÀ E LUOGO DEL TRATTAMENTO
I dati sono trattati su server situato in Italia/Unione Europea, senza trasferimento verso paesi extra-UE, con misure tecniche e organizzative adeguate ai sensi degli artt. 5 e 32 GDPR.

6. CONSERVAZIONE DEI DATI
I dati non hanno un termine di conservazione prestabilito legato al tempo: restano accessibili finché il rapporto associativo è attivo. Quando il Comitato competente segnala che una persona non è più socia/attiva, i suoi dati diventano non più consultabili, restando recuperabili solo per il tempo necessario ad adempiere a eventuali obblighi legali, contabili o archivistici, prima della cancellazione definitiva.
Le immagini di cui al punto 3.4 sono conservate per il tempo necessario alle finalità indicate e comunque non oltre 5 anni dalla data dell'evento.

7. SOGGETTI CHE ACCEDONO AI DATI
- Personale già abilitato alla consultazione dei dati su GAIA (per il punto 3.2);
- Operatori assegnati a uno specifico evento, limitatamente agli aspetti logistici (es. pasti, alloggio) di quell'evento;
- Amministratori regionali e locali, per le finalità di gestione scadenze qualifiche, graduatorie, corsi ed eventi;
- Il responsabile del trattamento di cui al punto 2, per la gestione tecnica del portale.
I dati non sono ceduti, venduti o comunicati a soggetti terzi al di fuori di quanto sopra indicato.

8. DIRITTI DELL'INTERESSATO
Ai sensi degli artt. 15-22 del GDPR, hai diritto di: accedere ai tuoi dati (art. 15); richiederne la rettifica (art. 16); ottenerne la cancellazione (art. 17); richiedere la limitazione del trattamento (art. 18); opporti al trattamento (art. 21); richiedere la portabilità dei dati (art. 20); revocare in qualsiasi momento un consenso prestato, senza pregiudizio per la liceità del trattamento effettuato prima della revoca.

9. MODALITÀ DI ESERCIZIO DEI DIRITTI
Per esercitare questi diritti puoi rivolgerti al Comitato titolare del trattamento (punto 1), scrivendo a: {email}. Il Comitato può coinvolgere il responsabile tecnico del portale per l'esecuzione della richiesta. Risposta entro 30 giorni, prorogabili di ulteriori 60 in casi di particolare complessità, con comunicazione del motivo (art. 12.3 GDPR).

10. DIRITTO DI RECLAMO
Hai diritto di proporre reclamo all'Autorità Garante per la protezione dei dati personali (art. 77 GDPR) — www.garanteprivacy.it
EOT;

        PrivacyTemplate::updateOrCreate(
            ['tipo' => 'informativa_dati', 'comitato_regionale_id' => null],
            ['testo' => $informativa, 'attivo' => true]
        );

        // Il consenso foto/video ora è un punto (3.4) del testo unico sopra: questo
        // template resta in tabella per compatibilità ma non è più risolto/mostrato
        // separatamente dai controller dei form pubblici.
        PrivacyTemplate::where('tipo', 'foto_video')
            ->whereNull('comitato_regionale_id')
            ->update(['attivo' => false]);
    }
}
