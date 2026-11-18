# Userscript bridge GAIA

> **A chi serve:** amministratori (superadmin, regionali e locali). Gli operatori non lo usano: per loro i pulsanti non compaiono.

Lo **userscript bridge GAIA** è un piccolo programma che si aggiunge al browser con l'estensione **Tampermonkey** e collega il pannello a GAIA. Serve a due cose:

- trovare l'**ID del profilo GAIA** di un socio (dal codice fiscale) e salvarlo nella sua scheda, così il pannello può aprire il suo curriculum su GAIA con un clic;
- leggere il **curriculum GAIA** dei soci e importarne le **qualifiche CRI** e le **patenti CRI**, con un'anteprima da confermare, come l'import da Excel ([Importare da GAIA](../../02-gestione-operativa/05-qualifiche/02-importare-da-gaia.md)).

## Perché serve uno userscript

GAIA non permette a un altro sito di leggere i suoi dati per conto di chi è collegato: una pagina del pannello, da sola, non può interrogare GAIA. Lo userscript sì, perché lavora dentro il browser con la **sessione GAIA già aperta** da chi lo usa. Ne derivano due regole:

- per usarlo bisogna **essere entrati su GAIA** ([gaia.cri.it](https://gaia.cri.it)) nello stesso browser in cui si usa il pannello;
- lo userscript legge solo ciò che **il proprio utente GAIA può già vedere**: se su GAIA non si hanno i permessi su un socio, il suo curriculum non si legge.

Il pannello non conserva credenziali GAIA e non contatta GAIA da solo: ogni lettura parte dal browser di chi preme il pulsante.

## Dove compare

| Pagina | Pulsanti | Cosa fanno |
|---|---|---|
| [Scheda del socio](../../03-archivi/01-soci/02-scheda-del-socio.md) | **Cerca ID GAIA**, **Importa CV GAIA** | ID e curriculum di quel socio |
| [Candidati di una graduatoria](../../02-gestione-operativa/04-graduatorie/03-candidati.md) | **Cerca ID GAIA**, **Importa qualifiche GAIA (graduatoria)** | ID e curriculum di tutti i candidati |
| [Ricerca qualifiche](../../02-gestione-operativa/05-qualifiche/01-ricerca-ed-export.md), scelto un comitato locale | **Importa qualifiche GAIA (comitato)** | curriculum di tutti i soci del comitato |

Finché lo userscript non è installato, al posto dei pulsanti c'è un solo collegamento, **Installa userscript GAIA**; se la versione installata è vecchia, compare **Aggiorna userscript** ([Installazione e aggiornamento](01-installazione-e-aggiornamento.md)).

## Chi può usarlo e su quali soci

- **Superadmin**: tutti i soci, tutte le graduatorie, tutti i comitati.
- **Amministratore regionale**: soci, graduatorie e comitati della propria regione.
- **Amministratore locale**: i soci del proprio comitato, le graduatorie del proprio comitato (non quelle regionali), solo il proprio comitato nell'import per comitato.

Valgono anche per i soci **in estensione** presso il comitato: le loro qualifiche si leggono e si importano, mentre i dati anagrafici restano modificabili solo dal comitato di appartenenza. I controlli sono fatti dal pannello a ogni salvataggio: uno userscript installato da chi non ne ha diritto non riesce comunque a scrivere nulla.

## Le pagine di questo capitolo

1. [Installazione e aggiornamento](01-installazione-e-aggiornamento.md)
2. [Usarlo: socio, graduatoria, comitato](02-usarlo.md)
3. [Anteprima e importazione](03-anteprima-e-importazione.md)

## Un altro script: GAIA Swissknife

Il pulsante **Incolla da GAIA** nei moduli di creazione di [corsi](../../02-gestione-operativa/02-corsi/02-creare-e-modificare.md) e [graduatorie](../../02-gestione-operativa/04-graduatorie/01-elenco-e-creazione.md) non usa questo userscript, ma **GAIA Swissknife**, uno script Tampermonkey separato che lavora sulle pagine di GAIA. Nella pagina «Informazioni» di un corso su GAIA aggiunge il pulsante **Copia per LURCH (JSON)**, che copia negli appunti nome, protocollo, direttore, quota, date e scadenza delle domande; nel pannello, **Incolla da GAIA** li inserisce nel modulo.

Si installa da [questo collegamento](https://luigidacunto.com/scripts/gaia-swissknife/tampermonkey-script/GAIA-Swissknife.user.js), con Tampermonkey già presente nel browser, come descritto in [Installazione e aggiornamento](01-installazione-e-aggiornamento.md). I due script sono indipendenti: si può installare l'uno, l'altro o entrambi.
