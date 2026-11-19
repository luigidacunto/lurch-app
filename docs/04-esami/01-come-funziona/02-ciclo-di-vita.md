# Ciclo di vita di un esame

> **A chi serve:** direttori dei corsi e amministratori.

Un esame attraversa tre stati: **bozza**, **in corso** e **chiuso**. Dentro lo stato «in corso» c'è anche un interruttore, **prova finale aperta o chiusa**.

## Bozza

L'esame nasce in bozza quando lo si crea da **Gestione corso** ([Creare l'esame](../02-preparazione/01-creare-l-esame.md)). Prende il **modello** del tipo di corso, nell'ultima versione disponibile, e ha già il **Laboratorio 1**.

In bozza si prepara tutto: LAP, commissione, laboratori e gruppi, tutoraggio, domande del test. L'esame **non compare nell'app**: docenti e discenti non lo vedono ancora.

Un esame in bozza si può **eliminare**, purché non abbia ancora dati (schede, domande del test, esiti).

## In corso

Si passa a «in corso» con **Attiva esame**, nella Panoramica ([Panoramica e commissione](../02-preparazione/02-panoramica-e-commissione.md)). Serve un direttore collegato a un socio e almeno un laboratorio.

All'attivazione il modello viene **congelato**: l'esame continuerà a usare pesi e voci di quel momento, anche se in futuro arriverà una versione nuova del modello. LAP, commissione, laboratori, gruppi e tutoraggio restano modificabili fino alla chiusura.

Da qui:

- docenti e commissione trovano l'esame nell'app e compilano le loro **schede personali** del corso;
- il direttore avvia il **test scritto** ai discenti ([Test scritto](../03-svolgimento-e-chiusura/02-test-scritto.md));
- il direttore inserisce le **schede finali** dal pannello ([Schede personali e schede finali](03-schede-personali-e-finali.md)).

Se l'esame è stato attivato per errore, **Riporta in bozza** (nella pagina di modifica dell'esame) lo riporta indietro, ma solo finché non esiste ancora nessuna scheda, test o esito.

### Prova finale aperta o chiusa

**Apri la prova finale**, nella Panoramica, apre al direttore e ai docenti di commissione le schede personali della prova finale (nel modello TCRI: colloquio e prova pratica). Lo aziona chi gestisce l'esame, solo a esame in corso.

**Richiudi la prova finale** le blocca: restano visibili in sola lettura e si riprendono riaprendo. L'interruttore riguarda solo le schede personali: le schede finali del direttore si inseriscono comunque.

## Chiuso

**Chiudi esame**, nella pagina Esito, spetta **solo al direttore**. Si può chiudere solo quando ogni discente ammesso ha tutti i dati; altrimenti il pulsante resta spento e il suggerimento dice cosa manca ([Chiusura, riapertura ed export](../03-svolgimento-e-chiusura/06-chiusura-riapertura-export.md)).

Alla chiusura LURCH scatta una fotografia: i risultati di ogni discente vengono salvati e da lì in poi **non si ricalcolano più**. Nessuno può modificare schede, test o configurazione. Restano possibili solo la consultazione, gli export e la **pubblicazione** di risultati ed esiti ai discenti, che riguarda ciò che vedono e non le valutazioni.

## Riapertura

Un esame chiuso si può **riaprire solo dal superadmin**, con un motivo scritto (almeno 10 caratteri), **entro 15 giorni di calendario** dalla chiusura, fino alla fine del quindicesimo giorno. La riapertura cancella i risultati salvati: la chiusura successiva li riscrive. Passati i 15 giorni l'esame è definitivo e non lo riapre nessuno.
