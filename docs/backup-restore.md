# LURCH — Backup e Disaster Recovery

Guida operativa per backup, ripristino d'emergenza su server nuovo, e import dump in locale.

---

## Indice

- [Come funziona il backup](#come-funziona-il-backup)
- [Eseguire un backup manuale](#eseguire-un-backup-manuale)
- [Disaster Recovery — ripristino su server nuovo](#disaster-recovery--ripristino-su-server-nuovo)
- [Prova di ripristino su un DB separato](#prova-di-ripristino-su-un-db-separato)
- [Import dump in sviluppo locale](#import-dump-in-sviluppo-locale)

---

## Come funziona il backup

Lo script `scripts/backup.sh` crea due archivi in `~/lurch-backups/YYYYMMDD_HHMMSS/`.
Il nome dei file include la **versione dell'app** al momento del backup (letta da
`config/app.php` → `'version'`), così si sa subito a che release corrisponde ogni backup:

| File | Contenuto |
|------|-----------|
| `db-vX.Y.Z.sql.gz` | Dump completo PostgreSQL (schema **+** dati), compresso gzip |
| `files-vX.Y.Z.tar.gz` | Archivio `/var/www/lurch` esclusi `.git`, `node_modules`, `vendor` |

(Se la versione non è leggibile, il nome usa `-vunknown`.)

Il dump è prodotto con `pg_dump --no-owner --no-acl` in formato plain SQL.
Questo significa che:
- Include DDL (CREATE TABLE, indici, vincoli) **e** dati (COPY)
- I vincoli FK sono scritti **dopo** i dati — su DB vuoto il restore non genera errori
- Non lega il restore a uno specifico owner PostgreSQL

Vengono mantenuti automaticamente gli ultimi **10 backup**; i più vecchi vengono eliminati.

Con l'opzione `--only-db` viene creato solo il dump DB, saltando l'archivio file — utile
quando serve solo il dato (es. prima di un import/modifica massiva) e non ha senso
backuppare anche i file (nessuna modifica prevista lì).

---

## Eseguire un backup manuale

```bash
bash /var/www/lurch/scripts/backup.sh              # dump DB + archivio file
bash /var/www/lurch/scripts/backup.sh --only-db     # solo dump DB
```

Eseguire **sul server** (PostgreSQL non è esposto all'esterno). Output di esempio:

```
→ [1/2] Dump database...   ✓ db-v1.71.1.sql.gz  (4.2M)
→ [2/2] Archivio file...   ✓ files-v1.71.1.tar.gz  (18M)
  Backup salvato in: ~/lurch-backups/20260511_143022  (22M totali)
```

Il backup viene eseguito automaticamente anche all'inizio di ogni deploy tramite il workflow standard.

---

## Disaster Recovery — ripristino su server nuovo

Da eseguire quando il server è irrecuperabile e bisogna ripartire da zero.

### Prerequisiti

- Backup `db.sql.gz` e `files.tar.gz` disponibili (copiati dal backup precedente o da un'altra fonte)
- Server Linux Ubuntu 22.04 appena installato
- Repo Git accessibile

### Fase 1 — Setup server

Seguire la sezione **"Setup iniziale server"** di [deploy.md](deploy.md) fino al punto 3 incluso
(PHP, Nginx, PostgreSQL, Supervisor installati e configurati).

Creare utente e database **vuoto**:

```bash
sudo -u postgres psql <<'SQL'
CREATE USER lurch_user WITH PASSWORD 'la_password_di_produzione';
CREATE DATABASE lurch OWNER lurch_user;
\c lurch
GRANT ALL ON SCHEMA public TO lurch_user;
SQL
```

### Fase 2 — Deploy codice

```bash
cd /var/www
sudo git clone <url-repo> lurch
sudo chown -R www-data:www-data lurch
cd lurch
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data cp .env.example .env
# Modificare .env con i valori di produzione (DB_PASSWORD, APP_KEY, MAIL_*, ecc.)
sudo -u www-data php artisan key:generate
```

### Fase 3 — Ripristino database

> ⚠️ **Non eseguire `php artisan migrate`** — il dump contiene già schema e dati completi.
> Eseguire migrate su un DB già ripristinato sovrascrive/corrompe i dati.

```bash
gunzip -c /percorso/del/db.sql.gz | psql -U lurch_user -d lurch
```

**Oppure, con lo script dedicato** (fa drop+create+restore+verifica in un comando, legge le credenziali da `.env`):

```bash
bash scripts/restore-db.sh --file /percorso/assoluto/db.sql.gz
```

Il restore impiega alcuni minuti proporzionalmente alla dimensione del DB.
Al termine non devono comparire errori — se compaiono errori FK, il DB di destinazione
non era vuoto (vedere nota in fondo).

### Fase 4 — Ripristino file caricati

`files.tar.gz` contiene tutto `/var/www/lurch` incluso `storage/app` con i file degli utenti
(ricevute PDF, media Spatie). Senza questo restore, le iscrizioni con ricevuta
punteranno a file inesistenti.

```bash
sudo tar -xzf /percorso/del/files.tar.gz -C /
sudo chown -R www-data:www-data /var/www/lurch
sudo chmod -R 755 /var/www/lurch/storage
sudo chmod -R 755 /var/www/lurch/bootstrap/cache
```

### Fase 5 — Cache e avvio servizi

```bash
cd /var/www/lurch
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan filament:cache-components
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start lurch-worker:*
sudo systemctl reload nginx
```

### Fase 6 — Verifica

```bash
# Controlla che il worker sia attivo
sudo supervisorctl status lurch-worker:*

# Verifica accesso DB
sudo -u www-data php artisan tinker --execute="echo \App\Models\Socio::count() . ' soci';"

# Verifica log per errori
tail -50 /var/www/lurch/storage/logs/laravel.log
```

Accedere al pannello su `https://<dominio>/admin` e verificare che dati ed eventi siano presenti.

---

## Prova di ripristino su un DB separato

Serve a **verificare che un backup sia davvero ripristinabile** senza toccare il DB in uso. Da fare prima del primo uso reale del modulo Esami e poi periodicamente.

> ⚠️ **Non usare `scripts/restore-db.sh`** per questa prova: legge il database dal `.env` e farebbe drop+create di quello in uso. Si lavora a mano su un DB con **un altro nome**. Su Windows usare Git Bash (mai il pipe di PowerShell: rovina l'encoding).

1. Fai un backup (`bash scripts/backup.sh --only-db` sul server di produzione).
2. Su un server NON di produzione (staging o VM dev), con un utente PostgreSQL che può creare database:

```bash
createdb -U postgres lurch_restoretest
gunzip -c /percorso/db-vX.Y.Z.sql.gz | psql -U postgres -d lurch_restoretest -v ON_ERROR_STOP=1
```

3. Verifica i conteggi, confrontandoli con produzione al momento del backup (soci, corsi, esami e relative tabelle):

```bash
psql -U postgres -d lurch_restoretest -At -c "
select 'soci', count(*) from soci union all
select 'corsi', count(*) from corsi union all
select 'iscrizioni', count(*) from iscrizioni union all
select 'esami', count(*) from esami union all
select 'esiti', count(*) from esiti union all
select 'tcri_schede_itinere', count(*) from tcri_schede_itinere union all
select 'tcri_schede_semaforo', count(*) from tcri_schede_semaforo union all
select 'tcri_colloqui', count(*) from tcri_colloqui union all
select 'tcri_prove_pratiche', count(*) from tcri_prove_pratiche union all
select 'test_consegne', count(*) from test_consegne union all
select 'test_avvii', count(*) from test_avvii;"
```

4. Non lanciare `migrate` sul DB di prova: il dump contiene già schema e dati.
5. Elimina il DB di prova: `dropdb -U postgres lurch_restoretest`. Annota data, versione del backup e conteggi (per esempio nel registro della prova generale).

**Prova eseguita il 19/10/2026 sulla VM di sviluppo (v1.84.25):** dump del DB `webmaster_lurch` (1,1 MB compresso), ripristino in `lurch_restoretest` senza errori, conteggi identici (soci 11834, corsi 24, iscrizioni 367, esami 1, esiti 0, schede itinere 1), DB di prova eliminato. La prova sul dump di **produzione** resta da fare prima del primo uso reale (la lanci tu).

**Cosa copre il backup per il modulo Esami:** tutte le tabelle del modulo (`esami`, `esame_*`, `tcri_*`, `test_*`, `esiti`) sono nel dump di `pg_dump`: nessun elenco da aggiornare. I PDF/CSV d'esito e il PDF del test sono generati al volo e **non** salvati su disco: non c'è nulla da recuperare dai file.


---

## Import dump in sviluppo locale

Utile per sviluppare in locale su una copia dei dati della propria installazione (`db.sql.gz`
prodotto da `scripts/backup.sh`). Non esiste una procedura automatica di pull dal server.

> ⚠️ Un dump di produzione contiene dati personali reali: subito dopo il ripristino lancia
> `php artisan dev:anonimizza-dati`, e non condividere mai il file.

### Prerequisiti

- `php artisan serve` **non** deve essere in esecuzione durante l'import
- Il database locale deve essere **vuoto** (o ricreato da zero)

### Azzera il database esistente

```powershell
# Windows
psql -U postgres -c "DROP DATABASE IF EXISTS lurch;"
psql -U postgres -c "CREATE DATABASE lurch OWNER lurch_user;"
psql -U postgres -d lurch -c "GRANT ALL ON SCHEMA public TO lurch_user;"
```

```bash
# Linux / Mac
sudo -u postgres psql -c "DROP DATABASE IF EXISTS lurch;"
sudo -u postgres psql -c "CREATE DATABASE lurch OWNER lurch_user;"
sudo -u postgres psql -d lurch -c "GRANT ALL ON SCHEMA public TO lurch_user;"
```

### Importa il dump

> ⚠️ **Non usare mai il pipe `|` di PowerShell per il restore.** PowerShell tratta il pipe come testo e riconverte l'encoding, corrompendo i caratteri UTF-8 multi-byte (`°`, `à`, `è`, ecc.) in `??`. Usare sempre Git Bash o il metodo "estrai poi importa".

**Windows — Git Bash** (consigliato, pipe binario):
```bash
gunzip -c /c/percorso/del/db.sql.gz | psql -U lurch_user -d lurch
```

**Windows — PowerShell** (estrai prima, poi importa — encoding sicuro):
```powershell
$src = [System.IO.File]::OpenRead("db.sql.gz")
$gz  = New-Object System.IO.Compression.GzipStream($src, [System.IO.Compression.CompressionMode]::Decompress)
$dst = [System.IO.File]::Create("db.sql")
$gz.CopyTo($dst); $gz.Close(); $dst.Close(); $src.Close()
psql -U lurch_user -d lurch -f db.sql
Remove-Item db.sql
```

**Linux / Mac**:
```bash
gunzip -c db.sql.gz | psql -U lurch_user -d lurch
```

### Dopo l'import

Non eseguire `migrate` né `db:seed` — il dump contiene tutto.

Eseguire il superadmin seeder se le credenziali locali non corrispondono a quelle del dump:

```bash
php artisan db:seed --class=SuperadminSeeder
```

---

## Nota — errori FK durante il restore

Se durante il restore compaiono errori del tipo:

```
ERRORE: la INSERT o l'UPDATE sulla tabella "X" viola il vincolo di chiave esterna
```

La causa è che il database **non era vuoto**: le tabelle esistevano già (con vincoli FK attivi)
e il dump ha tentato di inserire dati in ordine diverso da quello richiesto dai vincoli.

**Soluzione:** azzerare completamente il DB (DROP + CREATE) e ripetere il restore dall'inizio.

Errori del tipo `la relazione X esiste già` o `non è possibile avere più di una chiave primaria`
sono **non critici** e indicano solo che le tabelle/indici erano già presenti — lo schema
è stato saltato ma i dati vengono comunque inseriti.
