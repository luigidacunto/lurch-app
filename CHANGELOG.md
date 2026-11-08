# Changelog

Una voce per ogni versione rilasciata, dalla più recente.

Le voci fino alla 1.85.16 sono ricostruite dagli oggetti dei commit.

## 1.85.21 — 2026-11-08

- feat: in «I miei tutorati» il tutor legge anche le note dei docenti sui propri discenti (itinere sempre, laboratorio a scheda inviata); la commissione continua a non vederle (solo il direttore). Sotto il campo della nota, il testo su chi la legge cita i tutor

## 1.85.20 — 2026-11-08

- feat: gruppi di tutoraggio negli esami: nella sezione Gestione dell'esame la scheda «Tutoraggio» crea gruppi di discenti con uno o più docenti tutor (un discente in un solo gruppo, un docente anche in più gruppi); migrazione solo additiva (tre tabelle nuove)
- feat: nella PWA il tutor trova «I miei tutorati»: per ogni suo discente, in sola lettura, medie dei docenti per area dell'itinere e medie dei laboratori colorate rispetto al LAP, schede itinere di tutti i docenti, laboratori con la tabella voce per voce (solo schede inviate) e punteggio finale d'area; niente prova pratica, colloquio, test scritto ed esito
- fix: nella home della PWA docenti e commissione trovano gli esami nella sezione «Area docenti» («Esami da gestire»); il discente con il test avviato continua a vedere «I tuoi esami»
- refactor: la tabella voce per voce dei laboratori è un componente condiviso tra il confronto tra docenti e la pagina del tutor

## 1.85.19 — 2026-11-08

- fix: aggiornamento di sicurezza delle dipendenze composer (`composer audit` da 92 avvisi a 0): Filament 5.11.2, Laravel 12.71, Livewire 4.4.8, Symfony, league/commonmark, phpoffice/phpspreadsheet, guzzle, dompdf, medialibrary e altri
- chore: PHP 8.4 come versione minima (`composer.json` con piattaforma fissata a 8.4.0); README e guide di installazione e deploy aggiornati
- fix: le pagine personalizzate di evento e corso dichiarano `#[Locked]` sul record, come richiesto da Filament 5.11 per le proprietà bloccate ridichiarate
- fix: vista delle briciole di pane riallineata al componente di Filament 5.11 (attributi di accessibilità), mantenendo «Dashboard» come primo livello
- docs: `.env.example` e guida di deploy con `APP_LOCALE=it` e `APP_FALLBACK_LOCALE=en`: i testi senza traduzione italiana (messaggi di validazione di Laravel, testi nuovi di Filament) escono in inglese invece che come chiave grezza

## 1.85.18 — 2026-11-08

- fix: dipendenze npm aggiornate per correggere vulnerabilità note (axios 1.21.0, vite 7.3.7, concurrently 9.2.5, shell-quote 1.12.0, source-map-js 1.2.2, follow-redirects 1.16.2)
- chore: release automatica su GitHub a ogni pubblicazione su `main` con una versione nuova (tag `vX.Y.Z` e note dai commit); Dependabot limitato agli aggiornamenti di sicurezza, raggruppati per ecosistema

## 1.85.17 — 2026-11-07

- chore: codice e documentazione preparati per la pubblicazione open source (licenza AGPL-3.0, CONTRIBUTING, SECURITY, sezioni del README su marchi e licenza); documentazione interna spostata fuori dal repository; commenti impersonali; nei documenti segnaposto al posto dei dati dei server; aggiunti CHANGELOG e ROADMAP
- feat: email di contatto della pagina iniziale e responsabile del trattamento dell'informativa privacy configurabili da `.env` (`LURCH_CONTATTO_EMAIL`, `LURCH_PRIVACY_RESPONSABILE`, segnaposto `{responsabile}`)
- feat: lo userscript GAIA bridge è servito con il dominio dell'installazione che lo distribuisce (v1.11.2 dello script)
- fix: il testo d'aiuto dei template privacy elenca i segnaposto realmente disponibili ({indirizzo}, {email}, {responsabile})

## 1.85.16 — 2026-11-02

- fix: aggiungi intervallo giorni evento andava in errore 500 (modalSubmitAction restituiva true) e "Incolla da GAIA" compila il testo del direttore

## 1.85.15 — 2026-10-30

- feat: eventi - elenco nominativo dei pasti (sezione + Excel) e conflitti di date azzerati dal ritiro

## 1.85.14 — 2026-10-25

- fix: esami - finestra della nota del docente centrata nella PWA

## 1.85.13 — 2026-10-25

- feat: esami - confronto tra docenti su tutti i laboratori del discente, con le note dei colleghi

## 1.85.12 — 2026-10-24

- fix: esami - sintesi laboratori nel titolo del discente e note aperte dalla «i» nel riepilogo itinere

## 1.85.11 — 2026-10-24

- fix: esami - scarto alto tra docenti più visibile nel riepilogo itinere

## 1.85.10 — 2026-10-24

- feat: esami - confronto tra docenti nei laboratori, note del direttore in modale e colori rispetto al LAP

## 1.85.9 — 2026-10-24

- feat: esami - annotazione libera del docente su schede laboratorio e itinere, letta dal direttore

## 1.85.8 — 2026-10-23

- fix: esami - il menu Valutazioni non torna a Gestione dopo un'azione modale

## 1.85.7 — 2026-10-23

- fix: esami - pagina Domande test apribile in bozza dai gestori

## 1.85.6 — 2026-10-21

- fix: esami - ripristinata la scala 1-5 e i descrittori originali dell'autovalutazione

## 1.85.5 — 2026-10-20

- refactor: esami - Prova finale divisa in Autovalutazione (colloquio) e Prova pratica, menu Valutazioni riordinato

## 1.85.4 — 2026-10-20

- feat: esami - pannello in due sezioni Gestione/Valutazioni, import JSON del test scritto e mescolamento domande/risposte

## 1.85.3 — 2026-10-20

- fix: dev:anonimizza-dati - l'anteprima conta solo le righe ancora da anonimizzare. corsi (direttore_corso) e log_modifiche_email contavano tutte le righe anche su un DB già anonimizzato, sembrava lavoro da fare

## 1.85.2 — 2026-10-20

- refactor: icone menu senza duplicati, URL patenti-cri, rimozione qualifiche_staff e comando di pulizia patenti

## 1.85.1 — 2026-10-20

- feat: login PWA con codice OTP fisso solo fuori produzione

## 1.85.0 — 2026-10-20

- feat: corsi - documento aggiuntivo richiesto ai discenti. Switch sul corso con dicitura libera dell'admin, upload PDF obbligatorio (max 10 MB) nel form di pre-registrazione (link email e PWA), attivabile anche a form già compilati (il discente carica dalla pagina di conferma/app, l'admin può caricare o sostituire al suo posto cancellando il vecchio file), verifica con anteprima, annulla verifica e filtro in Gestione corso

## 1.84.28 — 2026-10-20

- feat: esami - Passo 11 chiuso, modulo completo

## 1.84.25 — 2026-10-19

- feat: esami - Passo 10 esito, chiusura, riapertura entro 15 giorni, export PDF/CSV, non ammesso

## 1.84.24 — 2026-10-19

- feat: esami - Passo 9 test scritto: punteggio uguale per tutte le domande (senza penalità 1 punto per giusta e tutte obbligatorie, con penalità tre valori a 1 decimale), avvio per discente reversibile fino all'invio con token che invalida i moduli aperti, salvataggio a ogni tocco e invio unico (vincolo DB + compare-and-set), il discente non riceve mai risposte corrette né punteggio; tab «Test scritto» nel pannello (domande in bozza, solo refusi dopo l'avvio), PDF cartaceo per discente senza corrette, interruttore «fa il test su carta» con motivo e inserimento a mano del direttore; documentazione esami aggiornata in modo additivo (C26–C30)
- feat: esami - Passo 9 test scritto verificato e chiuso (scheda v1.20, 11 passi su 13): punteggio uguale per tutte le domande (senza penalità 1 punto per giusta e tutte obbligatorie, con penalità tre valori a 1 decimale), avvio per discente reversibile fino all'invio con token che invalida i moduli aperti, salvataggio a ogni tocco e invio unico (vincolo DB + compare-and-set), il discente non riceve mai risposte corrette né punteggio; tab «Test scritto» nel pannello (domande in bozza, solo refusi dopo l'avvio), PDF cartaceo per discente senza corrette, interruttore «fa il test su carta» con motivo e inserimento a mano del direttore; modale di conferma centrata; documentazione esami aggiornata in modo additivo (C26–C30)

## 1.84.20 — 2026-10-19

- feat: esami - prova finale richiusa: le schede personali di colloquio e prova pratica restano visibili in sola lettura (pannello, riepilogo e app per chi le ha scritte), con proposta e confronto per il direttore; riaprendo si riprende a inserire e modificare
- feat: esami - prova finale richiusa: le schede personali di colloquio e prova pratica restano visibili in sola lettura (pannello, riepilogo e app per chi le ha scritte), con proposta e confronto per il direttore; riaprendo si riprende a inserire e modificare. Documentazione esami allineata in modo additivo (testo superato barrato + nota) e correzioni rimandate verificate; promemoria informativo sull'area Crescita nelle schede itinere personali e finale

## 1.84.19 — 2026-10-19

- feat: dev:anonimizza-dati copre anche JSON delle risposte, notifiche e sessioni, ed è idempotente

## 1.84.18 — 2026-10-19

- feat: esami - Aggiornato il riepilogo della prova finale per includere i nomi di chi ha compilato la prova pratica e ottimizzato la query per il recupero dei membri

## 1.84.17 — 2026-10-19

- feat: esami - Passo 8: colloquio e prova pratica. Schede personali dalla PWA (voto intero + impressione; semaforo a 28 voci) per direttore e docenti di commissione, schede finali dal pannello solo per il direttore (colloquio con un decimale e proposta troncata; prova pratica precompilata), interruttore «Apri la prova finale», non ammessi esclusi, controllo di completezza
- feat: esami - Passo 8: colloquio e prova pratica. Schede personali dalla PWA (voto intero + impressione; semaforo a 28 voci) per direttore e docenti di commissione, schede finali dal pannello solo per il direttore (colloquio con un decimale e proposta troncata; prova pratica precompilata), interruttore «Apri la prova finale» con sezione in cima al dettaglio esame, non ammessi esclusi, controllo di completezza

## 1.84.16 — 2026-10-18

- feat: esami - Passo 7: riepilogo itinere (schede personali di tutti i docenti) e scheda finale d'area del direttore, lettura per commissione/admin in pannello e PWA, vedeScheda unica regola di lettura
- feat: esami - Passo 7 chiuso dopo le verifiche: riepilogo itinere (schede personali di tutti i docenti) e scheda finale d'area del direttore, lettura per commissione/admin in pannello e PWA, vedeScheda unica regola di lettura, scheda 1.17

## 1.84.14 — 2026-10-18

- feat: esami - elenco itinere con ricerca per nome/cognome e riepilogo per discente (voti, aree compilate, totale su massimo)

## 1.84.13 — 2026-10-18

- fix: esami - scheda itinere: totale su massimo e aree compilate, media d'area visibile sull'accordion chiuso, tolto "(appunto)" dal totale

## 1.84.12 — 2026-10-18

- feat: esami - PWA scheda itinere (tutti i docenti, tutti i discenti non ritirati, solo le proprie schede), medie d'area dal motore

## 1.84.11 — 2026-10-18

- fix: esami - bottoni della scheda laboratorio in ordine rosso, giallo, verde

## 1.84.10 — 2026-10-18

- feat: esami - PWA scheda laboratorio a semaforo (solo discenti del proprio gruppo), semaforo a 28 voci

## 1.84.9 — 2026-10-18

- feat: esami - pagina Gestione con riepilogo, Modifica solo parametri base, commissione in modale, riporta in bozza senza dati

## 1.84.8 — 2026-10-18

- fix: ripristino dump dev con verifica reale dell'anonimizzazione, documentata la colonna orfana catalogo_corsi.virtuale

## 1.84.7 — 2026-10-18

- feat: esami TCRI passo 4 - socio corrente, permessi per ruolo d'esame e sezione Esami nella PWA; decisioni D30-D34 su schede personali e finali

## 1.84.5 — 2026-10-17

- feat: esami TCRI passo 3 verificato, gruppi per laboratorio più guidati, modelli d'esame caricati dal deploy e ripristino dump dal PC

## 1.84.4 — 2026-10-16

- feat: dev:anonimizza-dati usabile su ogni DB di sviluppo, con analisi e conferma obbligatoria

## 1.84.3 — 2026-10-16

- feat: modulo Esami TCRI, passo 3 - pannello esame, commissione, discenti, laboratori e gruppi
- feat: modulo Esami TCRI, passo 3 - pannello esame, commissione, discenti, laboratori e gruppi

## 1.84.1 — 2026-10-16

- feat: modulo Esami TCRI, motore di calcolo (passo 2) e direttore/presidente/docenti sul corso (passo P)

## 1.84.0 — 2026-10-15

- feat: modulo Esami TCRI, passo 1 - struttura dati (19 tabelle nuove), modelli e modello TCRI v1 come JSON versionato con seeder

## 1.83.9 — 2026-10-04

- feat: email info evento e link form mostrano i giorni in presenza del corso (o i giorni evento per lo staff) invece del range inizio-fine

## 1.83.8 — 2026-10-04

- fix: sovrapposizione corsi valutata sui giorni in presenza in comune invece che sui range inizio-fine

## 1.83.7 — 2026-10-03

- fix: navigatori giorno, finestra check-in, scadenza badge e notti camere basati sui giorni reali (giorniOperativi/giorniInPresenza) invece del range inizio-fine

## 1.83.6 — 2026-10-03

- fix: liste date presenze corso e riepilogo disponibilità staff costruite sui giorni reali, non sul range

## 1.83.5 — 2026-10-01

- feat: azione "Modifica disponibilità" sulle candidature staff per correggere slot/tipologia/corsi

## 1.83.4 — 2026-10-01

- feat: export Excel discenti dalla Gestione Corso, tipo/comitato volontario-dipendente centralizzato su Socio

## 1.83.3 — 2026-09-29

- feat: colonna EMAIL nell'export Excel delle Qualifiche

## 1.83.2 — 2026-09-23

- fix: colori/icone giorni_presenza sbagliati e "Azzera tutte le risposte" con stato non valido

## 1.83.1 — 2026-09-23

- feat: toggle "Includi dipendenti" nella ricerca Soci, allineata semantica estensione a Qualifiche

## 1.83.0 — 2026-09-22

- feat: pannello di ricerca per Soci con filtri estratti dalla tabella + fix crash export Qualifiche in estensione

## 1.82.0 — 2026-09-22

- feat: filtro "Giovani" e primo export Excel sulla pagina Soci

## 1.81.5 — 2026-09-22

- fix: colonna "Età" nella ricerca qualifiche mostrava decimali invece di anni interi

## 1.81.4 — 2026-09-22

- fix: aggiungi intervallo controlla la coerenza prima del conteggio, oltre 15gg è un avviso bloccante senza conferma forzabile

## 1.81.3 — 2026-09-22

- fix: pillole giorni evento raggruppate visivamente data+X, etichette Dal/Al non disallineano più il gruppo intervallo

## 1.81.2 — 2026-09-22

- fix: pillole giorni evento cancellano solo dalla X e altezza allineata tra i due gruppi 'Aggiungi'

## 1.81.1 — 2026-09-22

- feat: elenco giorni evento a pillole con aggiunta singola/intervallo, dedup, limiti 5/15gg

## 1.81.0 — 2026-09-22

- feat: filtro "Giovani" (<32 anni attuale/ingresso) e colonne nascita/ingresso/età nella ricerca qualifiche

## 1.80.0 — 2026-09-22

- feat: gestione giorni evento riscritta (elenco date + intervalli, dedup/ordinamento) e giorni corso come pillole selezionabili

## 1.79.5 — 2026-09-21

- feat: modale 'Crea evento singolo' permette di scegliere i giorni evento e precompila i giorni in presenza del corso

## 1.79.4 — 2026-09-21

- fix: 'Crea evento singolo' dal corso non valorizzava giorni_evento (NOT NULL) e andava in errore

## 1.79.3 — 2026-09-21

- fix: azione 'Rimuovi iscritto' diventa 'Ritira' quando il form è già compilato

## 1.79.2 — 2026-09-17

- fix: stesso bug del conteggio info-evento anche nella gestione staff

## 1.79.1 — 2026-09-17

- fix: bottoni bulk hand-rolled in gestione-corso non applicavano il blocco corso annullato

## 1.79.0 — 2026-09-17

- feat: corso annullabile (reversibile), escluso dai previsti evento e dagli invii email

## 1.78.1 — 2026-09-17

- fix: anteprima sollecito preregistrazione mostrava scadenza +7gg invece di +1gg

## 1.78.0 — 2026-09-16

- feat: autocertificazione attività obbligatoria configurabile su Graduatorie

## 1.77.3 — 2026-09-14

- fix: uniforma copy-to-click di email/CF/telefono nelle liste iscritti e staff

## 1.77.2 — 2026-09-14

- fix: sollecito con scadenza +1gg reinviabile, form singolo non re-inviabile; aggiunto telefono cliccabile nelle liste iscritti/staff

## 1.77.1 — 2026-09-13

- fix: filtri disponibilità docenti/logistica non restituivano risultati

## 1.77.0 — 2026-09-13

- feat: filtri tabella candidature staff

## 1.76.4 — 2026-09-13

- fix: pulsante Chiudi nell'anteprima PDF non funzionante nelle modali

## 1.76.3 — 2026-09-11

- refactor: centralizza priorità volontario/dipendente in Socio::isVolontario/isDipendente

## 1.76.2 — 2026-09-11

- fix: priorità comitato di appartenenza su dipendente in tipoSocio/comitatoSocio

## 1.76.1 — 2026-09-11

- feat: comitato/ruolo (volontario-dipendente) visibili anche nelle griglie candidati/esclusi

## 1.76.0 — 2026-09-11

- feat: comitato/tipo (volontario-dipendente) in export graduatoria, blocco se candidati non collegati a socio

## 1.75.0 — 2026-09-09

- feat: QR di check-in inline nella comunicazione evento via email

## 1.74.1 — 2026-09-08

- fix: filtro docenza per corso mancante in Gestione staff — FK evento_id nell'eager-load

## 1.74.0 — 2026-09-08

- feat: barre filtro nelle liste hub Gestione staff e Gestione corso

## 1.73.2 — 2026-09-07

- fix: conversione patenti GAIA — tipi gia-2024 (2b, 7b) passano invariati

## 1.73.1 — 2026-09-05

- fix: allineamento pillole/icone stato documenti in Gestione staff e Gestione corso

## 1.73.0 — 2026-09-05

- feat: sostituzione documenti caricati finché non verificati (ricevuta/nullaosta/attestazione) — admin + pubblico + PWA

## 1.72.3 — 2026-09-04

- refactor: form modifica socio allineato alle convenzioni layout

## 1.72.2 — 2026-09-04

- refactor: scheda socio piu compatta (patenti + pasti), sezione salute nascosta dal form

## 1.72.1 — 2026-09-04

- feat: import patenti GAIA anche nei flussi batch (comitato/graduatoria) + dedup + riepilogo separato

## 1.72.0 — 2026-09-04

- feat: import patenti CRI dal curriculum GAIA + catalogo allineato al modello 2024

## 1.71.2 — 2026-09-02

- feat: backup.sh — versione app nei nomi dei file (db-vX.Y.Z.sql.gz, files-vX.Y.Z.tar.gz)

## 1.71.1 — 2026-09-02

- chore: output piu chiaro per corsi:pulisci-form-sections (pasto+data+motivo invece di sole date)

## 1.71.0 — 2026-09-02

- feat: modello nullaosta staff precompilato + anteprima PDF con pdf.js locale

## 1.70.2 — 2026-09-02

- feat: comando corsi:pulisci-form-sections per ripulire le voci pasto fantasma dei corsi congelati (una-tantum)

## 1.70.1 — 2026-09-02

- fix: check-in e presenze effettive saltano le voci pasto attivo=false (corsi congelati con form_sections vecchie)

## 1.70.0 — 2026-09-02

- feat: pasti/pernotti per giorni in presenza del corso + form PWA allineati ai pubblici

## 1.69.1 — 2026-09-01

- feat: colonna CF click-to-copy nelle griglie candidati graduatoria

## 1.69.0 — 2026-09-01

- feat: guide operatori pubbliche (/guide) + comando anonimizzazione dati dev

## 1.68.6 — 2026-09-01

- chore: merge-dev-to-main non fa piu checkout, working tree sporco non blocca

## 1.68.5 — 2026-09-01

- feat: contatti email/telefono degli esclusi in export ed anteprima graduatoria

## 1.68.4 — 2026-08-31

- fix: qualifiche soci — toggle 'Includi soci in estensione' ora funziona anche per admin_locale/operatori scoped (era escluso dallo scope base)

## 1.68.3 — 2026-08-31

- fix: anteprima attestazione graduatoria renderizzata con pdf.js su canvas invece che iframe — indipendente dalle impostazioni PDF del browser

## 1.68.2 — 2026-08-31

- feat: anteprima attestazione graduatoria in pagina dedicata autenticata invece del link diretto

## 1.68.1 — 2026-08-31

- fix: popup conferma allineamento con direttive Blade in chiaro + colonna Login utenti poco chiara

## 1.68.0 — 2026-08-31

- feat: allineamento soci da import GAIA — segna dimessi / pulisce estensioni per ambito, reversibile

## 1.67.8 — 2026-08-30

- fix: consenso dati salute e allergeni nascosti finché non si seleziona un pasto + data pagamento non futura

## 1.67.7 — 2026-08-27

- feat: import qualifiche GAIA scrive anche soci in estensione (canEditQualifiche)

## 1.67.6 — 2026-08-27

- feat: colora ammessi nell'export Excel graduatoria e allinea azioni riga candidati esclusi

## 1.67.5 — 2026-08-26

- feat: verifica titolo di studio graduatorie salvata in modo permanente sul socio

## 1.67.4 — 2026-08-26

- fix: riepilogo pasti Gestione Corso scoped al corso, non all'evento

## 1.67.3 — 2026-08-26

- fix: rifinitura UX verifica titolo di studio graduatorie

## 1.67.2 — 2026-08-26

- feat: link Apri scheda socio nella tabella risultati Qualifiche

## 1.67.1 — 2026-08-26

- feat: link Apri su GAIA nella tabella risultati Qualifiche

## 1.67.0 — 2026-08-26

- feat: prerequisito titolo di studio su graduatorie con autodichiarazione e verifica manuale GAIA

## 1.66.2 — 2026-08-25

- fix: anteprima badge usa lo stesso font del PDF, wrap/posizioni ora coerenti

## 1.66.1 — 2026-08-25

- fix: etichette e spiegazione più chiare per i parametri badge in Modifica evento

## 1.66.0 — 2026-08-25

- feat: script restore-db.sh con blocco produzione e conferma nome DB

## 1.65.1 — 2026-08-25

- fix: titolo badge non troncato e sottotitolo duplicato in gestione candidature

## 1.65.0 — 2026-08-25

- feat: anteprima badge in modale e titolo evento configurabile sul badge

## 1.64.2 — 2026-08-25

- feat: pulsante Modifica graduatoria nel menu Azioni di Gestione candidati

## 1.64.1 — 2026-08-25

- feat: contatori staff totale/docenza/logistica in gestione staff

## 1.64.0 — 2026-08-25

- feat: alias nomi corso nel catalogo per matching import GAIA

## 1.63.1 — 2026-08-25

- fix: recap consensi form corsista app mancava riga qualifiche GAIA

## 1.63.0 — 2026-08-25

- feat: rotazione comitati a due modalità, codice pubblico graduatoria svincolato dal protocollo

## 1.62.1 — 2026-08-25

- fix: bottoni export pagina Qualifiche fuori convenzione, spostati in ActionGroup header

## 1.62.0 — 2026-08-25

- feat: nullaosta obbligatorio per staff Logistica, verifica admin, redesign azioni gestione staff/corso

## 1.61.5 — 2026-08-24

- feat: titolo tab browser mostra il dato del record, tolto suffisso brand

## 1.61.4 — 2026-08-24

- fix: import iscritti/staff saltava righe silenziosamente per email_vecchia NOT NULL

## 1.61.3 — 2026-08-24

- fix: invio massivo form/sollecito/info si riattiva per i nuovi destinatari

## 1.61.2 — 2026-08-22

- fix: transaction+email pre-registrazione, rimozione qualifiche/patenti form, uniforma Docenza/Logistica

## 1.61.1 — 2026-08-22

- fix: errore 500 nel salvataggio pre-registrazione staff per chi è anche corsista

## 1.61.0 — 2026-08-22

- feat: conferma bulk candidature staff + uniforma form pre-registrazione

## 1.60.14 — 2026-08-19

- fix: blocca invio form/sollecito se il form non è generato

## 1.60.13 — 2026-08-19

- feat: db lurch_test dedicato ai test + link Gestione corso in Riepilogo

## 1.60.12 — 2026-08-19

- fix: anteprima form, comunicazione evento/staff, anteprima PDF

## 1.60.8 — 2026-08-18

- fix: raggruppa Modifica/Apri curriculum in ActionGroup su ViewSocio

## 1.60.7 — 2026-08-18

- fix: bottoni GAIA scheda socio, un solo installa + link curriculum

## 1.60.6 — 2026-08-18

- fix: gerarchia fonte qualifiche soci negli import

## 1.60.5 — 2026-08-18

- fix: titolo export Excel graduatoria sballato per colonna A stretta

## 1.60.4 — 2026-08-18

- fix: riconosci qualifiche GAIA "Certificato" + segnala righe senza data

## 1.60.3 — 2026-08-18

- fix: pulsante userscript unico su Candidati graduatoria

## 1.60.2 — 2026-08-18

- fix: ActionGroup su GestisciCandidati rompeva la pagina

## 1.60.1 — 2026-08-18

- fix: convenzione pulsanti header + niente maiuscolo cognomi export graduatoria

## 1.60.0 — 2026-08-18

- feat: export Excel classifica graduatoria
- feat: completa export Excel classifica graduatoria

## 1.59.0 — 2026-08-18

- feat: riconciliazione qualifiche rimosse su GAIA in fase di import

## 1.58.20 — 2026-08-16

- fix: bottoni GAIA spostati fuori dal pannello Qualifiche lazy, sull'header pagina socio

## 1.58.19 — 2026-08-16

- chore: manutenzione interna, nessuna modifica funzionale

## 1.58.18 — 2026-08-16

- fix: bottoni GAIA cliccabili verso il .user.js durante il controllo versione (userscript v1.10.9)

## 1.58.17 — 2026-08-16

- feat: ricerca per codice nella select qualifica + pulsante Ricalcola scadenze

## 1.58.16 — 2026-08-16

- fix: Aggiungi/Modifica qualifica invisibile su pagina socio anche per superadmin

## 1.58.15 — 2026-08-16

- fix: deploy resiliente a riscritture npm di package.json/lock

## 1.58.14 — 2026-08-16

- feat: link scheda socio anche nella modale dettagli dei candidati esclusi

## 1.58.13 — 2026-08-16

- fix: package-lock.json che si riscriveva su dev/prod ad ogni deploy

## 1.58.12 — 2026-08-16

- feat: link diretto alla scheda socio nella modale dettagli candidato graduatoria

## 1.58.11 — 2026-08-14

- fix: allinea docs/deploy.md al cambio server (Hestia anche in produzione) e Node 24

## 1.58.10 — 2026-08-13

- fix: progresso import GAIA un passo indietro invece di un flash

## 1.58.9 — 2026-08-13

- fix: conteggio qualifiche nel progresso import GAIA non veniva mai mostrato

## 1.58.8 — 2026-08-13

- feat: mostra numero qualifiche trovate nel progresso import GAIA

## 1.58.7 — 2026-08-13

- fix: gating invio form iniziale + rilevamento sessione GAIA scaduta

## 1.58.5 — 2026-08-13

- fix: tooltip su bottoni invio sollecito/comunicazione disabilitati

## 1.58.4 — 2026-08-13

- fix: disabilita invio solleciti/comunicazioni per corsi ed eventi conclusi

## 1.58.3 — 2026-08-13

- feat: apre bridge GAIA ad admin_locale, scoped al proprio comitato

## 1.58.2 — 2026-08-13

- fix: bottone Nuovo su Catalogo Corsi visibile ad admin_locale

## 1.58.0 — 2026-08-12

- feat: consenso GAIA e verifica email su pre-registrazione, contatti bloccati

## 1.57.12 — 2026-08-12

- fix: sub-nav Corsi, invii massivi escludono ritirati, quote/reset form

## 1.57.11 — 2026-08-12

- fix: Presenze live/Check-in azioni riga e blocchi alimentari, ricerca CF

## 1.57.10 — 2026-08-12

- fix: camere data cast UTC shift, tie-break ordinamento presenze effettive

## 1.57.9 — 2026-08-12

- fix: audit Graduatorie/Qualifiche priorità bassa, CreateAction verde ovunque, fix screenshot path

## 1.57.8 — 2026-08-12

- fix: audit Graduatorie/Qualifiche priorità media, permessi eliminazione qualifiche per fonte

## 1.57.7 — 2026-08-12

- fix: audit Graduatorie/Qualifiche priorità alta, breadcrumb standard, CreateAction in header tabella

## 1.57.6 — 2026-08-11

- fix: sub-nav e ActionGroup mancanti su Corsi, card comunicazioni, fix allineamento Ritirato

## 1.57.5 — 2026-08-11

- fix: Elimina rimosso dagli header Modifica (resta solo in lista con i controlli), refusi vari

## 1.57.3 — 2026-08-11

- fix: audit blocco Corsi completo, tabella ristrutturata, GestioneCorso/Quote a vere Action

## 1.57.2 — 2026-08-11

- fix: a capo non renderizzati nell'accordion informativa privacy sui form pubblici

## 1.57.1 — 2026-08-11

- fix: audit convenzioni Presenze/Candidature/Partecipanti/Report, fix ruolo docenza mai sincronizzato

## 1.54.0 — 2026-08-08

- feat: consenso privacy unificato per import qualifiche GAIA

## 1.53.0 — 2026-08-08

- feat: import GAIA batch multi-socio da graduatoria, solo admin regionale

## 1.52.1 — 2026-08-08

- fix: freeze pagina Qualifiche soci per fetch concorrenti non guardate nello userscript GAIA

## 1.52.0 — 2026-08-08

- feat: import GAIA batch multi-socio per comitato locale

## 1.51.0 — 2026-08-07

- feat: catalogo alimentare a 4 categorie, comitati json in root data

## 1.50.2 — 2026-08-06

- feat: elimina candidature duplicate per CF sbagliato negli esclusi graduatoria

## 1.50.1 — 2026-08-06

- fix: userscript GAIA bridge non rilevava aggiornamenti disponibili per cache HTTP

## 1.50.0 — 2026-08-06

- feat: riordino menu Archivi/Impostazioni, permessi admin_locale su Utenti/Corsi/Eventi, qualifiche.manage per operatori

## 1.49.0 — 2026-08-06

- feat: bottone Cerca ID GAIA su scheda socio, ricalcolo scadenze, colori bottoni

## 1.48.0 — 2026-08-06

- feat: import qualifiche CRI da curriculum GAIA via userscript bridge

## 1.47.1 — 2026-08-06

- fix: testi landing pubblica troppo stretti, ora sfruttano tutta la larghezza disponibile

## 1.47.0 — 2026-08-06

- feat: redesign landing page pubblica con hero, funzionalità e contatto protetto

## 1.46.0 — 2026-08-05

- feat: griglia esclusi senza card duplicata, punteggi manuali e attestazione anche lì

## 1.45.7 — 2026-08-05

- fix: fallback duplicati per equivalenza corso nei propedeutici graduatorie

## 1.45.6 — 2026-08-05

- fix: SocioEvento::firstOrCreate() ignorava il ruolo nella ricerca

## 1.45.5 — 2026-08-05

- feat: transizioni libere stato candidatura staff, fix rimozione staff orfana

## 1.45.4 — 2026-08-05

- fix: PostgreSQL locale senza password su connessioni localhost

## 1.45.3 — 2026-08-05

- fix: verifica email reattiva nella modale, CF bloccato dopo aggancio socio

## 1.45.2 — 2026-08-05

- fix: verifica email confrontava "mai verificata" invece dell'indirizzo attuale

## 1.45.1 — 2026-08-05

- fix: email nei Dettagli candidatura di sola lettura finché non si clicca Modifica

## 1.45.0 — 2026-08-05

- feat: verifica email multi-target, esterno staff, GAIA protegge email verificata

## 1.44.1 — 2026-08-05

- fix: colonna anagrafica e correggi CF su candidature staff

## 1.44.0 — 2026-08-05

- feat: storico ritiri/ripristini candidature graduatoria + tab socio

## 1.43.0 — 2026-08-05

- feat: conferma candidatura staff crea IscrizioneStaff

## 1.42.1 — 2026-08-04

- fix: disabilita temporaneamente Conferma candidature staff

## 1.42.0 — 2026-08-04

- feat: candidatura pubblica staff con ruolo docente e corsi

## 1.41.0 — 2026-08-03

- feat: candidature pubbliche staff, giorni evento non contigui, dedup pasti/pernotti

## 1.40.4 — 2026-08-03

- fix: link/dettagli candidati esclusi sempre a sinistra

## 1.40.3 — 2026-08-03

- feat: link GAIA anche negli scartati, azioni pericolose raggruppate a tendina

## 1.40.2 — 2026-08-03

- feat: ricerca su Equivalente a/Corsi equivalenti/Scadenze in Catalogo Corsi

## 1.40.1 — 2026-08-03

- feat: colonna codice qualifica in Gestione Qualifiche socio

## 1.40.0 — 2026-08-03

- feat: nome_visibile, ereditarietà scadenza MAX+esonero, fix validazione codici

## 1.39.3 — 2026-08-02

- feat: toggle "gestito manualmente" per corsi catalogo esclusi dal sync GAIA

## 1.39.1 — 2026-08-02

- feat: opzione --only-db in scripts/backup.sh

## 1.39.0 — 2026-08-02

- feat: punteggio manuale +/- per candidato graduatoria

## 1.38.0 — 2026-08-02

- feat: ricerca automatica ID profilo GAIA per candidati graduatoria

## 1.37.5 — 2026-08-01

- feat: disclaimer pre-chiusura graduatoria con elenco qualifiche da riesportare/importare

## 1.37.4 — 2026-08-01

- feat: modale dettagli candidatura anche per candidati esclusi/ritirati

## 1.37.3 — 2026-07-31

- feat: ordinamento colonne Comitato ed Esito in tabella candidati graduatorie

## 1.37.2 — 2026-07-31

- fix: allinea posizione/ammissione classifica con rotazione + ordinamento tabella candidati

## 1.37.1 — 2026-07-31

- fix: ripristina pulsante invio verifica email + rinomina criterio in report

## 1.37.0 — 2026-07-31

- feat: sidebar collassabile + modale dettagli candidatura graduatorie

## 1.36.4 — 2026-07-31

- feat: abilita multi-sessione pannello admin, ciascuna con scadenza indipendente

## 1.36.3 — 2026-07-30

- feat: sessione pannello admin durata 1 settimana + sessione unica per utente

## 1.36.2 — 2026-07-29

- feat: estrazione automatica coordinate da link Google Maps incollato

## 1.36.1 — 2026-07-29

- fix: redirect bloccato in creazione evento per repeater camere con item fantasma

## 1.36.0 — 2026-07-23

- feat: attestazione presidente con punteggio graduatoria verificato manualmente

## 1.35.1 — 2026-07-21

- fix: sposta i candidati ritirati nella griglia esclusi graduatorie

## 1.35.0 — 2026-07-21

- feat: calcolo classifica graduatorie (punteggio/ammissibilità/selezione) + correzione CF candidati in attesa

## 1.34.2 — 2026-07-14

- fix: previeni doppio submit sul form codice login email (419 spacciato per scadenza)

## 1.34.1 — 2026-07-13

- fix: scarta date nel futuro nell'import qualifiche + semplifica messaggio login email

## 1.34.0 — 2026-07-12

- feat: correlazione User-Socio opzionale + login pannello via email/OTP di default

## 1.33.0 — 2026-07-12

- feat: protezione email verifica candidature, ritiro e modifica contatti graduatorie

## 1.29.2 — 2026-07-12

- refactor: protocollo come identificativo principale nella lista graduatorie

## 1.29.1 — 2026-07-12

- fix: npm run build mancante dal workflow deploy, CSS pubblico fermo a mesi fa

## 1.29.0 — 2026-07-12

- fix: timezone Europe/Rome fissato nel codice, contatto formazione, tipo graduatoria in EmailTemplateResource

## 1.28.2 — 2026-07-12

- fix: anteprima email graduatoria non apriva modal + copia link corretto + accordion privacy

## 1.28.1 — 2026-07-12

- feat: email conferma graduatoria via EmailTemplate + link copiabile + anteprima

## 1.28.0 — 2026-07-12

- feat: form pubblico iscrizione graduatoria — oscuramento contatti + verifica email

## 1.27.1 — 2026-07-12

- refactor: criteri di selezione graduatorie più snelli da compilare

## 1.27.0 — 2026-07-12

- feat: criteri di selezione configurabili per graduatoria

## 1.26.1 — 2026-07-12

- fix: scadenzaPerRegione crashava per dipendenti/estensioni senza appartenenza volontario

## 1.26.0 — 2026-07-11

- feat: fonte dedicata per qualifiche create dalla cascata "crea base se assente"

## 1.25.3 — 2026-07-11

- feat: filtro stato scadenza (tutte/valide/scadute) in ricerca Qualifiche

## 1.25.2 — 2026-07-11

- fix: box Automazioni corso subito sotto Giorni in presenza, senza gap

## 1.25.1 — 2026-07-11

- fix: layout Automazioni corso in box separato + azioni compatte lista graduatorie

## 1.25.0 — 2026-07-11

- feat: automazioni Crea evento singolo e Crea Graduatoria per corso

## 1.24.4 — 2026-07-11

- feat: migliora UX form graduatoria - corso collegato e date native

## 1.24.3 — 2026-07-11

- fix: form graduatoria usa tutta la larghezza + pulsante incolla da GAIA

## 1.24.2 — 2026-07-11

- fix: GraduatoriaResource usa Filament\Actions invece di Tables\Actions

## 1.24.0 — 2026-07-11

- feat: aggiungi sistema Graduatorie con form pubblico di iscrizione

## 1.22.0 — 2026-07-11

- feat: aggiungi corsi aggregatori virtuali per obblighi soddisfatti da corsi equivalenti

## 1.21.8 — 2026-07-11

- refactor: rimuove CatalogoCorsiResolver inutilizzato, usa Str::slug() in EditEvento

## 1.21.7 — 2026-06-14

- fix: hook globale Livewire per stoppar polling su sessione scaduta (419)

## 1.21.6 — 2026-06-03

- feat: statistiche check-in su report evento + checkout di massa + QR disabilitato post-evento

## 1.21.3 — 2026-06-01

- feat: luogo_nascita su soci + export pernottanti con dati anagrafici completi

## 1.21.2 — 2026-06-01

- fix: griglia slot universale nel modal modifica check-in, solo pasti/pernotti

## 1.21.1 — 2026-06-01

- fix: slot pasti/pernotti completi nel modal modifica check-in

## 1.21.0 — 2026-06-01

- feat: pagina tutti i check-in con modifica risposte + toolbar presenze live

## 1.20.3 — 2026-06-01

- fix: export pernottanti check-in in formato Excel invece di CSV

## 1.20.2 — 2026-06-01

- feat: export e anteprima pernottanti check-in nella pagina Report

## 1.20.1 — 2026-06-01

- fix: accordion report chiusi di default

## 1.20.0 — 2026-06-01

- feat: pagina Report evento con snapshot check-in per pasto/pernotto

## 1.19.9 — 2026-06-01

- feat: accordion snapshot check-in per pasto/pernotto in presenze effettive

## 1.19.8 — 2026-06-01

- fix: totale iscritti al corso include ritirati

## 1.19.7 — 2026-06-01

- feat: ora server + validazione timestamp futuro nel form check-in

## 1.19.6 — 2026-06-01

- feat: badge check-in anche per ritirati nella gestione corso

## 1.19.5 — 2026-06-01

- feat: contatori gestione corso aggiornati con ritirati e totale iscritti

## 1.19.4 — 2026-06-01

- feat: badge check-in verde/rosso nella lista iscritti del corso

## 1.19.3 — 2026-06-01

- feat: modifica dati checkin da PresenzeLive + rimuovi presenze attese

## 1.19.1 — 2026-05-31

- feat: esigenze alimentari prima nei pasti, nascoste per pernotto, rimossa colazione corrente

## 1.19.0 — 2026-05-31

- feat: attesi non arrivati per pasto + direttiva produzione sempre attiva

## 1.18.9 — 2026-05-31

- feat: liste confermati/non confermati per pasto nella pagina presenze effettive

## 1.18.8 — 2026-05-31

- feat: accordion staff deduplicato nella pagina presenze effettive

## 1.18.7 — 2026-05-31

- feat: ospiti evento nella pagina e nell'export partecipanti

## 1.18.6 — 2026-05-30

- fix: presenti include chi ha checkin attivo da giorni precedenti senza uscita

## 1.18.5 — 2026-05-30

- feat: corsi del giorno con lista iscritti e badge check-in collassabile

## 1.18.4 — 2026-05-30

- feat: contatore previsti (da form) accanto agli effettivi

## 1.18.3 — 2026-05-30

- feat: colazione domani derivata dai pernotti confermati stanotte

## 1.18.2 — 2026-05-30

- fix: esigenze alimentari collassabili per visione d'insieme

## 1.18.1 — 2026-05-30

- fix: rimosso Carbon::clamp() inesistente in GestionePresenzeEffettive

## 1.18.0 — 2026-05-30

- feat: pagina presenze effettive per giorno con griglia pasti/pernotti
- refactor: converti presenze-effettive in pagina Filament admin

## 1.17.4 — 2026-05-30

- fix: rimuovi campo CF dal login PWA, solo email per OTP

## 1.17.3 — 2026-05-29

- feat: pre-riempi form utente dopo "Salva & nuovo" con valori precedente

## 1.17.2 — 2026-05-29

- feat: messaggi anonimi da scan badge QR + sezione messaggi nella PWA

## 1.17.1 — 2026-05-27

- fix: badge PDF escludono iscritti ritirati e ordinati per cognome

## 1.17.0 — 2026-05-27

- feat: soft delete iscritti (stato ritirato) con gestione budget completa

## 1.16.4 — 2026-05-26

- feat: aggiunti email e telefono nell'export Excel partecipanti evento

## 1.16.3 — 2026-05-26

- feat: colonna com. regionale + export Excel in pagina Partecipanti evento

## 1.16.2 — 2026-05-26

- feat: sistema check-in evento-centrico via SocioEvento + form ospiti

## 1.15.1 — 2026-05-25

- feat: pernotti discenti raggruppati per corso con sub-accordion

## 1.15.0 — 2026-05-25

- feat: app PWA — home unificata per evento e hub con form per corso

## 1.14.8 — 2026-05-25

- fix: reset form cancella tutte le presenze (admin+form) e bloccato se ricevuta presente

## 1.14.7 — 2026-05-25

- feat: form pubblico già compilato → redirect a pagina di conferma (privacy dati)

## 1.14.6 — 2026-05-25

- fix: tre card consenso separate nell'ordine corretto — identico al form pubblico

## 1.14.5 — 2026-05-25

- fix: consenso salute come card separata sempre visibile — allineato al form pubblico

## 1.14.4 — 2026-05-25

- feat: privacy app form con collapsibile e PDF — stessa logica form pubblici

## 1.14.3 — 2026-05-25

- feat: pulsanti seleziona tutto/deseleziona per pasti e disponibilità staff nei form app

## 1.14.0 — 2026-05-25

- feat: portale app — hub evento, form preregistrazione corsista e staff

## 1.12.0 — 2026-05-24

- feat: gestione camere per notte in modifica evento

## 1.11.2 — 2026-05-24

- feat: anteprima documenti allegati in modifica evento e anteprima mail

## 1.11.1 — 2026-05-24

- feat: storico presenze completo con filtri data e nome/CF

## 1.11.0 — 2026-05-24

- feat: sistema check-in/check-out presenze evento
