# Roadmap

Elenco di ciò che non è ancora stato rilasciato. Una voce completata viene tolta nel commit che la rilascia.

## Previsto

### Eventi e presenze
- Vista delle presenze effettive (pasti, pernotti, giorni) calcolata su chi è entrato, separata dalle presenze stimate.
- Riorganizzazione delle viste dell'evento: pianificazione prima dell'evento e operatività quotidiana di segreteria e cucina.
- Scelta della veste di partecipazione (volontario o dipendente) nei form di iscrizione e staff: la colonna esiste ma non è ancora usata.

### Corsi, graduatorie e qualifiche
- Campo `nome_visibile` del corso da usare in tutte le visualizzazioni al posto di `nome`.
- Graduatoria su più corsi, ciascuno con i propri posti disponibili.
- Proposta automatica del corso di riferimento della graduatoria a partire dal corso collegato.
- Import dello storico completo delle date dei corsi per ogni qualifica.
- Allineamento delle funzioni che leggono il direttore del corso come testo (elenchi, stampe, mail) al direttore collegato all'anagrafica.

### Staff
- Caricamento del nullaosta per la logistica spostato dalla preregistrazione alla candidatura staff.
- Sezione pubblica «Modelli» (portale e sito) con il modello vuoto del nullaosta staff.
- Sondaggio pubblico sulla disponibilità dello staff.

### Esami
- Avvisi non bloccanti per i discenti non assegnati a nessun gruppo.
- Statistiche dell'esame per il direttore.
- Esami per altri corsi oltre al TCRI: per ogni tipo di corso il proprio metodo (quali prove, come si calcola), con versioni che possono cambiare anche la struttura; prove comuni a più corsi riusate (per esempio il test scritto, già indipendente dal TCRI) e un archivio di domande condiviso tra esami dello stesso corso.
- Varianti regionali del modello d'esame: la struttura di base resta nazionale (prove e pesi uguali per tutti), mentre ogni regione può avere le proprie versioni di voci, metriche, valori minimi e massimi e modalità. Alla creazione dell'esame si propone l'ultima versione della regione del corso, oppure il modello nazionale se la regione non ne ha uno proprio.
- Consultazione in sola lettura dei dati già scritti per i discenti poi non ammessi.
- Blocco delle modifiche lato corso (iscrizioni, ritiri, import GAIA, docenti) a esame chiuso.

### Privacy e portale
- Gestione del consenso dei minori (consenso del genitore o tutore).
- Modifica dei propri consensi da parte del partecipante, dal portale.
- Pagina in sola lettura della struttura dei comitati per tutti gli utenti registrati.
- Identità unica per pannello e portale: l'utente del pannello coincide con il socio.
- Cifratura dei dati sensibili a livello di database.

### Pannello
- Pulsante «Elimina» spostato dalla tabella alla pagina di modifica.
- Traduzione italiana completa dei messaggi di validazione di Laravel.
- Questionari (struttura del database già presente).
- Guida completa: capitolo sugli esami (pannello e app) e sezione pubblica nell'app con le parti per volontari e moduli pubblici.

### Tecnico
- Compattazione delle migrazioni in uno schema di prima installazione.
- Verifica generale del pannello su una copia recente dei dati di produzione.
- Editor dei messaggi del badge su Quill 2 (la 1.3.7 ha un avviso di sicurezza, oggi neutralizzato dal filtro lato server; la 2 cambia l'HTML degli elenchi).
- pdf.js da pacchetto npm compilato con Vite al posto dei file in `public/vendor/pdfjs` (versione tracciata e controllata da `npm audit`; il worker richiede una configurazione dedicata).

## Non previsto
- Blocco delle altre operazioni sui soci durante un import massivo: per ora non previsto, da riconsiderare solo se le operazioni in parallelo diventano un problema reale.
- Sanatoria retroattiva del consenso GAIA: gestita fuori dall'applicazione.
