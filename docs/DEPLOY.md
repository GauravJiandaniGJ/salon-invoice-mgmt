# Deploying Wow Salon (Docker)

One image, one server. The app (Nginx + PHP-FPM), a scheduler for backups, and Caddy for automatic HTTPS run with `docker compose`. Data (SQLite file, PDFs, uploaded logo, backups) lives in two Docker volumes and survives redeploys.

## 1. One-time server setup (Ubuntu 22.04/24.04, any VPS)

```bash
# as root or a sudo user
curl -fsSL https://get.docker.com | sh
mkdir -p /opt/wowsalon/docker && cd /opt/wowsalon
curl -fsSLo docker-compose.yml https://raw.githubusercontent.com/GauravJiandaniGJ/saloon-invoice-mgmt/main/docker-compose.yml
curl -fsSLo docker/Caddyfile   https://raw.githubusercontent.com/GauravJiandaniGJ/saloon-invoice-mgmt/main/docker/Caddyfile
curl -fsSLo .env               https://raw.githubusercontent.com/GauravJiandaniGJ/saloon-invoice-mgmt/main/.env.production.example
```

Edit `.env`: set `APP_URL`, `APP_DOMAIN`, `SEED_OWNER_PASSWORD`, and `APP_KEY` (generate below). Point the domain's DNS A record at the server first, so Caddy can obtain the certificate.

```bash
# the image is private on GitHub Container Registry: log in once with a token that has read:packages
docker login ghcr.io -u GauravJiandaniGJ
docker compose pull
docker compose run --rm app php artisan key:generate --show   # paste the value into .env as APP_KEY
docker compose up -d
docker compose logs -f app   # wait for "[boot] ready"
```

Open `https://<your-domain>` and log in as `owner@wowsalon.local` with `SEED_OWNER_PASSWORD`. Then complete the first-run checklist in section 5.

## 2. What happens on every start

The container entrypoint runs `migrate --force`, the idempotent seeders (settings, users, service catalog), `storage:link`, and `optimize`. Owner edits are never overwritten.

## 3. CI/CD (GitHub Actions)

- `tests` and `lint` run on every push.
- `deploy` runs after `tests` succeeds on `main`: builds the image, pushes it to `ghcr.io/gauravjiandanigj/saloon-invoice-mgmt` tagged `latest` and the commit SHA, then SSHes to the server and runs `docker compose pull && docker compose up -d`.

Enable the SSH step by adding in the repo's **Settings → Secrets and variables → Actions**:

| Type | Name | Value |
|---|---|---|
| Variable | `DEPLOY_ENABLED` | `true` |
| Secret | `DEPLOY_HOST` | server IP or hostname |
| Secret | `DEPLOY_USER` | SSH user (e.g. `root` or `deploy`) |
| Secret | `DEPLOY_SSH_KEY` | private key whose public key is in the server's `~/.ssh/authorized_keys` |
| Secret | `DEPLOY_PATH` | optional, defaults to `/opt/wowsalon` |

Until `DEPLOY_ENABLED` is set, the workflow only builds and pushes the image. You can also trigger it manually from the Actions tab (**Run workflow**).

**Rollback:** `APP_IMAGE=ghcr.io/gauravjiandanigj/saloon-invoice-mgmt:<sha> docker compose up -d` on the server.

## 4. Backups

The scheduler container runs `backup:run --only-db` nightly at 02:00 IST and a weekly full backup (SQLite file + invoices + uploads). Backups are kept 30 days on the `app_storage` volume; set the `SPACES_*` variables and `BACKUP_SPACES_ENABLED=true` to copy them to DigitalOcean Spaces or any S3-compatible bucket.

Manual backup / restore:

```bash
docker compose exec app php artisan backup:run
docker compose exec app php artisan backup:list
# restore: stop the stack, copy database.sqlite from the backup archive into the app_database volume, start again
```

## 5. First-run checklist

1. Settings → Salon: name, address, phone, WhatsApp number, upload the logo, confirm footer text.
2. Settings → Invoice: prefix and GST %. Settings → WhatsApp: check the message preview.
3. Settings → Users: change the owner password, add the receptionist.
4. On the salon laptop open web.whatsapp.com and scan the QR with the salon phone.
5. Create a test bill to the owner's own number and send it.

## 6. Useful commands

```bash
docker compose logs -f app            # application logs
docker compose exec app php artisan tinker
docker compose exec app php artisan invoice:regenerate-pdf 12
docker compose pull && docker compose up -d   # manual redeploy
```

## Alternative: Laravel Forge (no Docker)

Forge also works: PHP 8.3 site, SQLite, deploy script `composer install --no-dev && npm ci && npm run build && php artisan migrate --force && php artisan db:seed --class=ServiceCatalogSeeder --force && php artisan optimize`, plus the cron `* * * * * php artisan schedule:run`. The Docker path above is recommended because it is identical on any host.
