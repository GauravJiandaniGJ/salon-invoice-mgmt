# Deploying Wow Salon

Target: a Laravel Forge site on a DigitalOcean droplet (PHP 8.3+, Nginx). No MySQL, Redis or queue workers are needed.

## 1. Server & site

1. In Forge, create a site (or reuse a droplet) with **PHP 8.3** and **Nginx**. Web directory: `/public`.
2. Point your domain's A record at the droplet and enable **Let's Encrypt SSL** in Forge.
3. Connect the Git repository and set the branch (e.g. `main`).

## 2. Environment (`.env` in Forge → Environment)

```
APP_NAME="Wow Salon"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<your-domain>
APP_TIMEZONE=Asia/Kolkata
APP_KEY=                      # forge generates one; keep it safe — it encrypts sessions

DB_CONNECTION=sqlite
DB_DATABASE=/home/forge/<site>/database/database.sqlite

SESSION_DRIVER=database
CACHE_STORE=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
LOG_CHANNEL=daily

SEED_OWNER_PASSWORD=<strong password for owner@wowsalon.local>
WHATSAPP_DRIVER=wame

# Off-site backups (DigitalOcean Spaces, S3-compatible). Optional but recommended.
BACKUP_SPACES_ENABLED=true
SPACES_KEY=
SPACES_SECRET=
SPACES_REGION=blr1
SPACES_BUCKET=wowsalon-backups
SPACES_ENDPOINT=https://blr1.digitaloceanspaces.com
BACKUP_NOTIFY_EMAIL=owner@example.com
```

Create the database file once: `touch /home/forge/<site>/database/database.sqlite`.

## 3. Deploy script (Forge → Deploy Script)

```bash
cd /home/forge/<site>
git pull origin $FORGE_SITE_BRANCH
$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci && npm run build
$FORGE_PHP artisan migrate --force
$FORGE_PHP artisan db:seed --class=SettingsSeeder --force
$FORGE_PHP artisan db:seed --class=UserSeeder --force
$FORGE_PHP artisan db:seed --class=ServiceCatalogSeeder --force
$FORGE_PHP artisan storage:link
$FORGE_PHP artisan optimize
( flock -w 10 9 || exit 1; echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock
```

All seeders are idempotent: they only insert missing rows and never overwrite prices or settings the owner has edited.

## 4. Scheduler & backups

- Forge → **Scheduler**: add `php /home/forge/<site>/artisan schedule:run` every minute (Forge has a one-click preset).
- The app schedules (Asia/Kolkata): `backup:clean` 01:30, `backup:run --only-db` 02:00 daily, full `backup:run` (DB + invoice PDFs + logo) Sundays 03:00.
- Backups land in `storage/backups` and, when `BACKUP_SPACES_ENABLED=true`, in the Spaces bucket. Retention: 30 days.
- Manual backup: `php artisan backup:run`. Check health: `php artisan backup:monitor`.

## 5. Monitoring

Forge → Monitoring → Site health check on `https://<domain>/up`.

## 6. First-run checklist

1. Log in as `owner@wowsalon.local` with `SEED_OWNER_PASSWORD`, then change the password (avatar → My account → Password).
2. **Settings**: salon name, tagline, address, display phone, WhatsApp number, logo, footer, invoice prefix, and `App URL` = `https://<domain>` (this is what goes inside WhatsApp messages).
3. **Settings → Users**: add the receptionist as `staff`. **Staff members**: add stylists.
4. On the salon laptop open `web.whatsapp.com` and log in with the salon phone (scan the QR). Keep the tab open.
5. Create a test bill for the owner's own number and press **Send on WhatsApp** to confirm the link works.

## 7. Restoring a backup

1. Download the latest zip from `storage/backups/<APP_NAME>/` or the Spaces bucket.
2. Unzip; copy `db-dumps/sqlite-*.sql` (or the `database.sqlite` file from a full backup) back into place, and `storage/app/invoices/*.pdf` if present.
3. Missing invoice PDFs regenerate on demand when a customer opens the link.
