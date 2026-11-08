#!/bin/bash
set -e

QUICK=false
DEV=false
for arg in "$@"; do
    [[ "$arg" == "--quick" ]] && QUICK=true
    [[ "$arg" == "--dev" ]] && DEV=true
done

# Sul server dev il queue worker viene riavviato direttamente (nessuno controllo manuale
# prima del restart, a differenza di prod dove un riavvio inatteso del worker durante un
# job in corso ha conseguenze reali sugli utenti finali).
restart_worker() {
    if [ "$DEV" = true ]; then
        echo "==> Restart queue worker"
        sudo supervisorctl restart lurch-worker:*
    else
        echo "Ricorda di riavviare il queue worker:"
        echo "  sudo supervisorctl restart lurch-worker:*"
    fi
}

if [ "$QUICK" = true ]; then
    echo "==> [QUICK] Git pull"
    # npm (versioni recenti) può riscrivere package.json/package-lock.json come
    # effetto collaterale di npm ci/npm run build (es. autorizzazione allo script
    # di post-install di esbuild) — questi file devono cambiare solo via commit,
    # mai per drift locale lasciato da un run precedente: scartato prima del pull.
    git checkout -- package.json package-lock.json
    git pull

    echo "==> [QUICK] Cache clear + ottimizzazione"
    php artisan optimize:clear
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan filament:cache-components

    restart_worker

    echo ""
    echo "Deploy rapido completato."
    exit 0
fi

echo "==> [1/6] Git pull"
# Vedi commento nel ramo --quick sopra: npm può riscrivere questi file da solo,
# scartiamo sempre il drift locale prima di aggiornare dal repo.
git checkout -- package.json package-lock.json
git pull

echo "==> [2/6] Composer"
if [ "$DEV" = true ]; then
    # Niente --no-dev sul server dev: servono i pacchetti di sviluppo (es. debug, test).
    composer install --optimize-autoloader
else
    composer install --no-dev --optimize-autoloader
fi

echo "==> [3/6] NPM build"
npm ci
npm run build

echo "==> [4/6] Migrazioni"
# --force: il deploy completo deve girare senza interazione, sia in prod (dove Laravel
# chiederebbe conferma per APP_ENV=production) sia in dev.
php artisan migrate --force
# Dati di riferimento (modelli d'esame): crea solo quelli mancanti, mai sovrascrive.
php artisan db:seed --class=EsameModelliSeeder --force

echo "==> [5/6] Cache"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:cache-components

echo "==> [6/6] Storage link"
php artisan storage:link 2>/dev/null || true

echo ""
echo "Deploy completato."
restart_worker
