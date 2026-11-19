# Esami

> **A chi serve:** direttori dei corsi, commissioni d'esame, docenti, discenti e amministratori dei comitati che usano gli esami.

> **Come è stata scritta.** Questa guida è stata scritta interamente con l'ausilio di un agente di intelligenza artificiale, Claude Code di Anthropic con il modello Claude Opus 5.5, in base alle indicazioni dello sviluppatore e all'analisi diretta del codice. Il contenuto è verificato sul funzionamento reale del programma, ma finché non sarà revisionato da una persona alcuni passaggi potrebbero mancare e le motivazioni di alcune scelte potrebbero non essere spiegate con precisione. Le pagine già revisionate lo indicano in apertura con la riga «Pagina revisionata» e la data.

Un esame valuta i discenti di un corso: è legato a quel corso (uno solo per corso) e lo gestiscono il direttore del corso, la commissione e i docenti. Il direttore lavora nel pannello, docenti e commissione compilano le loro schede nell'app dei soci, i discenti svolgono il test scritto dall'app.

Ogni esame segue un **modello**, scelto in base al tipo di corso del catalogo: il modello dice quali prove ci sono, quali schede si compilano e come si calcola il punteggio finale. Questa guida è divisa di conseguenza:

- le parti **comuni a ogni esame** (come funziona, preparazione, svolgimento e chiusura);
- un capitolo **per ogni modello**, con le sue schede e il suo calcolo. Oggi c'è il [modello TCRI](04-modello-tcri/README.md).

Chi non usa gli esami può ignorare questa sezione.

## Capitoli

- **Come funziona:** [ruoli e accessi](01-come-funziona/01-ruoli-e-accessi.md), [ciclo di vita](01-come-funziona/02-ciclo-di-vita.md), [schede personali e finali](01-come-funziona/03-schede-personali-e-finali.md), [l'esame nell'app](01-come-funziona/04-l-esame-nell-app.md).
- **Preparazione:** [creare l'esame](02-preparazione/01-creare-l-esame.md), [panoramica e commissione](02-preparazione/02-panoramica-e-commissione.md), [discenti](02-preparazione/03-discenti.md), [laboratori e gruppi](02-preparazione/04-laboratori-e-gruppi.md), [tutoraggio](02-preparazione/05-tutoraggio.md), [domande del test](02-preparazione/06-domande-del-test.md).
- **Svolgimento e chiusura:** [valutazioni](03-svolgimento-e-chiusura/01-valutazioni.md), [test scritto](03-svolgimento-e-chiusura/02-test-scritto.md), [il test per il discente](03-svolgimento-e-chiusura/03-il-test-per-il-discente.md), [riepilogo dei discenti](03-svolgimento-e-chiusura/04-riepilogo-dei-discenti.md), [esito e pubblicazione](03-svolgimento-e-chiusura/05-esito-e-pubblicazione.md), [chiusura, riapertura ed export](03-svolgimento-e-chiusura/06-chiusura-riapertura-export.md).
- **[Modello TCRI](04-modello-tcri/README.md):** calcolo e pesi, laboratori a semaforo, itinere, colloquio (autovalutazione), prova pratica.
