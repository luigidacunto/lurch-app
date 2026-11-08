# LURCH — Setup ambiente di sviluppo

Guida per configurare l'ambiente locale su **Windows** o **Linux (Ubuntu/Debian)**.

Per il deploy su server di produzione vedi [deploy.md](deploy.md).
Per backup e disaster recovery vedi [backup-restore.md](backup-restore.md).

---

## Indice

- [Windows](#windows)
- [Linux](#linux)
- [Primo accesso e credenziali](#primo-accesso-e-credenziali)
- [Test PHPUnit](#test-phpunit)
- [Strumenti opzionali](#strumenti-opzionali)

---

## Windows

### 1. Installa i prerequisiti di sistema

| Strumento | Versione | Come installare |
|-----------|----------|-----------------|
| PHP | 8.4 | [windows.php.net/download](https://windows.php.net/download/) — Thread Safe x64; oppure via Scoop: `scoop install php` |
| Composer | 2.x | [getcomposer.org/download](https://getcomposer.org/download/) |
| Node.js | 20+ | [nodejs.org](https://nodejs.org/) |
| PostgreSQL | 15+ | [postgresql.org/download/windows](https://www.postgresql.org/download/windows/) — installare con l'installer EDB |

### 2. Abilita le estensioni PHP richieste

Apri il file `php.ini` (trovarlo con `php --ini` in PowerShell) e decommentare le righe seguenti (rimuovere il `;` iniziale):

```ini
extension=mbstring   ; richiesto da Laravel/Str::studly()
extension=exif       ; dipende da mbstring, abilitare insieme
extension=curl       ; richiesto da Composer e dipendenze HTTP
extension=openssl    ; richiesto da Composer per HTTPS e da Laravel per cifratura
extension=fileinfo   ; richiesto da flysystem, phpspreadsheet, spatie/medialibrary
extension=gd         ; richiesto da simplesoftwareio/simple-qrcode (badge QR)
extension=intl       ; richiesto da filament/support
extension=zip        ; richiesto da openspout/openspout e phpspreadsheet (Excel)
extension=pdo_pgsql  ; driver PostgreSQL per Laravel
extension=pgsql      ; idem
```

**Attivazione rapida via PowerShell** — abilita tutte e 10 in un colpo:

```powershell
$ini = (php --ini | Select-String "Loaded Configuration" | ForEach-Object { ($_ -split ":\s+")[1].Trim() })
$content = Get-Content $ini -Raw
foreach ($ext in 'mbstring','exif','curl','openssl','fileinfo','gd','intl','zip','pdo_pgsql','pgsql') {
    $content = $content -replace ";extension=$ext", "extension=$ext"
}
[System.IO.File]::WriteAllText($ini, $content)
```

> `[System.IO.File]::WriteAllText` scrive UTF-8 senza BOM — necessario perché PHP su Windows rifiuta il file con BOM.

Verifica che le estensioni siano attive:

```powershell
php -m | Select-String "mbstring|curl|openssl|fileinfo|gd|intl|zip|pdo_pgsql"
```

### 3. Crea il database PostgreSQL

Apri una finestra PowerShell e avvia psql come superuser `postgres`:

```powershell
psql -U postgres
```

> **Problema noto — `psql` non riconosciuto:** l'installer EDB a volte non aggiunge il `bin`
> al PATH di sistema anche se la spunta era selezionata. Tre opzioni:
>
> **A — fix permanente** (eseguire PowerShell come **amministratore**, poi riavviare PowerShell):
> ```powershell
> [System.Environment]::SetEnvironmentVariable(
>     "PATH",
>     [System.Environment]::GetEnvironmentVariable("PATH","Machine") + ";C:\Program Files\PostgreSQL\18\bin",
>     "Machine"
> )
> ```
> Sostituire `18` con la versione installata (verificabile con `Get-ChildItem "C:\Program Files\PostgreSQL"`).
>
> **B — percorso completo per la sessione corrente** (senza riavviare):
> ```powershell
> $env:PATH += ";C:\Program Files\PostgreSQL\18\bin"
> psql -U postgres
> ```
>
> **C — pgAdmin** (installato insieme a PostgreSQL): apri Query Tool sul server locale
> e incolla i comandi SQL sotto.

Una volta dentro il prompt `postgres=#`, esegui:

```sql
CREATE USER lurch_user WITH PASSWORD 'scegli_una_password';
CREATE DATABASE lurch OWNER lurch_user;
\c lurch
GRANT ALL ON SCHEMA public TO lurch_user;
\q
```

**Secondo database, dedicato ai test automatici** (`lurch_test` — vedi "Test PHPUnit" più sotto per il perché): stessa procedura, un `CREATE DATABASE` in più.

```sql
CREATE DATABASE lurch_test OWNER lurch_user;
\c lurch_test
GRANT ALL ON SCHEMA public TO lurch_user;
\q
```

#### Connessioni locali senza password (consigliato per lo sviluppo)

Di default PostgreSQL chiede la password anche per connessioni da `localhost`
(`scram-sha-256`), il che blocca comandi non interattivi (script, restore da
Bash, tool automatici) in attesa di un prompt che non arriva mai. Per lo
sviluppo locale conviene passare a `trust` sulle sole connessioni locali —
Postgres è comunque raggiungibile solo dalla stessa macchina, quindi non è un
rischio di sicurezza in un ambiente di sviluppo.

Modifica `pg_hba.conf` (percorso tipico Windows:
`C:\Program Files\PostgreSQL\<versione>\data\pg_hba.conf`) e cambia
`scram-sha-256` in `trust` sulle righe `local`/`127.0.0.1`/`::1`:

```
local   all             all                                     trust
host    all             all             127.0.0.1/32            trust
host    all             all             ::1/128                 trust
```

Poi ricarica la configurazione (**serve una sessione PowerShell da
amministratore** — il file è in `Program Files` e il segnale di reload
richiede privilegi elevati):

```powershell
Restart-Service postgresql-x64-18
# oppure, più leggero (nessun riavvio del processo):
& "C:\Program Files\PostgreSQL\18\bin\pg_ctl.exe" reload -D "C:\Program Files\PostgreSQL\18\data"
```

Da questo momento `psql -U postgres` e `psql -U lurch_user` funzionano senza
chiedere password su `127.0.0.1`/`localhost` — utile per gli script di questa
guida e per il restore in [backup-restore.md](backup-restore.md).

> `\q` esce da psql e torna a PowerShell.

> **Problema noto — code page Windows (850 vs 1252):** psql su Windows mostra spesso questo avviso:
> ```
> ATTENZIONE: Il code page della console (850) differisce dal code page di Windows (1252).
> ```
> Se la password contiene caratteri speciali (accenti, `@`, `!`, ecc.), il terminale può
> codificarli diversamente da come PostgreSQL li memorizza — causando `password authentication failed`
> anche se la password sembra corretta. **Usare solo caratteri ASCII** (lettere, numeri, `_`, `-`)
> nelle password create da riga di comando psql, oppure impostare la password tramite **pgAdmin**
> dove l'encoding è gestito correttamente.

### 4. Configura l'accesso SSH all'host git (solo per chi fa push)

Per clonare il repository pubblico basta HTTPS (passo 5). Per lavorare su un fork o su un repo privato via SSH, per evitare
prompt di username/password ad ogni `git pull`/`push` — e senza salvare un token in chiaro —
si usa una chiave SSH dedicata a questa macchina, **senza passphrase** (comoda per un uso
interattivo quotidiano; se preferisci una passphrase puoi ometterla dal comando e verrà
richiesta a ogni utilizzo).

```powershell
# 1. Genera una chiave dedicata a questo PC (nessuna passphrase: -N vuoto)
ssh-keygen -t ed25519 -C "lurch-windows-dev" -f "$env:USERPROFILE\.ssh\lurch_windows" -N '""'

# 2. Mostra la chiave pubblica da registrare
Get-Content "$env:USERPROFILE\.ssh\lurch_windows.pub"
```

Copia l'intera riga stampata (inizia con `ssh-ed25519 ...`) e registrala tra le chiavi SSH del
tuo utente sull'host git (GitHub: **Settings** → **SSH and GPG keys**; Forgejo: **Impostazioni** →
**Chiavi SSH / GPG**). A differenza della deploy key usata sui server (vedi `deploy.md`, sola lettura e
legata a un solo repo), questa va registrata come **chiave dell'utente**, non come deploy key
di un singolo repo: è la tua chiave personale per lavorare su tutti i repo a cui hai accesso da
questo PC, con gli stessi permessi del tuo account (push incluso).

Poi crea/estendi `~/.ssh/config` (`$env:USERPROFILE\.ssh\config`) per dire a SSH di usare
questa chiave con questo host:

```
Host <host-git>
    IdentityFile ~/.ssh/lurch_windows
    IdentitiesOnly yes
```

Testa la connessione (non deve chiedere nulla, risponde con un messaggio di benvenuto):

```powershell
ssh -T git@<host-git>
```

> **Nota porta SSH:** se l'host git usa una porta non standard (es. `2222` invece di `22`),
> aggiungi `Port 2222` nello stesso blocco `Host` di `~/.ssh/config`.

**Parametri usati in questo progetto:**

| Parametro | Valore |
|---|---|
| Tipo chiave | `ed25519` |
| File chiave privata | `~/.ssh/lurch_windows` |
| File chiave pubblica | `~/.ssh/lurch_windows.pub` |
| Passphrase | nessuna |
| Host in `~/.ssh/config` | `<host-git>` |
| Dove registrarla | Impostazioni utente → Chiavi SSH/GPG (non Deploy Key del repo) |
| URL remoto da usare dopo | `git@<host-git>:<owner>/lurch-app.git` |

### 5. Clona il repository e installa le dipendenze

```powershell
git clone https://github.com/luigidacunto/lurch-app.git lurch
cd lurch
composer install
npm install
```

> Se il repo è già clonato via HTTPS e vuoi solo passare a SSH senza riclonare:
> ```powershell
> git remote set-url origin git@<host-git>:<owner>/lurch-app.git
> git fetch origin   # test — non deve chiedere credenziali
> ```

> **Problema noto — `npm` bloccato da execution policy:** PowerShell blocca per default l'esecuzione
> di script `.ps1` (incluso `npm.ps1`), causando `PSSecurityException`. Due opzioni:
>
> **Solo utente corrente** (non richiede admin):
> ```powershell
> Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
> ```
>
> **Tutti gli utenti della macchina** (richiede PowerShell aperta come **amministratore**):
> ```powershell
> Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope LocalMachine
> ```
>
> Poi riesegui `npm install`. `RemoteSigned` permette script locali ma blocca script
> scaricati da internet non firmati — è il compromesso sicuro standard per sviluppo.

### 6. Configura l'ambiente

```powershell
cp .env.example .env
php artisan key:generate
```

Apri `.env` e imposta almeno:

```env
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=lurch
DB_USERNAME=lurch_user
DB_PASSWORD=scegli_una_password

SUPERADMIN_EMAIL=admin@lurch.local
SUPERADMIN_PASSWORD=password

QUEUE_CONNECTION=database
```

### 7. Esegui migrazioni e seeder

**Opzione A — schema pulito + dati di esempio** (default):

```powershell
php artisan migrate
php artisan db:seed
```

**Opzione B — ripristino da dump sviluppo** (se hai un `db.sql.gz` con dati reali anonimizzati):

Vedi la sezione **"Import dump in sviluppo locale"** in [backup-restore.md](backup-restore.md).

**Dopo aver ripristinato un dump con dati reali** su un DB di sviluppo (PC, portatile o VM), anonimizzalo subito:

```powershell
php artisan dev:anonimizza-dati
```

Mostra quante righe verranno modificate per tabella e chiede sempre conferma; si rifiuta di girare con `APP_ENV=production`. È irreversibile (per riavere i dati reali si ripristina di nuovo il dump). Il tuo utente superadmin resta intatto, quindi il login con la solita email funziona.

> ⚠️ In caso di import da dump: **non eseguire `migrate` né `db:seed`** — il dump contiene già
> schema e dati completi. Eseguirli su un DB già ripristinato corrompe i dati.

> **Problema noto — `password authentication failed`:** la password in `.env` (`DB_PASSWORD`) non
> corrisponde a quella impostata su PostgreSQL durante il `CREATE USER`. Due opzioni:
>
> **A — ricordi la password:** apri `.env` e correggi `DB_PASSWORD` con il valore esatto usato.
>
> **B — reimposta la password su PostgreSQL:**
> ```powershell
> & "C:\Program Files\PostgreSQL\18\bin\psql.exe" -U postgres -c "ALTER USER lurch_user WITH PASSWORD 'nuova_password';"
> ```
> Poi aggiorna `DB_PASSWORD=nuova_password` nel `.env` e riprova.

> **Problema noto — `could not find driver` (pgsql):** significa che `pdo_pgsql` non è caricato
> da PHP nonostante lo step 2. Verificare con `php -m | Select-String "pgsql"` — se non appare,
> abilitare manualmente:
> ```powershell
> $ini = (php --ini | Select-String "Loaded Configuration" | ForEach-Object { ($_ -split ":\s+")[1].Trim() })
> $content = Get-Content $ini -Raw
> foreach ($ext in 'pdo_pgsql','pgsql') {
>     $content = $content -replace ";extension=$ext", "extension=$ext"
> }
> [System.IO.File]::WriteAllText($ini, $content)
> php -m | Select-String "pgsql"   # deve mostrare pdo_pgsql e pgsql
> ```
> Poi rieseguire `php artisan migrate`.

### 8. Compila gli asset frontend

```powershell
npm run build
```

### 9. Avvia il server

Apri **due terminali separati**:

```powershell
# Terminale 1 — web server
php artisan serve

# Terminale 2 — queue worker (necessario per import Excel e job background)
php artisan queue:work
```

Il pannello è disponibile su **http://127.0.0.1:8000/admin**.

---

## Linux

Istruzioni per Ubuntu 22.04 / Debian 12. Adatta i comandi `apt` se usi una distro diversa.

### 1. Installa PHP 8.4 e le estensioni

```bash
sudo add-apt-repository ppa:ondrej/php   # Ubuntu — per avere PHP 8.4
sudo apt update
sudo apt install php8.4 php8.4-fpm php8.4-cli \
    php8.4-mbstring php8.4-curl php8.4-openssl \
    php8.4-fileinfo php8.4-gd php8.4-intl php8.4-zip \
    php8.4-pgsql php8.4-pdo-pgsql php8.4-xml php8.4-bcmath
```

Verifica:

```bash
php -m | grep -E "mbstring|curl|openssl|fileinfo|gd|intl|zip|pdo_pgsql"
```

### 2. Installa Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 3. Installa Node.js 20+

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

### 4. Installa PostgreSQL

```bash
sudo apt install postgresql postgresql-contrib
sudo systemctl enable --now postgresql
```

### 5. Crea il database

```bash
sudo -u postgres psql <<'SQL'
CREATE USER lurch_user WITH PASSWORD 'scegli_una_password';
CREATE DATABASE lurch OWNER lurch_user;
\c lurch
GRANT ALL ON SCHEMA public TO lurch_user;
SQL
```

Secondo database, dedicato ai test automatici (vedi "Test PHPUnit" più sotto):

```bash
sudo -u postgres psql <<'SQL'
CREATE DATABASE lurch_test OWNER lurch_user;
\c lurch_test
GRANT ALL ON SCHEMA public TO lurch_user;
SQL
```

### 6. Clona il repository e installa le dipendenze

```bash
git clone <url-repo> lurch
cd lurch
composer install
npm install
```

### 7. Configura l'ambiente

```bash
cp .env.example .env
php artisan key:generate
```

Modifica `.env` con gli stessi valori della sezione Windows (paragrafo 5), cambiando se necessario `DB_PASSWORD`.

### 8. Esegui migrazioni e seeder

```bash
php artisan migrate
php artisan db:seed
```

Vedi lo step 6 Windows per l'opzione di ripristino da dump.

### 9. Compila gli asset frontend

```bash
npm run build
```

### 10. Avvia il server

Apri **due terminali separati**:

```bash
# Terminale 1 — web server
php artisan serve

# Terminale 2 — queue worker
php artisan queue:work
```

Il pannello è disponibile su **http://127.0.0.1:8000/admin**.

---

## Primo accesso e credenziali

Vai su `http://127.0.0.1:8000/admin/login`.

**Credenziali default** (`.env` con valori di esempio):

| Campo | Valore |
|-------|--------|
| Email | `admin@lurch.local` |
| Password | `password` |

Le credenziali sono definite da `SUPERADMIN_EMAIL` e `SUPERADMIN_PASSWORD` nel `.env`.
Per aggiornarle senza toccare gli altri dati:

```bash
php artisan db:seed --class=SuperadminSeeder
```

---

## Ripristinare un dump nel database di sviluppo

Il database di sviluppo può stare in locale o su una macchina di sviluppo condivisa: conta solo la connessione nel `.env`. Lo script qui sotto usa quelle credenziali.

**Ripristinare un dump, dal PC** (da Git Bash, nella cartella del progetto):

```bash
bash scripts/restore-db-dev.sh /percorso/db-vX.Y.Z.sql.gz
```

Chiede di riscrivere il nome del database per conferma, poi sostituisce **solo i dati**: la struttura resta quella già aggiornata al codice, quindi anche un dump di una versione più vecchia si ripristina senza dover rilanciare `migrate`. Lavora in un'unica transazione: se qualcosa va storto il DB resta com'era. Svuota tutte le tabelle tranne `migrations`, anche quelle che il dump non contiene. Subito dopo lancia `php artisan dev:anonimizza-dati`.

---

## Test PHPUnit

`phpunit.xml` punta a un **secondo database dedicato**, `lurch_test` (Postgres, non SQLite in-memory) — creato al passo 3/5 sopra. Motivo: alcune funzioni Postgres usate nel progetto (es. `gen_random_uuid()`) non esistono su SQLite, quindi la suite non gira affidabilmente lì. `lurch_test` è pensato per essere rotto/svuotato/ricreato liberamente — ogni test usa `RefreshDatabase` (migrazioni + transazione per test, rollback automatico), non tocca mai `lurch` (il database di sviluppo, tenuto allineato ai dati reali per un test finale delle migrazioni prima del deploy — vedi [backup-restore.md](backup-restore.md)).

```bash
php artisan test
# oppure, per un solo file/gruppo:
php artisan test --filter=NomeDelTest
```

Se `lurch_test` finisce in uno stato inconsistente (migrazioni a metà, dati residui da un test interrotto), ricrealo da zero — nessun dato da preservare:

```bash
psql -U postgres -c "DROP DATABASE IF EXISTS lurch_test;"
psql -U postgres -c "CREATE DATABASE lurch_test OWNER lurch_user;"
psql -U postgres -d lurch_test -c "GRANT ALL ON SCHEMA public TO lurch_user;"
```

---

## Strumenti opzionali

### Test E2E automatizzati

```bash
npm install
npx playwright install chromium   # scarica il browser (~120 MB), una-tantum

npm test              # tutti i test headless
npm run test:ui       # UI interattiva di Playwright
npm run test:headed   # browser visibile durante i test
```

Richiede `php artisan serve` attivo. Configurazione in `playwright.config.js`.
