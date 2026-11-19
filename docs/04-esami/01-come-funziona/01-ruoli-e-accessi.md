# Ruoli e accessi

> **A chi serve:** tutti quelli che hanno un ruolo in un esame, e agli amministratori che li impostano.

I ruoli di un esame si leggono **dal corso**, in diretta, senza copie: il direttore, il presidente o delegato e i docenti si scelgono tra i soci nel modulo del corso ([Creare e modificare un corso](../../01-pannello/02-gestione-operativa/02-corsi/02-creare-e-modificare.md)), i discenti sono gli iscritti al corso. L'esame aggiunge soltanto la **commissione**: da 0 a 3 docenti del corso, scelti da un amministratore. Se un ruolo cambia sul corso, cambia subito anche nell'esame.

## Chi fa cosa

| Ruolo | Dove lavora | Cosa fa |
|---|---|---|
| **Direttore del corso** | pannello e app | Prepara l'esame (laboratori, gruppi, tutoraggio, domande del test), lo attiva, avvia il test scritto, inserisce le **schede finali**, pubblica risultati ed esiti, **chiude** l'esame. Nell'app compila anche le sue schede personali, come un docente. |
| **Amministratore regionale o locale**, **superadmin** | pannello | Per gli esami del proprio territorio: imposta la **commissione** (il direttore no), modifica e prepara l'esame, lo attiva, apre la prova finale, pubblica risultati ed esiti. Non inserisce schede finali, non avvia il test e non chiude: questo spetta solo al direttore, anche rispetto al superadmin. |
| **Docente in commissione** | app (pannello in sola lettura) | Compila le sue schede personali, anche quelle della prova finale; vede le schede di tutti, i riepiloghi e il test scritto. |
| **Presidente o delegato** | app (pannello in sola lettura) | **Non compila nulla**: vede tutto quello che vede la commissione. |
| **Docente** (non in commissione) | app | Compila le sue schede personali durante il corso e vede solo quelle. Non vede la prova finale, il test scritto né l'esito. |
| **Tutor** | app | È un docente del corso (o il direttore) a cui il direttore affida un gruppo di discenti: oltre alle sue schede, legge quelle di tutti i docenti sui discenti che segue ([Tutoraggio](../02-preparazione/05-tutoraggio.md)). |
| **Discente** | app | Svolge il **test scritto** quando il direttore lo avvia per lui, poi lo rivede in sola lettura. Vede la correzione del test e l'esito finale solo quando gli vengono pubblicati. |

Quali schede compilano docenti e commissione dipende dal modello dell'esame: per gli esami TCRI vedi [Modello TCRI](../04-modello-tcri/README.md).

## Chi vede l'esame nel pannello

- Il **superadmin** vede tutti gli esami.
- L'**amministratore regionale** vede gli esami dei corsi degli eventi della sua regione, compresi quelli organizzati dai comitati locali.
- L'**amministratore locale** vede gli esami dei corsi degli eventi del suo comitato locale.
- Il **direttore del corso** vede l'esame del corso che dirige, qualunque sia il suo ruolo nel pannello.
- **Presidente o delegato** e **docenti in commissione** vedono l'esame in sola lettura, dopo l'attivazione.

Gli **operatori** non vedono gli esami, nemmeno degli eventi a cui sono assegnati, a meno che non siano direttori di quel corso.

> Per chi lavora nel pannello come direttore, presidente o docente di commissione, l'utente del pannello deve essere **collegato al suo socio**: è da lì che LURCH lo riconosce. Il collegamento lo fa un amministratore in [Utenti](../../01-pannello/04-impostazioni/01-utenti.md). Un direttore senza utente collegato può compilare le sue schede dall'app, ma non può inserire le schede finali né chiudere l'esame.

## Chi vede l'esame nell'app

Nell'app l'esame compare a chi ha un ruolo nel corso (direttore, presidente o delegato, docente, commissione) **dopo l'attivazione**: in bozza non lo vede nessuno. Al discente compare solo quando il test scritto viene avviato per lui, e resta visibile dopo l'invio. Dettagli in [L'esame nell'app](04-l-esame-nell-app.md).
