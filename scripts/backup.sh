#!/usr/bin/env bash
# =============================================================================
# backup.sh  —  Esegui SUL SERVER prima di pull rischiosi
#
# Crea nella home dell'utente corrente (VERSIONE = config/app.php → 'version'):
#   ~/lurch-backups/YYYYMMDD_HHMMSS/db-vVERSIONE.sql.gz      ← dump completo PostgreSQL
#   ~/lurch-backups/YYYYMMDD_HHMMSS/files-vVERSIONE.tar.gz   ← archivio della directory del deploy (saltato con --only-db)
#
# Uso (path del deploy = directory padre di scripts/, rilevato automaticamente):
#   bash /percorso/del/deploy/scripts/backup.sh
#   bash /percorso/del/deploy/scripts/backup.sh --only-db   # solo il dump database, niente file
#
# Ripristino DB:
#   gunzip -c ~/lurch-backups/YYYYMMDD_HHMMSS/db-vVERSIONE.sql.gz | psql -h HOST -U USER -d DBNAME
#
# Ripristino file (con sudo solo se APP_DIR appartiene a un altro utente, es. www-data o l'utente Hestia del sito):
#   sudo tar -xzf ~/lurch-backups/YYYYMMDD_HHMMSS/files-vVERSIONE.tar.gz -C /
# =============================================================================

set -euo pipefail

ONLY_DB=false
[[ "${1:-}" == "--only-db" ]] && ONLY_DB=true

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$APP_DIR/.env"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="$HOME/lurch-backups/$TIMESTAMP"

_env() { grep "^$1=" "$ENV_FILE" 2>/dev/null | head -1 | cut -d= -f2- | tr -d '"' | tr -d "'"; }

# Versione app da config/app.php ('version' => '1.71.1',) — messa nei nomi dei file di
# backup per sapere a colpo d'occhio a che release corrisponde ogni backup.
APP_VERSION=$(grep -oE "'version'[[:space:]]*=>[[:space:]]*'[^']+'" "$APP_DIR/config/app.php" 2>/dev/null \
    | head -1 | grep -oE "[0-9][0-9A-Za-z.+_-]*")
APP_VERSION=${APP_VERSION:-unknown}

DB_FILE="db-v${APP_VERSION}.sql.gz"
FILES_FILE="files-v${APP_VERSION}.tar.gz"

DB_HOST=$(_env DB_HOST);     DB_HOST=${DB_HOST:-127.0.0.1}
DB_PORT=$(_env DB_PORT);     DB_PORT=${DB_PORT:-5432}
DB_NAME=$(_env DB_DATABASE)
DB_USER=$(_env DB_USERNAME)
DB_PASS=$(_env DB_PASSWORD)

if [[ -z "$DB_NAME" || -z "$DB_USER" ]]; then
    echo "ERRORE: DB_DATABASE o DB_USERNAME non trovati in $ENV_FILE"
    exit 1
fi

mkdir -p "$BACKUP_DIR"

echo ""
echo "┌─────────────────────────────────────────────────────┐"
echo "│  L.U.R.C.H. — Backup  │  $TIMESTAMP  │  v$APP_VERSION"
echo "└─────────────────────────────────────────────────────┘"
echo ""

STEP_TOTAL=2
$ONLY_DB && STEP_TOTAL=1

# ── 1. Dump database ──────────────────────────────────────────────────────────
echo "→ [1/$STEP_TOTAL] Dump database ($DB_USER@$DB_HOST:$DB_PORT/$DB_NAME)..."

export PGPASSWORD="$DB_PASS"
pg_dump \
    -h "$DB_HOST" -p "$DB_PORT" \
    -U "$DB_USER" -d "$DB_NAME" \
    --no-owner --no-acl \
    | gzip > "$BACKUP_DIR/$DB_FILE"

DB_SIZE=$(du -sh "$BACKUP_DIR/$DB_FILE" | cut -f1)
echo "  ✓ $DB_FILE  ($DB_SIZE)"

# ── 2. Archivio file (saltato con --only-db) ───────────────────────────────────
if ! $ONLY_DB; then
    echo "→ [2/2] Archivio file ($APP_DIR)..."

    TAR_ARGS=(
        --exclude="$APP_DIR/.git"
        --exclude="$APP_DIR/node_modules"
        --exclude="$APP_DIR/vendor"
        -czf "$BACKUP_DIR/$FILES_FILE"
        -C / "${APP_DIR#/}"
    )

    # ponytail: prova senza sudo (caso comune: deploy nella home utente); sudo
    # serve solo se il deploy appartiene a un altro utente (es. www-data)
    if ! tar "${TAR_ARGS[@]}" 2>/dev/null; then
        sudo tar "${TAR_ARGS[@]}"
        sudo chown "$USER:$USER" "$BACKUP_DIR/$FILES_FILE"
    fi

    FILES_SIZE=$(du -sh "$BACKUP_DIR/$FILES_FILE" | cut -f1)
    echo "  ✓ $FILES_FILE  ($FILES_SIZE)"
fi

# ── Riepilogo ─────────────────────────────────────────────────────────────────
TOTAL_SIZE=$(du -sh "$BACKUP_DIR" | cut -f1)
echo ""
echo "  Backup salvato in: $BACKUP_DIR  ($TOTAL_SIZE totali)"
echo ""
echo "  Ripristino DB:"
echo "    gunzip -c $BACKUP_DIR/$DB_FILE | psql -h $DB_HOST -U $DB_USER -d $DB_NAME"
echo ""
if ! $ONLY_DB; then
    echo "  Ripristino file (sudo obbligatorio per ownership www-data):"
    echo "    sudo tar -xzf $BACKUP_DIR/$FILES_FILE -C /"
    echo ""
fi

# ── Pulizia automatica: mantieni solo gli ultimi 10 backup ────────────────────
BACKUP_ROOT="$HOME/lurch-backups"
BACKUP_COUNT=$(ls -1 "$BACKUP_ROOT" | wc -l)
if [[ $BACKUP_COUNT -gt 10 ]]; then
    echo "  Pulizia backup vecchi (mantengo gli ultimi 10)..."
    ls -1t "$BACKUP_ROOT" | tail -n +11 | while read -r old; do
        rm -rf "$BACKUP_ROOT/$old"
        echo "  → eliminato: $old"
    done
fi

echo "  Fatto."
echo ""
