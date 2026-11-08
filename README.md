# L.U.R.C.H.
**Logistica Unificata Registrazione Corsi e Hospitality**

> *Dalla registrazione al check-in, senza stress*

Applicazione web per la gestione completa di eventi formativi e campi scuola (campi scuola, corsi), progettata per organizzazioni strutturate su base territoriale (comitati regionali e locali). Gestisce l'intero ciclo di vita dell'evento: dalla creazione alla preregistrazione dei partecipanti, dal check-in in segreteria alla raccolta del feedback finale.

---

## Documentazione tecnica

- [docs/README.md](docs/README.md) — guide operative (setup, deploy, backup) e guide per gli operatori
- [CHANGELOG.md](CHANGELOG.md) — modifiche per versione
- [ROADMAP.md](ROADMAP.md) — sviluppi previsti
- [CONTRIBUTING.md](CONTRIBUTING.md) — come contribuire · [SECURITY.md](SECURITY.md) — segnalare una vulnerabilità · [LICENSE](LICENSE) — AGPL-3.0

---

## Indice

- [Panoramica](#panoramica)
- [Funzionalità principali](#funzionalità-principali)
- [Struttura territoriale](#struttura-territoriale)
- [Utenti e permessi](#utenti-e-permessi)
- [Flusso corsisti](#flusso-corsisti)
- [Flusso staff](#flusso-staff)
- [Portale mobile (PWA)](#portale-mobile-pwa)
- [Form di preregistrazione dinamico](#form-di-preregistrazione-dinamico)
- [Check-in in segreteria](#check-in-in-segreteria)
- [Gestione email e code](#gestione-email-e-code)
- [Questionario di gradimento](#questionario-di-gradimento)
- [Stack tecnologico](#stack-tecnologico)
- [Architettura del sistema](#architettura-del-sistema)
- [Installazione](#installazione)
- [Marchi e loghi](#marchi-e-loghi)
- [Contribuire e sicurezza](#contribuire-e-sicurezza)
- [Licenza](#licenza)

---

## Panoramica

La piattaforma nasce per rispondere alle esigenze operative delle organizzazioni che gestiscono eventi formativi multi-corso, con partecipanti importati da sistemi esterni (es. GAIA), staff da coordinare e una segreteria che opera in loco durante l'evento.

Il sistema copre tre profili di utilizzo distinti:

- **Operatori di segreteria** — pannello web per gestire eventi, importare iscritti, inviare comunicazioni e gestire il check-in
- **Partecipanti (corsisti e staff)** — portale mobile PWA per consultare le proprie informazioni e completare la preregistrazione
- **Amministratori** — gestione territoriale di utenti, permessi e configurazioni

---

## Funzionalità principali

### Gestione eventi e corsi
- Creazione di eventi con date, sede, indirizzo, coordinate geografiche e programma giornaliero
- Allegamento di cartine e mappe della location
- Creazione di corsi all'interno di ogni evento con date, orari, docente e numero posti
- Configurazione per ogni corso: se è residenziale (pernotto previsto) e se richiede pagamento

### Import partecipanti da file Excel
- Caricamento liste iscritti esportate da GAIA in formato XLSX
- Match univoco basato sul codice fiscale — identificatore stabile nel tempo
- Gestione automatica del cambio email: se il CF è già presente con email diversa, l'email viene aggiornata e la modifica viene tracciata nel log
- Precaricamento suggerito di allergie e intolleranze da partecipazioni precedenti

### Preregistrazione personalizzata
- Form dinamico generato automaticamente in base agli orari e alla tipologia del corso
- Link univoco inviato via email a ogni partecipante — nessun account richiesto per compilarlo
- Raccolta di: allergie, intolleranze, consenso privacy GDPR, autorizzazione foto/video, upload PDF ricevuta pagamento (se previsto dal corso)
- Al termine: email di conferma con riepilogo dati e QR code personale allegato

### Check-in in segreteria
- Scansione QR code o ricerca per codice fiscale
- Form precompilato con i dati inseriti in preregistrazione
- Conferma o modifica di: pasti previsti (pranzo/cena per ogni giorno), pernotto per ogni notte del corso, tipo di sistemazione
- I dati confermati al check-in diventano visibili nel portale del partecipante

### Portale mobile PWA
- Applicazione web installabile su smartphone (Android e iOS) senza passare dagli store
- Accesso tramite codice OTP inviato via email — nessuna password da ricordare
- Visualizzazione di eventi, corsi, orario del campo, cartine, dati logistici e QR code personale
- Sessione lunga per tutta la durata dell'evento una volta eseguito l'accesso

### Questionario di gradimento
- Questionario post-evento configurabile con domande personalizzate
- Link univoco per ogni partecipante, risposte salvate in forma completamente anonima
- Report aggregato disponibile nel pannello di gestione

---

## Struttura territoriale

L'applicazione è organizzata su due livelli territoriali:

```
Comitati Regionali
  └── Comitati Locali
```

Ogni evento appartiene a un solo comitato regionale. Gli operatori sono assegnati a eventi specifici e vedono solo i dati di quell'evento. I dati non si incrociano tra regioni diverse — ogni livello gestisce solo ciò che è di propria competenza.

La lista dei comitati (regionali e locali) è un'anagrafica di riferimento unica per tutto il sistema, aggiornata per competenza:

| Chi | Cosa può gestire |
|-----|-----------------|
| Superadmin | Tutti i comitati regionali e locali |
| Admin regionale | Solo i comitati locali della propria regione |
| Operatore | Nessuna modifica all'anagrafica comitati |

---

## Utenti e permessi

### Tre ruoli nel pannello backend

**Superadmin**
Unico nel sistema. Gestisce comitati, qualifiche globali e admin regionali. Può intervenire su qualsiasi regione per sbloccare problemi operativi. Nel pannello, i dati sensibili (CF, telefono, email, indirizzo) dei partecipanti di altre regioni sono oscurati.

**Admin regionale**
Creato dal superadmin, assegnato a un comitato regionale. Gestisce gli operatori del proprio comitato, gli eventi e i comitati locali della propria regione. Non accede ai dati di altre regioni.

**Operatore**
Creato da un admin regionale. Può essere assegnato a più eventi. I permessi sono granulari e individuali — ogni funzione può essere attivata o disattivata per ogni operatore singolarmente, senza livelli gerarchici fissi.

### Permessi granulari operatori

I permessi non sono a livelli (es. "livello 1 include livello 2") ma assegnazioni individuali on/off:

| Permesso | Funzione |
|----------|----------|
| `eventi.view/create/edit` | Visualizza e gestisce eventi |
| `corsi.view/create/edit` | Visualizza e gestisce corsi |
| `iscritti.view/import/edit` | Gestisce la lista corsisti |
| `iscritti.view_sensitive` | Vede CF, telefono, email, indirizzo |
| `staff.view/import/edit` | Gestisce la lista staff |
| `email.send` | Invia email di preregistrazione |
| `checkin.manage` | Gestisce il check-in all'evento |
| `report.view` | Visualizza report e statistiche |
| `questionario.manage` | Crea e gestisce questionari |

### Oscuramento dati sensibili

I campi codice fiscale, telefono, indirizzo ed email vengono oscurati nell'interfaccia del pannello per gli utenti che non hanno il permesso `iscritti.view_sensitive`. Il superadmin vede oscurati i dati dei partecipanti di regioni diverse dalla propria.

---

## Flusso corsisti

```
1. Operatore importa XLSX da GAIA nel corso
          ↓
2. Sistema fa match per codice fiscale
   ├── Nuovo CF → crea partecipante
   ├── CF esistente, email uguale → nessuna modifica
   └── CF esistente, email diversa → aggiorna email + log
          ↓
3. Crea iscrizione con token UUID univoco
          ↓
4. Operatore invia email preregistrazione (manuale, quando vuole)
          ↓
5. Partecipante compila form via link personale /form/{token}
   (senza account — solo il link)
          ↓
6. Sistema invia email di conferma con QR code allegato
          ↓
7. Giorno dell'evento: check-in in segreteria con QR o CF
          ↓
8. Segreteria conferma pasti/pernotto nel form check-in
          ↓
9. Dati visibili nel portale mobile del partecipante
          ↓
10. Post-evento: link questionario gradimento (anonimo)
```

### Gestione cambio email

Il codice fiscale è l'identificatore stabile. Se un partecipante viene importato con una email diversa da quella già presente nel sistema per quel CF, l'email viene aggiornata automaticamente e la modifica viene registrata nel log con data, vecchia email, nuova email e fonte (import o modifica manuale admin). In caso di problemi di accesso, il partecipante contatta il supporto e l'admin corregge l'email dal pannello.

---

## Flusso staff

Lo staff segue un flusso analogo ai corsisti con alcune differenze:

- L'import XLSX carica lo staff a livello di evento (non di corso specifico)
- Nel form di preregistrazione è presente una sezione aggiuntiva per indicare i **giorni di disponibilità** (checkbox per ogni giorno dell'evento)
- Il form di check-in mostra i giorni dichiarati invece delle sezioni corso
- Nel portale mobile lo staff vede le informazioni dell'evento ma non quelle di un corso specifico

### Survey pubblica staff *(sviluppo futuro)*

Sarà disponibile una pagina pubblica per raccogliere le disponibilità di potenziali staffisti prima dell'evento. Per il primo utilizzo, lo staff viene caricato tramite import XLSX.

---

## Portale mobile (PWA)

Il portale è un'applicazione web progressiva (PWA) installabile su smartphone come se fosse un'app nativa, senza passare da App Store o Google Play.

### Accesso tramite OTP

Non esistono password. L'accesso avviene sempre tramite codice OTP:

1. Il partecipante inserisce la propria email
2. Riceve un'email con un codice di 6 cifre (valido 15 minuti)
3. Inserisce il codice e accede
4. La sessione rimane attiva per tutta la durata dell'evento + 7 giorni se si accede dalla PWA installata, oppure per 2 ore se si accede da browser

Dopo 3 tentativi errati il codice viene bloccato per 10 minuti.

### Cosa vede ogni partecipante

Tutti i partecipanti (corsisti, staff, operatori fisici) vedono:
- Gli eventi a cui sono registrati
- Orario giornaliero del campo
- Sede, indirizzo e link alla mappa
- Cartine della location
- I propri dati inseriti in preregistrazione
- I dati confermati al check-in
- Il proprio QR code scaricabile

I corsisti vedono in aggiunta:
- Il corso (o i corsi) a cui sono iscritti con descrizione e docente

### Operatori nel portale

Un operatore del pannello backend che partecipa fisicamente all'evento (es. come membro della segreteria) ha accesso al portale mobile con le stesse modalità OTP, come partecipante separato. Le due identità (backend e portale) sono distinte e indipendenti.

---

## Form di preregistrazione dinamico

Il form inviato ai partecipanti non è fisso ma viene generato automaticamente in base agli orari e alla configurazione del corso.

### Sezioni sempre presenti

- **Allergie e intolleranze** — checkbox per i principali allergeni (glutine, lattosio, uova, frutta secca, pesce, crostacei, soia, sedano, senape, sesamo) + campo testo libero. Se il partecipante ha già compilato form in passato, i dati vengono precaricati come suggerimento.
- **Consenso privacy GDPR** — obbligatorio, con testo personalizzabile per ogni evento
- **Autorizzazione foto e video** — opzionale, non blocca l'invio del form

### Sezioni condizionali

- **Upload PDF pagamento** — presente solo se il corso è configurato con pagamento previsto
- **Giorni di disponibilità** — presente solo per i partecipanti con ruolo staff

### Cosa NON è nel form di preregistrazione

Pranzi, cene e pernotti **non vengono chiesti nel form online**. Vengono gestiti direttamente al check-in in segreteria, dove la segreteria chiede e conferma verbalmente con il partecipante. Questo evita dati non aggiornati o dimenticati nella compilazione online.

### Anteprima e personalizzazione (pannello admin)

Dopo aver inserito date e orari del corso, l'operatore può:
- Visualizzare l'anteprima delle sezioni generate automaticamente
- Attivare o disattivare singole sezioni
- Modificare le etichette
- Aggiungere sezioni personalizzate (testo libero, sì/no, selezione)

Le modifiche vengono salvate e il form inviato ai partecipanti riflette esattamente quanto configurato.

---

## Check-in in segreteria

Il check-in avviene tramite il pannello web aperto su un browser dalla postazione di segreteria. L'operatore deve avere il permesso `checkin.manage` e deve essere assegnato all'evento.

### Identificazione del partecipante

- **Scansione QR code** — il partecipante mostra il QR ricevuto per email o visibile nel portale mobile. La scansione con qualsiasi lettore QR o fotocamera compila automaticamente il campo token.
- **Ricerca per codice fiscale** — alternativa per i casi in cui il QR non è disponibile, evitando che si perda l'accesso per mancanza del codice.

### Form di check-in

Il form si apre precompilato con tutti i dati già noti:

- Dati anagrafici (sola lettura)
- Allergie e intolleranze dalla preregistrazione (modificabili)
- Sezioni pasto e pernotto generate dinamicamente in base ai giorni del corso:
  - **Pranzo** — per ogni giorno in cui il corso copre l'orario di pranzo
  - **Cena** — per ogni giorno in cui il corso finisce dopo le 19:30 o per i giorni intermedi di un corso residenziale
  - **Pernotto** [notte X→Y] — per ogni notte del corso residenziale, con selezione tipo sistemazione
  - **Colazione** — per ogni mattina successiva a un pernotto

Tutte le voci sono pre-spuntate secondo le regole del corso e possono essere modificate dalla segreteria in base a quanto dichiarato dal partecipante all'arrivo.

### Sicurezza del check-in

Il form di check-in è accessibile solo da operatori autenticati con sessione attiva assegnati all'evento di riferimento. Se il token del QR appartiene a un evento non assegnato all'operatore, viene mostrata una pagina di errore 403. Questo impedisce che personale non autorizzato possa accedere ai dati di partecipanti di altri eventi.

---

## Gestione email e code

### Provider: Resend

Le email vengono inviate tramite Resend (piano gratuito: 3.000 email/mese, 100/giorno). L'utilizzo di un provider dedicato garantisce alta deliverability, SPF/DKIM/DMARC configurati e nessun rischio di finire in spam rispetto all'invio diretto dal server.

### Code con priorità

Le email vengono processate in background tramite tre code con priorità distinte:

| Coda | Contenuto | Priorità | Limite |
|------|-----------|----------|--------|
| `otp` | Codici OTP per il login | Alta | Nessuno — l'utente aspetta |
| `email_corso` | Form preregistrazione in massa | Media | 80/giorno |
| `default` | Questionari e comunicazioni varie | Bassa | 15/giorno |

### Invio preregistrazione — controllo operatore

L'invio delle email di preregistrazione **non è automatico** dopo l'import. L'operatore con permesso `email.send` sceglie quando e a chi inviare dal pannello, con queste opzioni:

- Invia a tutti (prima volta)
- Invia solo a chi non ha ancora ricevuto
- Reinvia a chi ha ricevuto ma non ha compilato
- Invia a una selezione manuale
- Reinvio singolo per problemi di consegna

Il pannello mostra in tempo reale: quante email sono state inviate, quante compilate, eventuali errori e una stima del tempo di completamento della coda.

---

## Questionario di gradimento

Al termine dell'evento è possibile inviare un questionario di gradimento configurabile:

- Le domande sono personalizzabili per ogni evento (testo libero, scala 1-5, scelta multipla)
- Ogni partecipante riceve un link univoco
- Le risposte vengono salvate in forma completamente anonima — nessun collegamento al partecipante
- I risultati aggregati sono consultabili nel pannello di gestione

---

## Stack tecnologico

| Componente | Tecnologia |
|------------|------------|
| Backend | PHP 8.4 + Laravel 12 |
| Database | PostgreSQL 18 |
| Frontend | Laravel Blade + Alpine.js + Tailwind CSS v4 |
| Pannello admin | Filament 5 |
| Permessi | Spatie Laravel Permission 6 |
| Import Excel | Maatwebsite Excel 3 |
| QR Code | simplesoftwareio/simple-qrcode 4 |
| Upload file | Spatie Media Library 11 |
| Email | Resend |
| PWA | manifest.json + Service Worker |
| Deploy | VPS Ubuntu 22.04, Nginx, PHP-FPM, Supervisor, Certbot |

---

## Architettura del sistema

```
┌─────────────────────────────────────────────────┐
│                   VPS Ubuntu                    │
│                                                 │
│  ┌─────────────┐    ┌──────────────────────┐   │
│  │    Nginx    │    │   Laravel 12         │   │
│  │  (reverse   │───▶│   ├── Filament 5     │   │
│  │   proxy)    │    │   │   (pannello)     │   │
│  └─────────────┘    │   ├── Portale PWA    │   │
│                     │   │   (/portale)     │   │
│  ┌─────────────┐    │   ├── Form pubblici  │   │
│  │  Certbot    │    │   │   (/form/{token})│   │
│  │  (HTTPS)    │    │   └── Check-in       │   │
│  └─────────────┘    │       (/checkin)     │   │
│                     └──────────┬───────────┘   │
│  ┌─────────────┐               │               │
│  │ Supervisor  │    ┌──────────▼───────────┐   │
│  │  (worker)   │───▶│   PostgreSQL 18      │   │
│  └─────────────┘    └──────────────────────┘   │
└─────────────────────────────────────────────────┘
                              │
                    ┌─────────▼──────────┐
                    │   Resend API       │
                    │   (email delivery) │
                    └────────────────────┘
```

### Tre aree applicative distinte

**Pannello backend** (`/admin`)
Accessibile solo agli utenti con account backend (superadmin, admin, operatori). Gestisce eventi, corsi, import, invio email e check-in. Richiede autenticazione email + password.

**Portale partecipanti** (`/portale`)
PWA mobile per corsisti, staff e operatori fisici. Accesso solo via OTP. Sola lettura — nessuna modifica possibile dai partecipanti.

**Form pubblici** (`/form/{token}`, `/checkin/{token}`, `/questionario/{token}`)
Accessibili tramite link univoco senza autenticazione (form preregistrazione e questionario) o tramite sessione operatore (check-in).

---

## Installazione

Per le istruzioni complete di installazione su Windows e Linux (sviluppo e produzione) vedi **[docs/setup-dev.md](docs/setup-dev.md)**.

### Prerequisiti minimi

| Strumento | Versione minima |
|-----------|-----------------|
| PHP | 8.4 |
| Composer | 2.x |
| Node.js | 20+ |
| PostgreSQL | 15+ |

### Avvio rapido (sviluppo locale)

```bash
git clone <url-repo> lurch && cd lurch
composer install
cp .env.example .env && php artisan key:generate
# → configura .env con DB e credenziali
php artisan migrate && php artisan db:seed
npm install && npm run build
php artisan serve        # terminale 1
php artisan queue:work   # terminale 2
# → http://127.0.0.1:8000/admin  (admin@lurch.local / password)
```

Per le istruzioni complete (estensioni PHP, setup PostgreSQL) vedi **[docs/setup-dev.md](docs/setup-dev.md)**; per il deploy in produzione vedi **[docs/deploy.md](docs/deploy.md)**.

---

## Moduli in sviluppo futuro

- **Survey pubblica staff** — pagina pubblica per raccogliere disponibilità di potenziali staffisti prima dell'evento
- **Spostamento operatori tra regioni** — procedura guidata con verifiche e conferme
- **Cifratura dati sensibili** — cifratura a livello database per CF, telefono e indirizzo
- **Dettaglio campi import staff** — definizione completa delle colonne del file Excel staff
- **Seeder qualifiche base** — lista qualifiche predefinita per i comitati che non creano una propria lista regionale

---

*Documentazione generata durante la fase di progettazione — aggiornata alla versione 1.0 del progetto.*

## Marchi e loghi

Il codice è rilasciato con licenza libera, ma i marchi, gli emblemi e i loghi della Croce Rossa e di altre organizzazioni **non fanno parte del software** e non sono inclusi nel repository: restano soggetti alle norme e ai regolamenti dei rispettivi titolari. Chi installa LURCH è responsabile di usare solo marchi di cui ha diritto.

---

## Contribuire e sicurezza

Le segnalazioni e le proposte sono benvenute: vedi [CONTRIBUTING.md](CONTRIBUTING.md). Per segnalare una vulnerabilità segui le indicazioni in [SECURITY.md](SECURITY.md).

---

## Licenza

Copyright © Luigi D'Acunto. Distribuito con licenza [GNU Affero General Public License v3.0 o successiva](LICENSE). In breve: puoi usare, studiare, modificare e ridistribuire il software; se offri una versione modificata come servizio online, devi rendere disponibile il codice delle tue modifiche con la stessa licenza.

---

<p align="center">
  <strong>L.U.R.C.H.</strong> — Logistica Unificata Registrazione Corsi e Hospitality<br>
  <em>Dalla registrazione al check-in, senza stress</em>
</p>
