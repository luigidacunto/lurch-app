# Contribuire a LURCH

Grazie dell'interesse. LURCH è un progetto con un unico mantenitore, che decide la direzione generale: le proposte sono benvenute, ma non tutte possono essere accolte. Per evitare lavoro inutile, conviene confrontarsi prima di scrivere codice impegnativo.

## Segnalare un problema o proporre una funzione

Apri una *issue* indicando:

- cosa ti aspettavi e cosa è successo (per un errore: passaggi per riprodurlo, versione di LURCH, PHP e database usati);
- per una nuova funzione, il bisogno concreto che risolve, più che la soluzione tecnica.

**Vulnerabilità di sicurezza**: non aprire una issue pubblica, segui [SECURITY.md](SECURITY.md).

## Proporre una modifica (pull request)

1. Per modifiche non banali apri prima una issue e attendi un riscontro sull'approccio.
2. Crea un ramo a partire da `dev` (il ramo di lavoro; `main` contiene solo le versioni rilasciate) e apri la pull request verso `dev`.
3. Una pull request, un argomento: modifiche piccole e focalizzate si valutano più in fretta.
4. Descrivi cosa cambia e perché, e come l'hai verificato.

### Requisiti della modifica

- **Test**: ogni correzione o funzione nuova deve avere un test, e la suite deve passare (`php artisan test`).
- **Stile del codice**: segui lo stile del codice circostante e formatta con Laravel Pint (`vendor/bin/pint`).
- **Lingua**: testi dell'interfaccia, commenti e documentazione sono in italiano; i nomi di classi, metodi e variabili seguono quelli già presenti nel codice.
- **Nessun dato reale**: niente dati personali, indirizzi o riferimenti a una specifica installazione nel codice, nei test o negli esempi; i dati dell'installazione sono configurazione (`.env`), non codice.
- **Nessun marchio di terzi**: non aggiungere loghi, emblemi o immagini di cui non si abbiano i diritti.
- **Migrazioni**: solo additive e reversibili quando possibile; una modifica che può far perdere dati esistenti va discussa prima in una issue.
- **Dipendenze**: aggiungine di nuove solo se indispensabili e con licenza compatibile con la AGPL-3.0.

### Firma dei commit (DCO)

Ogni commit deve contenere la riga

```
Signed-off-by: Nome Cognome <email@esempio.it>
```

che si aggiunge in automatico con `git commit -s`. Con questa firma dichiari quanto previsto dal [Developer Certificate of Origin 1.1](https://developercertificate.org/): in sintesi, che il contributo è opera tua (o che hai comunque il diritto di inviarlo) e che può essere distribuito con la licenza del progetto. Le pull request con commit non firmati non possono essere accettate.

### Cosa succede dopo

Il mantenitore valuta le proposte compatibilmente con il tempo disponibile. Può chiedere modifiche, proporre un'alternativa o non accettare una proposta anche se tecnicamente corretta, quando non è coerente con la direzione del progetto. Non è un giudizio sul lavoro svolto.

## Licenza dei contributi

Inviando una pull request accetti che il tuo contributo sia distribuito con la licenza del progetto, la [GNU AGPL v3 o successiva](LICENSE). Mantieni i diritti d'autore sul tuo contributo.

## Comportamento

Discutere con rispetto e restare sul merito tecnico. Commenti offensivi o fuori tema possono essere rimossi e chi li scrive escluso dalla discussione.
