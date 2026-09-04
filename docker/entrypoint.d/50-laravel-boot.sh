#!/bin/sh
# Runs on every container start, before Nginx/PHP-FPM come up (serversideup/php entrypoint hook).
#
# Migrations must succeed: a half-applied schema is worse than a stopped container.
# Seeders must NOT be fatal: a seeding problem should never stop the salon from billing.
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
for seeder in SettingsSeeder UserSeeder ServiceCatalogSeeder; do
    if ! php artisan db:seed --class="$seeder" --force --no-interaction; then
        echo "[boot] WARNING: $seeder failed; continuing so the app still starts."
        echo "[boot] Fix with: docker compose exec app php artisan db:seed --class=$seeder --force"
    fi
done

php artisan storage:link --force >/dev/null 2>&1 || true
php artisan optimize
echo "[boot] ready"
