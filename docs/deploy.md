# LURCH — Deploy su server di produzione

Guida per il primo setup e i deploy successivi. **Produzione e sviluppo girano su Ubuntu con
HestiaCP** — Nginx, PHP-FPM, SSL e PostgreSQL sono creati/gestiti dal pannello, non a mano via
`apt`/`certbot`. Le sezioni sotto sono scritte per questo schema; le differenze tra i due
ambienti (dominio, utente di sistema, modalità di inizializzazione del DB) sono segnalate dove
rilevanti.

Per il setup in sviluppo locale vedi [setup-dev.md](setup-dev.md).
Per backup e disaster recovery vedi [backup-restore.md](backup-restore.md).

---

## Indice

- [Setup iniziale server](#setup-iniziale-server)
- [Autenticazione git via deploy key (SSH)](#autenticazione-git-via-deploy-key-ssh)
- [Server di sviluppo (HestiaCP)](#server-di-sviluppo-hestiacp)
- [Deploy successivi](#deploy-successivi)
- [Riepilogo componenti](#riepilogo-componenti)

---

## Setup iniziale server

### 1. Crea dominio, PHP e PostgreSQL da pannello Hestia

Nginx, PHP-FPM e SSL sono creati e gestiti da Hestia per il dominio — non serve configurarli a
mano né usare `certbot` direttamente: si crea il dominio dal pannello, si abilita SSL (Let's
Encrypt) da lì, e si sceglie la versione PHP (8.4) dal selettore del dominio.

PostgreSQL va creato vuoto dal pannello Hestia (utente + database dedicati) invece che via
`CREATE USER`/`CREATE DATABASE` a mano.

⚠️ **Verificare che il document root del dominio in Hestia punti a `public_html/public/` e non
a `public_html/`** — Laravel deve sempre esporre solo la sottocartella `public/`, mai la root
dell'applicazione.

### 2. Deploy del codice

Il repository va clonato **direttamente dentro `public_html/`** del dominio (non in una
cartella sorella con symlink):

```bash
cd /home/<utente-sistema>/web/<dominio>/public_html
git clone <url-repo> .

composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

`<utente-sistema>` è l'utente di sistema Hestia del sito (es. `webmaster`) — sostituisce `www-data` di uno stack Ubuntu "nudo": è lui il proprietario dei
file e l'utente sotto cui girano PHP-FPM e i comandi `artisan`, non serve `sudo -u www-data`
davanti a ogni comando.

Modifica `/home/<utente-sistema>/web/<dominio>/public_html/.env`:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tuodominio.it
APP_LOCALE=it
APP_FALLBACK_LOCALE=en

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=lurch
DB_USERNAME=lurch_user
DB_PASSWORD=password_sicura

SUPERADMIN_EMAIL=admin@tuodominio.it
SUPERADMIN_PASSWORD=password_sicura

QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database

MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=noreply@tuodominio.it
MAIL_FROM_NAME="Nome Organizzazione"

LOG_CHANNEL=daily
LOG_LEVEL=error
```

```bash
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan db:seed
sudo -u www-data npm ci && sudo -u www-data npm run build
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan filament:cache-components
```

### 3. Configura il queue worker (Supervisor)

Hestia non installa Supervisor di default — va installato a parte (`apt install supervisor`).

Crea `/etc/supervisor/conf.d/lurch-worker.conf`:

```ini
[program:lurch-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /home/<utente-sistema>/web/<dominio>/public_html/artisan queue:work --timeout=0 --sleep=3 --max-jobs=500
directory=/home/<utente-sistema>/web/<dominio>/public_html
user=<utente-sistema>
numprocs=2
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
redirect_stderr=true
stdout_logfile=/home/<utente-sistema>/web/<dominio>/public_html/storage/logs/worker.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=3
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start lurch-worker:*
sudo supervisorctl status
```

> **Problema noto — `Call to undefined function Illuminate\Queue\pcntl_signal()`:**
> Su Ubuntu 24.04 e/o con HestiaCP, le funzioni `pcntl_*` sono disabilitate per default
> nel `php.ini` CLI tramite `disable_functions`. Il queue worker le richiede e va in BACKOFF.
>
> Soluzione — svuota `disable_functions` nel php.ini CLI (sicuro: il CLI non è esposto al web):
>
> ```bash
> sudo sed -i 's/^disable_functions = .*/disable_functions =/' /etc/php/8.4/cli/php.ini
> ```
>
> Poi riavvia supervisor. Se usi una versione PHP diversa, sostituisci `8.4` con la versione corretta.

### 4. Configura lo scheduler

Preferire la UI di Hestia (**Server → Cron Jobs**) invece di `crontab -e` a mano, per restare
gestito dal pannello. Il comando da schedulare ogni minuto:

```cron
cd /home/<utente-sistema>/web/<dominio>/public_html && php artisan schedule:run >> /dev/null 2>&1
```

### 5. Permessi filesystem

Hestia crea già i file con l'utente di sistema corretto durante il clone/deploy; se serve
correggere i permessi dopo un'operazione manuale:

```bash
chmod -R 755 /home/<utente-sistema>/web/<dominio>/public_html/storage
chmod -R 755 /home/<utente-sistema>/web/<dominio>/public_html/bootstrap/cache
```

---

## Autenticazione git via deploy key (SSH)

Il repository pubblico si può clonare anche via HTTPS, senza credenziali. Se invece il server
scarica il codice via SSH (repo privato, fork privato o mirror), per evitare
che `git pull` sul server chieda username/password ad ogni deploy — e senza salvare un
token in chiaro nel `.git/config` o in un `.netrc` — si usa una **deploy key SSH dedicata**,
in sola lettura, associata al singolo repo.

Procedura (da rifare ogni volta che si cambia server, come già successo in passato):

```bash
# 1. Genera una chiave dedicata sul server, senza passphrase (serve senza interazione)
ssh-keygen -t ed25519 -C "deploy-lurch" -f ~/.ssh/deploy_lurch -N ""

# 2. Mostra la chiave pubblica da registrare
cat ~/.ssh/deploy_lurch.pub
```

Sull'host git (GitHub, Forgejo, GitLab): repo → **Settings** → **Deploy Keys** → **Add Deploy Key** →
incolla la chiave pubblica, lascia spuntato solo **Read Only** (basta per il pull di deploy).

Di nuovo sul server, punta il remote su SSH e di' a git di usare quella chiave:

```bash
cd /home/<utente-sistema>/web/<dominio>/public_html
git remote set-url origin git@<host-git>:<owner>/lurch-app.git
git config core.sshCommand "ssh -i ~/.ssh/deploy_lurch -o IdentitiesOnly=yes"

git fetch origin   # test — non deve chiedere nulla
```

> **Nota porta SSH:** se l'host git usa una porta SSH non standard (es. `2222`
> invece di `22`), aggiungere `-p <porta>` al `core.sshCommand`, oppure definire un alias in
> `~/.ssh/config` con `Host` + `Port`.

---

## Differenze tra produzione e server di sviluppo

Produzione e dev girano entrambi su Hestia con lo stesso schema descritto sopra. Uniche differenze reali tra i due:

| | Produzione | Dev |
|---|---|---|
| Dominio | dominio pubblico | dominio interno di sviluppo |
| Utente di sistema | utente Hestia del sito | `webmaster` |
| Deploy key SSH | `~/.ssh/deploy_lurch` | `~/.ssh/deploy_lurch_dev` (registrata separatamente come Read Only) |
| Inizializzazione DB | `migrate --force` + `db:seed` da zero | PostgreSQL creato vuoto dal pannello, poi ripristinato da un dump `.gz` di produzione (vedi [backup-restore.md](backup-restore.md)); dopo il restore va comunque eseguito `php artisan migrate --force` (idempotente) e il singolo `php artisan db:seed --class="Database\Seeders\PrivacyTemplateSeeder" --force` (mai `db:seed` completo su un DB già popolato dal dump) |
| Deploy successivi | `bash scripts/deploy.sh` | `bash scripts/deploy.sh --dev` (mantiene i pacchetti di sviluppo e riavvia il worker da solo) |

Resta valido su entrambi il fix noto sui `pcntl_*` disabilitati nel php.ini CLI (sezione sopra).

---

## Pubblicare dev su main

I commit vanno sempre prima su `dev`. Quando una serie di modifiche è pronta per la
produzione, `dev` viene pubblicato su `main` con un fast-forward: mai un merge commit,
`main` riceve solo da `dev` e non ha commit propri. Dalla postazione di sviluppo, con `dev`
allineato a `origin/dev`:

```bash
git fetch origin
git push origin origin/dev:main   # rifiutato se non è un fast-forward
git fetch origin main:main        # allinea il ref main locale (se non è il branch corrente)
```

Solo a questo punto ha senso fare il deploy in produzione (sezione sotto).

---

## Deploy successivi

`scripts/deploy.sh` automatizza l'intera procedura (git pull, composer, npm build, migrate,
cache, storage:link) — non copiare più i comandi a mano, così i due percorsi non divergono.

**Produzione** (da `/home/<utente-sistema>/web/<dominio>/public_html`, come `<utente-sistema>`):

```bash
bash scripts/backup.sh
bash scripts/deploy.sh
```

`--no-dev` sul composer, `migrate --force` non interattivo, a fine deploy stampa solo il
promemoria per riavviare il worker (mai automatico in prod — un riavvio inatteso a metà di
un job in corso ha conseguenze reali sugli utenti).

**Server di sviluppo** (come `webmaster`, vedi sezione sopra):

```bash
bash scripts/deploy.sh --dev
```

Con `--dev`: composer **senza** `--no-dev` (servono i pacchetti di sviluppo), e il queue
worker viene riavviato direttamente via `sudo supervisorctl restart lurch-worker:*` invece
di limitarsi al promemoria.

Entrambi gli ambienti supportano anche `--quick` (solo git pull + cache clear, per modifiche
che non toccano composer/npm/migration) — combinabile con `--dev`: `bash scripts/deploy.sh
--quick --dev`.

> ⚠️ **`npm run build` era assente da questa procedura** (scoperto luglio 2026, poi
> incorporato nello script) — `public/build/` è in `.gitignore`, quindi il bundle CSS/JS
> compilato non arriva mai col `git pull` e va rigenerato ad ogni deploy. Se lo si salta, il
> codice PHP/Blade si aggiorna ma lo stile visivo resta quello dell'ultima build fatta (anche
> di mesi prima) — sintomo tipico: un fix CSS "non si vede" nonostante il codice sorgente sia
> corretto, e non risolvibile con `view:cache`/`filament:clear-cached-components`. Il modo
> `--quick` lo salta deliberatamente (presuppone nessuna modifica a Tailwind/asset), non
> usarlo per un deploy che tocca `resources/`.

> Eseguire sempre `bash scripts/backup.sh` prima di pull rischiosi. Il backup viene salvato
> in `~/lurch-backups/` e mantiene automaticamente gli ultimi 10. Non incorporato in
> `deploy.sh` stesso — resta un passo esplicito separato, per non backuppare automaticamente
> anche sul server dev dove non serve.

---

## Riepilogo componenti

| Componente | Gestito da | Avvio automatico |
|---|---|---|
| Richieste web | Nginx + PHP-FPM | systemd |
| Job in background | Supervisor → `queue:work` | Supervisor autostart |
| Task pianificati | cron → `schedule:run` | crontab |
| Database | PostgreSQL | systemd |
