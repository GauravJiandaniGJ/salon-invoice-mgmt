#!/bin/sh
# Runs on every container start, before Nginx/PHP-FPM come up (serversideup/php entrypoint hook).
set -e
cd /var/www/html

: "${APP_KEY:?APP_KEY is required. Generate one with: docker compose run --rm app php artisan key:generate --show}"

DB_FILE="${DB_DATABASE:-/var/www/html/database/database.sqlite}"
if [ ! -f "$DB_FILE" ]; then
    echo "[boot] creating SQLite database at $DB_FILE"
    mkdir -p "$(dirname "$DB_FILE")"
    touch "$DB_FILE"
fi

echo "[boot] migrating"
php artisan migrate --force --no-interaction

echo "[boot] seeding settings, users and the service catalog (idempotent)"
php artisan db:seed --class=SettingsSeeder --force --no-interaction
php artisan db:seed --class=UserSeeder --force --no-interaction
php artisan db:seed --class=ServiceCatalogSeeder --force --no-interaction

php artisan storage:link --force >/dev/null 2>&1 || true
php artisan optimize
echo "[boot] ready"
