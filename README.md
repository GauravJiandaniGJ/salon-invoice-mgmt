# Wow Salon — Invoicing & WhatsApp Billing

Admin-only web app for a single salon: create a bill in ~30 seconds, generate an invoice with a public link + PDF, and send it on WhatsApp with one click. See `PLAN.md` for the full spec.

**Stack:** Laravel 12 · Inertia.js · Vue 3 · Tailwind · SQLite · DomPDF

## Local setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan storage:link
npm run dev          # in one terminal
php artisan serve    # in another → http://localhost:8000
```

Seeded logins (password from `SEED_OWNER_PASSWORD` in `.env`, default `password`):

| Role  | Email                     |
|-------|---------------------------|
| Owner | `owner@wowsalon.local`    |
| Staff | `staff@wowsalon.local`    |

## Tests & checks

```bash
composer test        # Pest
vendor/bin/pint      # code style
npm run build        # production assets
```

## WhatsApp prerequisite

The salon laptop must have **WhatsApp Web** (`web.whatsapp.com`) logged in with the salon's phone (one-time QR scan). Keep that tab open all day. The **Send on WhatsApp** button opens WhatsApp Web with the customer and message pre-filled; the receptionist presses Enter.

`APP_URL` (and the `app_url` setting) must be the real public HTTPS domain, otherwise invoice links in messages will be wrong.
