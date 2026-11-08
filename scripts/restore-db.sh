#!/usr/bin/env bash
# =============================================================================
# restore-db.sh  —  Esegui SUL SERVER per ripristinare il database da un dump
#
# ⚠️ DISTRUTTIVO: cancella e ricrea il database configurato in .env (DB_DATABASE)
# con i dati del dump indicato. Pensato per il server di SVILUPPO — su produzione
# usare solo in un vero disaster recovery (vedi docs/backup-restore.md).
#
# Uso:
#   bash /percorso/del/deploy/scripts/restore-db.sh --file /percorso/assoluto/db.sql.gz
#
# Il dump può essere .sql.gz (gzip) o .sql (plain) — rilevato dall'estensione.
# =============================================================================

set -euo pipefail

DUMP_FILE=""
while [[ $# -gt 0 ]]; do
    case "$1" in
        --file) DUMP_FILE="$2"; shift 2 ;;
        *) echo "Argomento sconosciuto: $1"; exit 1 ;;
    esac
done

if [[ -z "$DUMP_FILE" ]]; then
    echo "Uso: bash restore-db.sh --file /percorso/assoluto/db.sql.gz"
    exit 1
fi

if [[ ! -f "$DUMP_FILE" ]]; then
    echo "ERRORE: file non trovato: $DUMP_FILE"
    exit 1
fi

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$APP_DIR/.env"

_env() { grep "^$1=" "$ENV_FILE" 2>/dev/null | head -1 | cut -d= -f2- | tr -d '"' | tr -d "'" || true; }

DB_HOST=$(_env DB_HOST);     DB_HOST=${DB_HOST:-127.0.0.1}
DB_PORT=$(_env DB_PORT);     DB_PORT=${DB_PORT:-5432}
DB_NAME=$(_env DB_DATABASE)
DB_USER=$(_env DB_USERNAME)
DB_PASS=$(_env DB_PASSWORD)

if [[ -z "$DB_NAME" || -z "$DB_USER" ]]; then
    echo "ERRORE: DB_DATABASE o DB_USERNAME non trovati in $ENV_FILE"
    exit 1
fi

APP_ENV=$(_env APP_ENV); APP_ENV=${APP_ENV:-unknown}

# ── Blocco duro su produzione ────────────────────────────────────────────────
# Questo script è pensato per il server di sviluppo. Su produzione un restore va
# fatto solo a mano seguendo docs/backup-restore.md (disaster recovery reale),
# mai lanciando questo script per errore — niente conferma, si esce e basta.
if grep -qE '^APP_ENV=production$' "$ENV_FILE" 2>/dev/null; then
    echo ""
    echo "ERRORE: questo è l'ambiente di PRODUZIONE (APP_ENV=production in $ENV_FILE)."
    echo "Questo script non è utilizzabile qui. Per un vero disaster recovery in produzione"
    echo "seguire la procedura manuale in docs/backup-restore.md."
    echo ""
    exit 1
fi

echo ""
echo "┌─────────────────────────────────────────────────────┐"
echo "│  L.U.R.C.H. — Restore DB  │  $DB_NAME  │"
echo "└─────────────────────────────────────────────────────┘"
echo ""
echo "  Ambiente:  $APP_ENV"
echo "  Database:  $DB_USER@$DB_HOST:$DB_PORT/$DB_NAME"
echo "  Dump:      $DUMP_FILE"
echo ""

echo "  Questo CANCELLA tutti i dati attuali di \"$DB_NAME\" e li sostituisce col dump."
read -r -p "  Per confermare, scrivi il nome esatto del database (\"$DB_NAME\"): " CONFERMA
if [[ "$CONFERMA" != "$DB_NAME" ]]; then
    echo "  Annullato (nome non corrispondente)."
    exit 1
fi

# ── 1. Drop + create (via postgres superuser, evita problemi di permessi/password app) ──
echo "→ [1/3] Drop + create database..."
sudo -n -u postgres psql -c "DROP DATABASE IF EXISTS \"$DB_NAME\";"
sudo -n -u postgres psql -c "CREATE DATABASE \"$DB_NAME\" OWNER \"$DB_USER\";"
sudo -n -u postgres psql -d "$DB_NAME" -c "GRANT ALL ON SCHEMA public TO \"$DB_USER\";"
echo "  ✓ fatto"

# ── 2. Restore dump ──────────────────────────────────────────────────────────
echo "→ [2/3] Ripristino dump..."
export PGPASSWORD="$DB_PASS"
if [[ "$DUMP_FILE" == *.gz ]]; then
    gunzip -c "$DUMP_FILE" | psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -v ON_ERROR_STOP=1 -q
else
    psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -v ON_ERROR_STOP=1 -q -f "$DUMP_FILE"
fi
echo "  ✓ fatto"

# ── 3. Verifica ──────────────────────────────────────────────────────────────
echo "→ [3/3] Verifica..."
CONTEGGIO=$(psql -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -t -c "SELECT count(*) FROM soci;" | tr -d ' ')
echo "  ✓ soci: $CONTEGGIO"

echo ""
echo "  Fatto. Non eseguire migrate/db:seed — il dump contiene già tutto."
echo ""
