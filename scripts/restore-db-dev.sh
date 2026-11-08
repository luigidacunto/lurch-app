#!/usr/bin/env bash
# =============================================================================
# restore-db-dev.sh  —  Esegui DAL PC (Git Bash) per ripristinare un dump sul
# database di sviluppo configurato nel .env locale (oggi: PostgreSQL della VM dev)
#
# Uso:
#   bash scripts/restore-db-dev.sh /percorso/db-v1.83.9.sql.gz
#
# Differenze da restore-db.sh (che gira SUL SERVER e fa DROP DATABASE):
# - non serve SSH né superuser: basta l'utente app del .env;
# - sostituisce solo i DATI, lo schema resta quello attuale (già migrato al
#   codice): un dump più vecchio del codice si ripristina senza dover rifare
#   migrate; la tabella migrations NON viene toccata (né righe né sequenza);
# - tutto in UNA transazione: se qualcosa fallisce il DB resta com'era.
#
# ⚠️ Svuota TUTTE le tabelle (tranne migrations), anche quelle che il dump non
# contiene: i loro dati punterebbero a ID che non esistono più.
# ⚠️ Il dump contiene dati reali: dopo, lanciare php artisan dev:anonimizza-dati.
# =============================================================================

set -euo pipefail

DUMP_FILE="${1:-}"
if [[ -z "$DUMP_FILE" || ! -f "$DUMP_FILE" ]]; then
    echo "Uso: bash scripts/restore-db-dev.sh <dump.sql.gz | dump.sql>"
    [[ -n "$DUMP_FILE" ]] && echo "ERRORE: file non trovato: $DUMP_FILE"
    exit 1
fi

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$APP_DIR/.env"

_env() { grep "^$1=" "$ENV_FILE" 2>/dev/null | head -1 | cut -d= -f2- | tr -d "\"'\r" || true; }

if [[ "$(_env APP_ENV)" == "production" ]]; then
    echo "ERRORE: APP_ENV=production in $ENV_FILE — script solo per sviluppo."
    exit 1
fi

DB_HOST=$(_env DB_HOST); DB_HOST=${DB_HOST:-127.0.0.1}
DB_PORT=$(_env DB_PORT); DB_PORT=${DB_PORT:-5432}
DB_NAME=$(_env DB_DATABASE)
DB_USER=$(_env DB_USERNAME)
export PGPASSWORD="$(_env DB_PASSWORD)"

PSQL=$(command -v psql || ls "/c/Program Files/PostgreSQL"/*/bin/psql.exe 2>/dev/null | tail -1 || true)
if [[ -z "$PSQL" ]]; then
    echo "ERRORE: psql non trovato (né nel PATH né in C:\\Program Files\\PostgreSQL)."
    exit 1
fi
q() { "$PSQL" -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -tAc "$1"; }

echo ""
echo "  Database:  $DB_USER@$DB_HOST:$DB_PORT/$DB_NAME"
echo "  Dump:      $DUMP_FILE"
echo "  Soci ora:  $(q 'SELECT count(*) FROM soci' | tr -d '\r')"
echo ""
echo "  Questo SOSTITUISCE tutti i dati di \"$DB_NAME\" con quelli del dump."
read -r -p "  Per confermare, scrivi il nome esatto del database (\"$DB_NAME\"): " CONFERMA
if [[ "$CONFERMA" != "$DB_NAME" ]]; then
    echo "  Annullato (nome non corrispondente)."
    exit 1
fi

SQL_FILE=$(mktemp)
trap 'rm -f "$SQL_FILE"' EXIT

{
cat <<'SQL'
\set ON_ERROR_STOP on
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
BEGIN;
-- L'utente app non è superuser (niente session_replication_role): si tolgono
-- le FK, si caricano i dati in qualsiasi ordine, si rimettono le FK (validate).
CREATE TEMP TABLE _fk AS SELECT conrelid::regclass::text AS t, conname AS n, pg_get_constraintdef(oid) AS d
  FROM pg_constraint WHERE contype = 'f' AND connamespace = 'public'::regnamespace;
DO $$ DECLARE r RECORD; BEGIN
  FOR r IN SELECT * FROM _fk LOOP EXECUTE format('ALTER TABLE %s DROP CONSTRAINT %I', r.t, r.n); END LOOP;
  FOR r IN SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename <> 'migrations' LOOP
    EXECUTE 'TRUNCATE TABLE public.' || quote_ident(r.tablename);
  END LOOP;
END $$;
SQL
# Dal dump solo i blocchi COPY e i setval, esclusa la tabella migrations e la sua
# sequenza (altrimenti il prossimo migrate va in duplicate key su migrations_pkey).
if [[ "$DUMP_FILE" == *.gz ]]; then gunzip -c "$DUMP_FILE"; else cat "$DUMP_FILE"; fi | awk '
    /^COPY public\.migrations /                 { skip = 1 }
    /^COPY public\./                            { f = 1 }
    f && !skip                                  { print }
    f && /^\\\.$/                               { f = 0; skip = 0; next }
    /^SELECT pg_catalog\.setval/ && !/migrations_id_seq/ { print }
'
cat <<'SQL'
SET search_path = public;
DO $$ DECLARE r RECORD; BEGIN
  FOR r IN SELECT * FROM _fk LOOP EXECUTE format('ALTER TABLE %s ADD CONSTRAINT %I %s', r.t, r.n, r.d); END LOOP;
END $$;
COMMIT;
SQL
} > "$SQL_FILE"

echo "→ Ripristino in corso ($(grep -c '^COPY' "$SQL_FILE") tabelle)..."
if ! "$PSQL" -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -q -f "$SQL_FILE" > /dev/null; then
    echo ""
    echo "  ERRORE: ripristino annullato, il database è rimasto com'era."
    echo "  Se l'errore parla di colonne/tabelle mancanti, il dump è più nuovo"
    echo "  dello schema: lancia prima php artisan migrate e riprova."
    exit 1
fi

echo "  ✓ soci: $(q 'SELECT count(*) FROM soci' | tr -d '\r')"
# Dati di riferimento che un dump più vecchio del codice non contiene (idempotente).
php "$APP_DIR/artisan" db:seed --class=EsameModelliSeeder --force > /dev/null
echo "  ✓ modelli d'esame: caricati"
php "$APP_DIR/artisan" optimize:clear > /dev/null 2>&1 || true
echo ""
echo "  Fatto. Niente migrate: lo schema non è stato toccato."
# ponytail: dev:anonimizza-dati dà a ogni socio un'email @example.invalid (dominio riservato,
# mai reale): un'email diversa = dati non anonimizzati. Se il comando cambia formato, va allineato qui.
REALI=$(q "SELECT count(*) FROM soci WHERE email IS NOT NULL AND email NOT LIKE '%@example.invalid'" | tr -d '\r')
if [[ "$REALI" != "0" ]]; then
    echo "  ⚠️ $REALI soci con email reali: dump NON anonimizzato, lancia php artisan dev:anonimizza-dati"
else
    echo "  ✓ dati già anonimizzati"
fi
echo ""
