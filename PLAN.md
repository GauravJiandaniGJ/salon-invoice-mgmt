# Wow Salon — Invoicing & WhatsApp Billing System

**Build plan for Claude Code. Read this whole file before writing any code.**

---

## How to use this file (instructions for Claude Code)

1. This is a greenfield project. Work through **Section 11 (Build Phases)** in order. Do not skip ahead.
2. At the end of every phase: run the test suite, make sure `npm run build` passes, and commit with a message like `feat(phase-2): services & categories CRUD`.
3. Every decision in **Section 2** is final unless something is technically impossible — in that case stop, explain the blocker, and propose an alternative before continuing.
4. **Section 14** lists assumptions. Do not ask about them; implement as written. They will be corrected by the humans later if needed.
5. Keep it boring. No abstractions we don't need, no packages beyond the ones listed, no microservices, no queues. This is a one-salon app that will see ~30–100 invoices a day.
6. UI copy is English. Currency is INR (₹). Timezone is `Asia/Kolkata`. Phone numbers are Indian (+91).

---

## 0. TL;DR

A small **admin-only web app** for a single salon. A receptionist creates a bill in ~30 seconds (pick customer by phone → add services → save). The app generates an invoice with a **public link + PDF**, and a one-click **"Send on WhatsApp"** button opens WhatsApp Web with a pre-filled, polite message containing the link. The owner can **edit services & prices** from the UI and see a **daily statement** (earnings, expenses, net).

- **Stack:** Laravel (latest stable) + Inertia.js + Vue 3 + Tailwind, SQLite, DomPDF. One deployable, one server.
- **WhatsApp:** Phase 1 uses `wa.me` click-to-chat links (free, no approval, works with unsaved numbers, one click to send). Phase 2 (optional, later) swaps in the WhatsApp Cloud API behind the same interface for fully automatic sending.
- **Hosting:** any PHP host. Target: a Laravel Forge site on a DigitalOcean droplet. (Netlify was mentioned in the brief — it cannot run Laravel. It is not needed.)
- **No GST.** A `tax_amount` column exists (default 0) so GST can be enabled later without a migration.

---

## 1. Context & Goals

### Who
- **Owner** — the salon owner. Wants zero manual DB edits: manages services/prices, sees daily earnings vs expenses, sends invoices.
- **Receptionist / staff** — creates bills all day. Needs the billing screen to be fast and forgiving.
- **Customer** — receives a WhatsApp message with a link; opens it on a phone; can view/download the invoice. Never logs in.

### What "done" looks like
1. Receptionist types a phone number → customer auto-fills (or is created inline with just name + phone).
2. Picks services from the catalog (search or category browse), adjusts qty/price/discount, chooses payment mode, saves.
3. Invoice gets a sequential number, a short public URL (`https://<domain>/i/AbC123xY`) and a PDF.
4. Clicks **Send on WhatsApp** → WhatsApp Web opens in a new tab with the message pre-filled → receptionist presses Enter. Invoice is marked as sent.
5. Owner opens **Reports → Daily Statement** for any date: total earnings (by payment mode), list of invoices, expenses entered that day, net.
6. Owner opens **Services** and edits a price inline; the change is live on the next bill.

### Explicitly out of scope (v1)
- Online booking / appointments, customer-facing login, loyalty points, inventory/stock, GST returns, multi-branch, payment gateway, printing to a thermal printer (nice-to-have later — the public invoice page is print-friendly anyway).

### Source material
- Two call transcripts with the salon (summarised in Section 1 above and Section 14).
- 13 photos of the salon's printed service menu — transcribed into **Section 10 (Seed Data)**. Note the printed menu is branded "Meraki – The Unisex Salon"; the app brand is "Wow Salon" per the transcripts. Salon name/branding lives in Settings, so this is a config value, not a code change.

---

## 2. Key Decisions & Trade-offs

| # | Decision | Why | Alternative considered |
|---|----------|-----|------------------------|
| D1 | **Laravel + Inertia + Vue 3** (monolith) | One codebase, one deploy, no API/CORS/token plumbing. Team already knows Laravel + Vue. | Laravel API + separate React SPA on Netlify — two deploys, auth tokens, CORS, more surface area for a tiny app. Filament admin — great for CRUD but the POS-style billing screen wants custom Vue UX. |
| D2 | **SQLite** as the database | One salon, low write volume, zero ops. Backup = copy one file. Laravel defaults to it. Swap to MySQL later by changing `.env` only. | Managed MySQL — extra cost & ops for no benefit at this scale. |
| D3 | **`wa.me` click-to-chat for WhatsApp (Phase 1)** | Free, no Meta business verification, no template approval, works for unsaved numbers, works today. Costs the receptionist exactly one click (Enter) per invoice. | WhatsApp Cloud API — fully automatic but needs Meta Business verification, an approved utility template, and per-message cost. Unofficial libraries (whatsapp-web.js / Baileys) — can get the salon's number banned. **Not allowed.** |
| D4 | **Send a link, not a PDF attachment** | Links deliver reliably to unsaved numbers, preview nicely, open on phone without download, and the PDF is one tap away on the page. | Attaching PDFs via WhatsApp Web is manual and flaky for unsaved contacts. |
| D5 | **DomPDF** (`barryvdh/laravel-dompdf`) for PDF | Pure PHP, no Chrome/Node on the server. Fine for a one-page invoice. | Browsershot/Spatie PDF — needs headless Chrome; overkill. |
| D6 | **Flattened service catalog** (one row per priced variant, with an optional `group_name` for display) | Owner can edit any price as a single row. No variant/option matrix to explain. | Service + variants/options tables — more flexible, more UI, more bugs, harder for the owner to edit. |
| D7 | **Invoice snapshots** — line items copy name & price at billing time | Price edits must never rewrite old invoices. | Joining to `services` at render time — wrong the moment a price changes. |
| D8 | **Never delete invoices; void them** | Sequential numbering must stay gap-free for accounting. | Hard delete — creates gaps, loses audit trail. |
| D9 | **Two roles: `owner`, `staff`** | Staff can bill and add expenses; only owner can edit catalog, settings, void invoices, see all reports. | Full RBAC — unnecessary. |
| D10 | **Sync everything, no queue** | PDF generation for one page takes <1s. No Redis, no workers, no supervisor. | Queue — one more thing to break on a $6 droplet. |

---

## 3. Architecture

```
┌──────────────────────────────┐        ┌───────────────────────────┐
│  Receptionist / Owner laptop │        │  Customer's phone         │
│                              │        │                           │
│  Browser tab 1: Salon app    │        │  WhatsApp → taps link     │
│  Browser tab 2: WhatsApp Web │        │  → opens /i/{code}        │
│  (logged in with salon no.)  │        │  → views / downloads PDF  │
└──────────────┬───────────────┘        └─────────────┬─────────────┘
               │ HTTPS                                │ HTTPS (public, no auth)
               ▼                                      ▼
┌─────────────────────────────────────────────────────────────────────┐
│  Laravel app  (single server, Forge / Nginx / PHP-FPM)              │
│                                                                     │
│  Inertia + Vue pages (auth'd)   │   Public routes                   │
│   /dashboard  /bills/new         │    GET /i/{code}      HTML view   │
│   /invoices   /customers         │    GET /i/{code}/pdf  PDF stream │
│   /services   /expenses          │                                   │
│   /reports    /settings          │                                   │
│                                  │                                   │
│  Services layer                  │   Storage                         │
│   InvoiceService (create/void)   │    SQLite  database/database.sqlite│
│   WhatsAppSender (interface)     │    storage/app/invoices/*.pdf     │
│     └ WaMeLinkSender (phase 1)   │                                   │
│     └ CloudApiSender (phase 2)   │                                   │
│   PdfRenderer (DomPDF)           │                                   │
│   ReportService (daily/monthly)  │                                   │
└─────────────────────────────────────────────────────────────────────┘
```

**Request flow — create & send a bill**

1. `POST /invoices` → `InvoiceService::create()` in a DB transaction: find-or-create customer → next invoice number → insert invoice + items (snapshot names/prices) → compute totals → generate `public_code` → render & store PDF → return invoice.
2. Redirect to `/invoices/{id}` which shows the invoice and a **Send on WhatsApp** button.
3. Button = `<a target="_blank" href="https://wa.me/91XXXXXXXXXX?text=<urlencoded message>">`. Clicking it also fires `POST /invoices/{id}/mark-sent` (optimistic — records `whatsapp_sent_at`).
4. Customer taps the link → `GET /i/{code}` → HTML invoice with **Download PDF** → `GET /i/{code}/pdf`.

**Security**
- Everything under `/` except `/i/*` and `/login` requires auth (Laravel session auth from the starter kit).
- `/i/{code}` is public by design (customer has no account). `code` is 10 chars from `[A-Za-z0-9]` (~59 bits) — unguessable. Rate-limit the route (`throttle:60,1`). No customer phone number is shown on the public page beyond what the customer already knows (their own name + last 4 digits of phone).
- No registration route. Users are seeded / created by the owner from Settings.

---

## 4. Data Model

All tables have `id`, `created_at`, `updated_at` unless noted. Money is stored as `decimal(10,2)` **in rupees** (not paise) — the salon works in whole rupees and the owner may read the DB directly in a pinch.

### `users`
| column | type | notes |
|---|---|---|
| name | string | |
| email | string, unique | login |
| password | string | |
| role | enum: `owner`, `staff` | default `staff` |
| is_active | bool | default true |

### `customers`
| column | type | notes |
|---|---|---|
| name | string | |
| phone | string(15), unique | **normalised E.164 without `+`**, e.g. `919876543210`. See §9 phone rules. |
| gender | enum nullable: `female`, `male`, `other` | optional; helps default the catalog filter |
| notes | text nullable | allergies, preferences |
| last_visit_at | datetime nullable | denormalised; updated on invoice create |
| total_spent | decimal(12,2) | denormalised; updated on invoice create/void |

### `service_categories`
| column | type | notes |
|---|---|---|
| name | string | e.g. "Cut & Style – Women" |
| audience | enum: `women`, `men`, `all` | drives the default filter on the billing screen |
| sort_order | int | |
| is_active | bool | |

### `services`
| column | type | notes |
|---|---|---|
| service_category_id | fk | |
| group_name | string nullable | display grouping inside a category, e.g. "Keratin", "Global Color / Highlights" |
| name | string | the variant/leaf name, e.g. "Upto Shoulder", "Ammonia Free" — see seed data |
| description | string nullable | shown as helper text |
| price | decimal(10,2) | default price; **editable per line at billing time** |
| price_max | decimal(10,2) nullable | for ranges like "Nail art 100–500"; UI shows "₹100–500" and requires a price on the bill |
| duration_minutes | smallint nullable | informational |
| sort_order | int | |
| is_active | bool | inactive = hidden from picker but kept for history |

**Display name** = `group_name ? "{group_name} – {name}" : name`. Add an accessor `display_name`.

### `invoices`
| column | type | notes |
|---|---|---|
| invoice_number | string, unique | `WS-0001`, `WS-0002`… prefix from settings; zero-padded 4, grows naturally past 9999 |
| public_code | string(10), unique, indexed | random `[A-Za-z0-9]{10}` |
| customer_id | fk | |
| user_id | fk | who billed |
| staff_member_id | fk nullable | who served (optional; see `staff_members`) |
| invoice_date | date | in Asia/Kolkata; defaults to today, editable by owner |
| subtotal | decimal(10,2) | sum of line totals |
| discount_type | enum nullable: `flat`, `percent` | |
| discount_value | decimal(10,2) | as entered |
| discount_amount | decimal(10,2) | computed rupees |
| tax_rate | decimal(5,2) | default 0 (no GST) |
| tax_amount | decimal(10,2) | default 0 |
| total | decimal(10,2) | `subtotal - discount_amount + tax_amount`, rounded to nearest rupee (`round_off` stores the delta) |
| round_off | decimal(5,2) | |
| payment_mode | enum: `cash`, `upi`, `card`, `other` | |
| payment_status | enum: `paid`, `unpaid` | default `paid` |
| notes | text nullable | internal |
| status | enum: `issued`, `void` | |
| void_reason | string nullable | |
| voided_at / voided_by | datetime nullable / fk nullable | |
| whatsapp_sent_at | datetime nullable | |
| pdf_path | string nullable | `invoices/WS-0001.pdf` on the `local` disk |

### `invoice_items`
| column | type | notes |
|---|---|---|
| invoice_id | fk | |
| service_id | fk nullable | null for ad-hoc "custom" lines |
| description | string | **snapshot** of service display_name at billing time |
| unit_price | decimal(10,2) | snapshot / override |
| quantity | decimal(6,2) | default 1 (nail art per finger → qty 10) |
| line_total | decimal(10,2) | |
| sort_order | int | |

### `staff_members` (optional but cheap — build it)
| column | type | notes |
|---|---|---|
| name | string | stylist / therapist name |
| is_active | bool | |

Lets the owner see earnings per stylist later. Not linked to `users` (most stylists won't log in).

### `expenses`
| column | type | notes |
|---|---|---|
| expense_date | date | |
| category | string | free text with suggestions: Products, Rent, Salary, Electricity, Tea/Snacks, Misc |
| description | string | |
| amount | decimal(10,2) | |
| payment_mode | enum: `cash`, `upi`, `card`, `other` | |
| user_id | fk | who entered |

### `settings` (key/value, single row per key)
Keys: `salon_name`, `salon_tagline`, `salon_address`, `salon_phone` (display), `salon_whatsapp_number` (E.164, the number WhatsApp Web is logged in with — informational), `invoice_prefix` (default `WS`), `whatsapp_template` (see §6), `footer_text` (default `Powered by 2iT`), `logo_path` nullable, `app_url` (public base URL used in links).

Provide a `Setting::get('key', default)` helper with a request-level cache. Seed defaults.

### Indexes
`customers.phone` unique; `invoices.public_code` unique; `invoices(invoice_date, status)`; `invoice_items.invoice_id`; `expenses.expense_date`; `services(service_category_id, is_active, sort_order)`.

---

## 5. Features & Screens (with acceptance criteria)

Routes are Inertia pages unless marked `(public)` or `(json)`. All authenticated.

### 5.1 Auth
- `GET /login` — email + password. No registration, no social login. "Remember me" on.
- Password reset by owner from Settings → Users (set a new password directly; no email flow needed in v1).
- Middleware `role:owner` for owner-only routes.

### 5.2 Dashboard — `GET /dashboard`
- Today's numbers: invoices count, gross earnings, expenses, net; small breakdown by payment mode.
- Big primary button: **New Bill**.
- Last 10 invoices (number, customer, total, sent ✓/✗ with a "Send" shortcut).
- Owner also sees this month's totals.

### 5.3 New Bill — `GET /bills/new` · `POST /invoices`   ← **the core screen; spend the most effort here**
Layout: left = customer + services picker, right = the bill.

**Customer block**
- Phone input first (autofocus). On 10+ digits, debounce 300ms → `GET /customers/lookup?phone=` `(json)`.
  - Found → fill name, show "last visit: 12 Aug, ₹1,400" and a link to history.
  - Not found → name field becomes required; customer is created on save. No separate "create customer" step.
- Optional: gender select (defaults the catalog filter), staff member select.

**Services picker**
- Search box (matches `group_name + name + category name`, case-insensitive, min 2 chars).
- Category tabs / chips filtered by audience (`women` / `men` / `all`, toggle at top). Inactive services hidden.
- Click a service → adds a line (or increments qty if already present). Services with `price_max` prompt for the price inline.
- "Add custom line" → description + price (for anything not in the catalog).

**Bill block**
- Lines: description, unit price (editable), qty (editable), line total, remove.
- Discount: toggle flat ₹ / % → shows amount.
- Subtotal, discount, (tax row hidden while rate = 0), round-off, **Total** in large text.
- Payment mode chips: Cash / UPI / Card / Other. Payment status default Paid.
- Notes (internal).
- **Save & Preview** (primary) → creates invoice, redirects to `/invoices/{id}`.
- Keyboard: Enter in search selects first result; `Ctrl+S` saves.

**Validation** (Form Request)
- phone valid per §9; name required when customer is new; ≥1 line; each line price ≥ 0 & qty > 0; discount ≤ subtotal; payment_mode in enum.

**Acceptance**: a new customer with 3 services can be billed in ≤ 6 clicks + typing name/phone. Totals on screen equal totals on the saved invoice to the paisa.

### 5.4 Invoice detail — `GET /invoices/{id}`
- Full invoice preview (same partial as the public page).
- Buttons: **Send on WhatsApp** (see §6), **Copy link**, **Download PDF**, **Print**, **Duplicate** (pre-fills New Bill), **Void** (owner; requires reason; confirm dialog).
- Shows: sent status + time, billed by, staff member.
- `POST /invoices/{id}/mark-sent` `(json)` sets `whatsapp_sent_at` (idempotent).
- `POST /invoices/{id}/void` (owner). Voided invoices render with a large "VOID" watermark on all views incl. PDF (regenerate PDF on void).

### 5.5 Invoices list — `GET /invoices`
- Filters: date range (default this month), status, payment mode, customer search (name/phone), sent/unsent. Paginated 25.
- Columns: number, date, customer, items summary (first 2 + "+n"), total, payment, sent ✓, actions.
- **Export CSV** for the current filter (owner).

### 5.6 Customers — `GET /customers`, `GET /customers/{id}`
- List with search (name/phone), total spent, last visit. Paginated.
- Detail: profile (edit name/phone/gender/notes), visit history (invoices), lifetime total, quick "New Bill for this customer".
- Merge duplicates is out of scope; phone is unique so duplicates are prevented at the source.

### 5.7 Services management (owner) — `GET /services`
- Categories as collapsible sections (rename, reorder via up/down or drag, toggle active, audience).
- Rows: group, name, price (inline-editable — click, type, Enter saves via `PATCH /services/{id}`), price_max, duration, active toggle, reorder.
- "Add service" inline in any category; "Add category".
- Bulk: "Increase all prices in this category by X%" (nice-to-have; do last).
- Deactivating instead of deleting; deleting is allowed only if the service was never billed (else disable the button and explain).

**Acceptance**: owner changes "Haircut – Men" from 225 to 250 in one click + typing; the next New Bill shows 250; an existing invoice at 225 is unchanged.

### 5.8 Expenses — `GET /expenses` · `POST /expenses`
- Quick-add form at top (date default today, category with datalist suggestions, description, amount, payment mode). List below with month filter and totals. Edit/delete own entries; owner can edit all.

### 5.9 Reports (owner; staff sees only "Today")
- `GET /reports/daily?date=YYYY-MM-DD` — **Daily Statement**:
  - Header: date, invoices count, customers served.
  - Earnings: total; by payment mode (Cash/UPI/Card/Other).
  - Invoice list (number, customer, total, mode).
  - Expenses list + total, by payment mode.
  - **Net = earnings − expenses**; also "Cash in hand = cash earnings − cash expenses".
  - Print-friendly (`@media print`) and **Download PDF** (same DomPDF pipeline).
- `GET /reports/monthly?month=YYYY-MM` — per-day table (earnings, expenses, net) + totals; by payment mode; top 10 services by revenue; earnings per staff member.
- `GET /reports/services?from&to` — service-wise count & revenue.
- CSV export on each.

### 5.10 Settings (owner) — `GET /settings`
- Salon: name, tagline, address, display phone, WhatsApp number, logo upload (png/jpg, stored on `public` disk), footer text.
- Invoice: prefix, next number (read-only), tax rate (default 0; label "GST %").
- WhatsApp: message template (textarea with placeholder legend, live preview).
- Users: list, add (name/email/role/password), deactivate, reset password.
- Staff members: list/add/deactivate.

### 5.11 Public (no auth)
- `GET /i/{code}` — invoice HTML page (see §7).
- `GET /i/{code}/pdf` — streams the stored PDF (`Content-Disposition: inline`, filename `WowSalon-WS-0001.pdf`).
- Unknown code → friendly 404 ("This invoice link is not valid").

---

## 6. WhatsApp Delivery

### 6.1 Phase 1 — `wa.me` click-to-chat (ship this)

**How it works:** `https://wa.me/<E164 without +>?text=<url-encoded text>` opens WhatsApp Web / Desktop (or the app on mobile) with the recipient and message pre-filled. The receptionist presses Enter. Works for numbers not saved in contacts.

**Prerequisite (document in README):** the salon laptop must have WhatsApp Web (`web.whatsapp.com`) logged in with the salon's phone — one-time QR scan. Keep that tab open all day.

**Implementation**
```php
interface WhatsAppSender {
    /** Returns a URL to open (phase 1) or null when sent server-side (phase 2). */
    public function send(Invoice $invoice, string $message): ?string;
}

class WaMeLinkSender implements WhatsAppSender {
    public function send(Invoice $invoice, string $message): ?string {
        return 'https://wa.me/' . $invoice->customer->phone . '?text=' . rawurlencode($message);
    }
}
```
Bind in a service provider based on `config('salon.whatsapp_driver')` (`wame` | `cloud`). The Vue button receives the URL from the controller (`whatsapp_url` prop) and renders `<a :href target="_blank" rel="noopener">`; on click it also POSTs `mark-sent`.

**Message template** (stored in settings, editable by owner). Default:

```
{greeting} {customer_name}! 🙏
Thank you for visiting {salon_name}.

Your invoice {invoice_number} for ₹{total} is here:
{invoice_link}

We look forward to seeing you again!
```

Placeholders: `{greeting}` (Good morning / afternoon / evening by Asia/Kolkata time), `{customer_name}` (first name only — split on space), `{salon_name}`, `{invoice_number}`, `{total}` (Indian grouping, no decimals if whole: `1,400`), `{invoice_link}`, `{date}`, `{items}` (comma-separated descriptions, max 3 then "+n more").

Newlines must survive URL-encoding (`rawurlencode` turns `\n` into `%0A` — correct). Keep the message under ~700 chars.

### 6.2 Phase 2 — WhatsApp Cloud API (optional, later; do NOT build in v1)
- Requires a Meta Business account, business verification, a phone number registered to the Cloud API (it can no longer be used with the normal WhatsApp app), and an approved **utility** message template (e.g. `invoice_ready` with variables name, invoice number, link). Per-conversation pricing applies.
- Implement `CloudApiSender` calling `POST https://graph.facebook.com/v{N}/{phone_number_id}/messages` with the template. Mark sent on 2xx; store `whatsapp_message_id`; log failures and fall back to showing the `wa.me` button.
- Keep the interface identical so switching is a config change.

### 6.3 Things to get right
- Store the customer phone normalised so the link never has spaces/`+`/`0` prefixes.
- The `app_url` setting must be the real public HTTPS domain, otherwise the link in the message is wrong. Assert it's set on the Send button (show a warning banner if not).
- Don't auto-open the link on invoice save — the receptionist should be able to review first.

---

## 7. Public Invoice Page & PDF

### 7.1 `GET /i/{code}` (Blade, not Inertia — must be light and work on any phone)
- Mobile-first, single column, no JS required. Salon logo/name/address/phone at top.
- Invoice number, date, customer first name + phone masked (`98XXXX3210`).
- Items table: description, qty, rate, amount. Subtotal, discount, (tax if > 0), round-off, **Total**. Payment mode & status.
- "Thank you for visiting" line. **Download PDF** button (→ `/i/{code}/pdf`). Footer: `{footer_text}` (default "Powered by 2iT").
- Open Graph tags so the WhatsApp link preview reads "Wow Salon – Invoice WS-0001 – ₹1,400" (title/description; logo as `og:image` if set).
- `robots: noindex`.
- Void invoices show a "VOID" banner.

### 7.2 PDF — `PdfRenderer`
- `barryvdh/laravel-dompdf`. A4 portrait, one page for ≤ 20 lines. Use a **table-based** Blade template (`resources/views/pdf/invoice.blade.php`) — DomPDF has no flexbox/grid. Font: **DejaVu Sans** (bundled, supports `₹`). Inline CSS only.
- Generate once on invoice create; store at `storage/app/invoices/{invoice_number}.pdf`; regenerate on void or on `php artisan invoice:regenerate-pdf {id}`. If the file is missing when requested, regenerate on the fly.
- Same template is reused (with a different layout wrapper) for the Daily Statement PDF.

---

## 8. Reports — calculation rules

- Earnings = sum of `invoices.total` where `status = issued` and `invoice_date` in range (use `invoice_date`, not `created_at`).
- Void invoices are listed in the daily statement in a separate "Voided" section with reason, excluded from totals.
- Expenses = sum of `expenses.amount` in range.
- Net = Earnings − Expenses. Cash in hand = cash earnings − cash expenses.
- All dates are Asia/Kolkata calendar dates. Set `config('app.timezone') = 'Asia/Kolkata'`.
- `ReportService` returns plain DTO arrays consumed by both Inertia pages and the PDF/CSV exporters — one source of truth.

---

## 9. Non-functional Requirements

- **Phone normalisation** (`PhoneNumber::normalise()`): strip everything non-digit; if 10 digits and starts with 6–9 → prefix `91`; if 11 digits starting with `0` → drop `0`, prefix `91`; if 12 digits starting with `91` → keep; else reject with a validation error. Display as `+91 98765 43210`.
- **Money**: `decimal(10,2)`; Indian grouping in UI (`₹1,40,000`); totals rounded to nearest rupee with `round_off` recorded.
- **Invoice numbering**: `InvoiceNumber::next()` inside the same DB transaction, using `SELECT ... FOR UPDATE`-equivalent (for SQLite, wrap in a transaction with `BEGIN IMMEDIATE` — Laravel's `DB::transaction` is enough given single-process PHP-FPM; also add a unique index as the backstop and retry once on collision).
- **Performance**: service picker loads the whole active catalog once per page (≈250 rows, tiny) and filters client-side — no per-keystroke requests. Customer lookup is the only live query.
- **Responsive**: billing screen designed for a laptop (≥1024px); everything else must work on a phone (owner checks reports on mobile).
- **Accessibility/UX**: large touch targets on the picker, visible focus, no modals for the happy path.
- **Audit**: `user_id` on invoices/expenses; `voided_by`; that's enough for v1.
- **Backups**: nightly `spatie/laravel-backup` of the SQLite file + `storage/app/invoices` to an S3-compatible bucket (DigitalOcean Spaces). Keep 30 days. Scheduler: `backup:run --only-db` daily 02:00 IST + weekly full.
- **Logging**: default Laravel daily log; log every WhatsApp send click and every void.
- **Packages allowed**: `laravel/framework`, starter kit (`inertiajs`, `vue`, `tailwindcss`), `barryvdh/laravel-dompdf`, `spatie/laravel-backup`, `pestphp/pest`, `laravel/pint`. Nothing else without asking.

---

## 10. Seed Data — Service Catalog

Transcribed from the salon's printed menu (13 photos). Create `database/seeders/ServiceCatalogSeeder.php` that is **idempotent** (upsert by category name + group_name + name). Prices in ₹. `audience` per category. Where the print was handwritten, ambiguous or offset, it's flagged `(verify)` — seed the value shown and leave a `// verify` comment; the owner can fix prices in the UI anyway.

Format: `group | name | price | extra`. A `–` group means `group_name = null`.

### Cut & Style – Women `(women)`
```
– | Female Haircut | 500
– | Fringe Cut | 150
– | Girls Haircut (upto 12 years) | 350
Hair Wash | Upto Shoulder | 200
Hair Wash | Below Shoulder | 300
Hair Wash | Upto Waist | 400
Premium Wash (Sulfate Free) | Upto Shoulder | 240   (menu says "20% extra" — derived)
Premium Wash (Sulfate Free) | Below Shoulder | 360   (derived)
Premium Wash (Sulfate Free) | Upto Waist | 480       (derived)
Blow Dry | Upto Shoulder | 200
Blow Dry | Below Shoulder | 350
Blow Dry | Upto Waist | 450
Blow Dry with Wash | Upto Shoulder | 400
Blow Dry with Wash | Below Shoulder | 500
Blow Dry with Wash | Upto Waist | 600
```

### Color Services – Women `(women)` — every row exists twice: `(Basic)` and `(Ammonia Free)`
```
Basic Touch Up (2 inches) | Basic | 1150
Basic Touch Up (2 inches) | Ammonia Free | 1400
Basic Touch Up (4 inches) | Basic | 1400
Basic Touch Up (4 inches) | Ammonia Free | 1800
Global Color / Highlights – Upto Neck | Basic | 1650
Global Color / Highlights – Upto Neck | Ammonia Free | 2000
Global Color / Highlights – Upto Shoulder | Basic | 2500
Global Color / Highlights – Upto Shoulder | Ammonia Free | 3000
Global Color / Highlights – Below Shoulder | Basic | 3000
Global Color / Highlights – Below Shoulder | Ammonia Free | 3500
Global Color / Highlights – Upto Waist | Basic | 3500
Global Color / Highlights – Upto Waist | Ammonia Free | 4000
Balayage / Ombre – Upto Neck | Basic | 3000
Balayage / Ombre – Upto Neck | Ammonia Free | 3500
Balayage / Ombre – Upto Shoulder | Basic | 4000
Balayage / Ombre – Upto Shoulder | Ammonia Free | 4500
Balayage / Ombre – Below Shoulder | Basic | 5000
Balayage / Ombre – Below Shoulder | Ammonia Free | 6500
Balayage / Ombre – Upto Waist | Basic | 7000
Balayage / Ombre – Upto Waist | Ammonia Free | 7500
Color Streak | Basic | 300
Color Streak | Ammonia Free | 350
Pre Lightening | Basic | 2000
Pre Lightening | Ammonia Free | 2000
```

### Hair Treatments – Women `(women)`
```
– | Dandruff / Dry Scalp Treatment | 1250
Olaplex | Upto Shoulder | 2500          (menu spells "Olapex")
Olaplex | Below Shoulder | 2800
Olaplex | Upto Waist | 3000
Olaplex | Upto Shoulder (DD) | 3000
Olaplex | Below Shoulder (DD) | 3300
Olaplex | Upto Waist (DD) | 3500
Dryness Control Treatment | Upto Shoulder | 1500
Dryness Control Treatment | Below Shoulder | 1750
Dryness Control Treatment | Upto Waist | 2250
Color Protect Treatment | Upto Shoulder | 800
Color Protect Treatment | Below Shoulder | 1000
Color Protect Treatment | Upto Waist | 1250
```

### Texture Services – Women `(women)`
```
Keratin | Upto Neck | 2500
Keratin | Upto Shoulder | 3500
Keratin | Below Shoulder | 4500
Keratin | Upto Waist | 5500
QOD | Upto Neck | 3000
QOD | Upto Shoulder | 4000
QOD | Below Shoulder | 5000
QOD | Upto Waist | 6250
Botox | Upto Neck | 4000            (handwritten — verify)
Botox | Upto Shoulder | 5000        (handwritten — verify)
Botox | Below Shoulder | 6000       (handwritten — verify)
Botox | Upto Waist | 7000           (handwritten — verify)
Oleo Shape Shine (Straight) | Upto Neck | 2500
Oleo Shape Shine (Straight) | Upto Shoulder | 3000
Oleo Shape Shine (Straight) | Below Shoulder | 4500
Oleo Shape Shine (Straight) | Upto Waist | 5500
Oleo Shape Shine (Straight) | Regrowth (<4 inch) / Crown | 3000
Oleo Shape Shine (Bond) | Upto Neck | 3000
Oleo Shape Shine (Bond) | Upto Shoulder | 3500
Oleo Shape Shine (Bond) | Below Shoulder | 5500
Oleo Shape Shine (Bond) | Upto Waist | 6500
Oleo Shape Shine (Bond) | Regrowth (<4 inch) / Crown | 4000
```

### Cut & Style – Men `(men)`
```
– | Haircut – Men | 225
– | Haircut – Boys / Kids | 175
– | Shave | 150
– | Beard Crafting | 175
– | Hair Wash with Hairstyle | 150
– | Hair Style | 100
```

### Color Services – Men `(men)`
```
Hair Color – Men | Basic | 900
Hair Color – Men | Ammonia Free | 1100
Beard Color | Basic | 250
Beard Color | Ammonia Free | 350
Sidelock / Moustache | Basic | 150
Sidelock / Moustache | Ammonia Free | 200
```

### Hair Treatments – Men `(men)`
```
– | Head Massage | 200                        (handwritten — verify)
– | Head Massage with Wash | 250              (handwritten — verify)
– | Premium Head Massage | 300                (handwritten — verify)
– | Premium Head Massage with Wash | 350      (handwritten — verify)
– | Hair Spa | 650
Olaplex | Upto Neck – Men | 2000
Olaplex | Upto Neck – Men (DD) | 2500
– | Dryness Control Treatment | 1250
– | Color Protect Treatment | 900
– | Dandruff / Dry Scalp Treatment | 800
– | Scalp Detox | 400
```

### Threading `(all)`
```
– | Eyebrows | 60
– | Upper Lip | 30
– | Lower Lip | 30
– | Sidelocks | 60
– | Cheeks | 80
– | Chin | 40
– | Forehead | 30
– | Jawline | 80
– | Full Face | 250
– | Earlobes | 50
– | Nose | 30
```

### Peel Off Wax `(all)`
```
– | Eyebrows | 100
– | Upper Lip | 50
– | Lower Lip | 50
– | Sidelocks | 100
– | Cheeks | 150
– | Chin | 70
– | Forehead | 70
– | Jawline | 100
– | Full Face | 350
– | Earlobes | 80
– | Nose | 50
```

### Regular Wax `(women)`
```
– | Underarms | 40
– | Full Arms (with Underarms) | 200
– | Half Arms | 150
– | Full Legs | 450
– | Half Legs | 300
– | Full Front / Back | 500
– | Half Front / Back | 300
– | Stomach / Chest | 250
– | Full Body (without Brazilian) | 2000
```

### Liposoluble Wax `(all)` — printed values appear shifted one row below their labels; interpreted as below (verify with owner)
```
Underarms | Women | 80
Underarms | Men | 200
Full Arms (with Underarms) | Women | 300
Full Arms (with Underarms) | Men | 1000
Half Arms | Women | 200
Half Arms | Men | 750
Full Legs | Women | 500
Full Legs | Men | 1500
Half Legs | Women | 400
Half Legs | Men | 1000
Full Front / Back | Women | 600
Full Front / Back | Men | 1500
Half Front / Back | Women | 400
Half Front / Back | Men | 800
Stomach / Chest | Women | 400
Stomach / Chest | Men | 1000
Behind | Women | 500
Brazilian | Women | 1000
Full Body (without Brazilian) | Women | 2500
Full Body (with Brazilian) | Women | 3000
```

### O3+ D-Tan / Bleach `(all)`
```
– | Full Face & Neck | 400
– | Neck | 150
– | Underarms | 200
– | Full Arms | 800
– | Half Arms | 600        (partly obscured — verify)
– | Full Legs | 1000       (partly obscured — verify)
– | Half Legs | 800
– | Full Front / Back | 1000
– | Half Front / Back | 800
– | Stomach | 500
– | Full Body | 2250
```

### Clean Ups & Masks `(all)`
```
– | Clean & Clear Clean Up (30 mins) | 700 | desc: Cleansing, exfoliation & mask; duration 30
– | Quick Glow Mask | 500
– | Youth Brightening / Rubber Mask | 1000
– | Collagen Mask | 1500
```

### Basic Facials `(all)`
```
– | Perfect Balance Facial | 1000 | desc: All skin types; cleanse, blackhead removal, massage & pack; duration 40
– | Anti Tan Facial | 1400 | desc: All skin types except sensitive & acne; duration 45
– | Oxyblast Facial | 1600 | desc: All skin types; oxygen facial; duration 45
– | Glovite Facial | 1750 | desc: Instant skin lightening; not for sensitive & acne skin; duration 45
– | Sensi Glow Facial | 1800 | desc: For sensitive skin only; duration 60
– | Signature Facial | 2000 | desc: All skin types; duration 60
```

### Premium Facials `(all)`
```
– | Episyl Facial (Pro Matte / Pro Hydra / Pro Merge) | 2500 | desc: Oily, dry & combination skin
– | Power Brightening Facial | 2800 | desc: Dull & lifeless skin, controls pigmentation; duration 70
– | Ultra Relaxing Facial | 3000 | desc: Sensitive skin; oats & botanical actives
– | Anti Aging Facial | 3000 | desc: Dry & mature skin; duration 70
– | Gensyl Facial (Walnut-Ginger / Papaya Marshmallow) | 3500 | desc: Dull, dry skin; hyperpigmentation
– | Thalgo Facial | 3850 | desc: Hydra marine; dull, dehydrated skin
– | Bride / Groom Facial | 4000 | desc: Vitamin C; duration 90
– | Dermasyl Blanch Facial | 4500 | desc: Fine lines, wrinkles, sensitivity & hyperpigmentation
```

### Hands & Feet Care `(all)`
```
Basic | Cut & File (Hands / Feet) | 100
Basic | Cut, File & Paint (Hands / Feet) | 200
Basic | Regular Manicure | 550
Basic | Regular Pedicure | 600
Basic | Cocktail Manicure | 650
Basic | Cocktail Pedicure | 750
Advance | Relaxing Manicure | 800
Advance | Relaxing Pedicure | 1000
Advance | Crystal Spa Manicure | 1400
Advance | Crystal Spa Pedicure | 1800
Advance | Premium AVL Manicure | 2250
Advance | Premium AVL Pedicure | 2650
Advance | Reflexology | 600
Advance | Heel Peel Treatment | 2000
```

### Nail Care `(all)`
```
– | Gel Nail Paint – Hands | 800
– | Gel Nail Paint – Feet | 600
– | Permanent Gel Extension | 2500
– | Temporary Extension – Hands | 1000
– | Nail Art per finger (with color) | 60
– | Nail Art Adv. per finger (glitter / sticker) | 80
– | Nail Art Adv. per finger (stones / accessories) | 100 | price_max 500
– | Extension Removal – Gel | 800
– | Extension Removal – Acrylic | 1000
– | Gel Nail Paint Removal | 150
```

### Makeup – Bride & Bridesmaids `(women)`
```
– | Engagement / Baby Shower / Mehendi / Sangeet Makeup (with Hairstyle & Draping) | 4000
– | Wedding Makeup with Styling & Draping | 8000
– | Reception Makeup with Styling & Draping | 6000
– | Party Makeup | 1000
– | Party Makeup with Styling & Draping | 1800
– | Open Hairstyle | 800
– | Bun / Updo | 1200
– | Hair Accessories (as per actual) | 0      (price entered at billing)
– | Venue Charges (as per distance / time / location) | 0   (price entered at billing)
```

### Makeup – Men `(men)`
```
– | Groom Makeup (Wedding / Reception) | 1000
– | Party Makeup for Men | 500
```

**Other seeds:** `SettingsSeeder` (defaults from §4), `UserSeeder` (one owner `owner@wowsalon.local` / password from `.env` `SEED_OWNER_PASSWORD`, one staff), `StaffMemberSeeder` (empty), and a `DemoDataSeeder` (20 customers, 60 invoices over the last 30 days, 15 expenses) used **only** in local/dev — never in production.

---

## 11. Build Phases (execute in order)

Each phase ends with: tests green, `npm run build` green, `vendor/bin/pint` clean, commit.

### Phase 0 — Scaffold
- `laravel new wowsalon --vue` (Inertia + Vue 3 + Tailwind starter kit). Pest for tests.
- SQLite; `APP_TIMEZONE=Asia/Kolkata`; `APP_URL` from `.env`.
- Remove registration routes. Add `role` to users + `EnsureRole` middleware.
- Base layout: sidebar (Dashboard, New Bill, Invoices, Customers, Expenses, Reports, Services*, Settings*) — `*` owner only. Mobile: collapsible.
- `config/salon.php` with `whatsapp_driver`, currency, defaults.
- Composer: `barryvdh/laravel-dompdf`, `spatie/laravel-backup`. Publish configs.
- Seeders: settings, users. `README.md` with local setup (`composer install`, `npm i`, `php artisan migrate --seed`, `npm run dev`).

**Done when:** login works, owner sees the sidebar, staff doesn't see owner-only items.

### Phase 1 — Catalog
- Migrations + models: `service_categories`, `services`, `staff_members`, `settings`.
- `ServiceCatalogSeeder` from §10 (idempotent).
- `/services` management page (§5.7) with inline price edit. Owner-only.
- Tests: seeder runs twice without duplicates; inline price update; staff gets 403.

### Phase 2 — Customers & Billing
- Migrations + models: `customers`, `invoices`, `invoice_items`.
- `PhoneNumber` value object + tests (§9).
- `InvoiceNumber::next()`, `InvoiceService::create()`, totals calculator (pure class, unit-tested for flat/percent discount, rounding).
- `GET /customers/lookup` json.
- New Bill page (§5.3). Invoices list (§5.5). Customers pages (§5.6).
- Tests: create invoice for new customer creates customer; existing phone reuses customer; item snapshots don't change when service price changes; numbering is sequential; validation errors.

### Phase 3 — Invoice output & WhatsApp
- Public Blade invoice page + PDF template + `PdfRenderer`. Regenerate command.
- Invoice detail page (§5.4) with Send / Copy / PDF / Print / Duplicate / Void.
- `WhatsAppSender` interface + `WaMeLinkSender` + template rendering with placeholders (§6). Settings page section for the template with live preview.
- Tests: public page renders for a valid code and 404s for an invalid one; PDF file exists after create; wa.me URL is built with a normalised phone and encoded newlines; mark-sent is idempotent; void regenerates PDF with watermark and excludes from totals.

### Phase 4 — Expenses & Reports
- `expenses` migration/model, page (§5.8).
- `ReportService` + daily / monthly / services reports (§5.9), CSV exports, Daily Statement PDF.
- Dashboard (§5.2) using the same service.
- Tests: daily totals by payment mode; void excluded; net & cash-in-hand; date boundaries in IST (an invoice at 23:30 IST belongs to that day).

### Phase 5 — Settings, polish, ops
- Full Settings page (§5.10): salon details, logo, users, staff members, tax rate.
- Empty states, loading states, toasts, keyboard shortcuts on New Bill.
- Backup config + scheduler entry. `DemoDataSeeder`.
- `docs/DEPLOY.md` (§13) and `docs/SALON-USER-GUIDE.md` — 1 page, plain English, with the WhatsApp Web login step.

### Phase 6 (optional, only if asked) — Cloud API sender, thermal print layout, bulk price update, split payments.

---

## 12. Testing

- **Pest** feature tests per phase as listed. Target: every controller action has at least one happy-path and one authorisation test.
- Unit tests for `PhoneNumber`, `InvoiceTotals`, `InvoiceNumber`, template placeholder rendering, `ReportService` date boundaries.
- Use an in-memory SQLite for tests (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` in `phpunit.xml`).
- No browser tests in v1 (Dusk/Playwright) — keep CI to `composer test` + `npm run build`.

---

## 13. Deployment & Ops

- **Server:** Laravel Forge → new site on an existing DigitalOcean droplet (or a $6 droplet). PHP 8.3+, Nginx, no MySQL/Redis needed. Domain `<to be confirmed>` with Forge's free Let's Encrypt SSL.
- **Env:** `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://<domain>`, `APP_TIMEZONE=Asia/Kolkata`, `DB_CONNECTION=sqlite`, `DB_DATABASE=/home/forge/<site>/database/database.sqlite`, `SESSION_DRIVER=database`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`, `FILESYSTEM_DISK=local`, backup bucket creds.
- **Deploy script:** `git pull`, `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`, `php artisan migrate --force`, `php artisan db:seed --class=ServiceCatalogSeeder --force` (idempotent), `php artisan optimize`, `php artisan storage:link`.
- **Scheduler:** Forge cron `* * * * * php artisan schedule:run`. Backups nightly to DO Spaces.
- **Monitoring:** Forge's site health check on `/up`. That's it.
- **First run checklist:** set Settings (name, address, phone, logo, template), create staff user, log WhatsApp Web in on the salon laptop, send a test invoice to the owner's own number.

---

## 14. Assumptions & Open Questions (implement as assumed; humans will confirm)

| # | Assumption | If wrong, impact |
|---|-----------|------------------|
| A1 | Salon brand in the app is **"Wow Salon"**; the "Meraki" menu photos are the service list to seed. | Settings change only. |
| A2 | Public domain is TBD (transcripts mention `wowsalon.to` and a `.com`). Use `APP_URL` everywhere; never hard-code. | `.env` change only. |
| A3 | "Send automatically" in transcript 2 is satisfied by the one-click `wa.me` flow for v1; fully automatic sending is Phase 6 (Cloud API, needs Meta verification + cost). | Build `CloudApiSender` later behind the same interface. |
| A4 | No GST now; `tax_rate` setting defaults to 0 and is hidden from bills while 0. | Set the rate in Settings; no code change. |
| A5 | One payment mode per invoice (no split payments). | Phase 6 item. |
| A6 | Invoices can be edited only by voiding and duplicating (no in-place edit) — keeps numbering and audit clean. | Could add same-day edit later. |
| A7 | Menu values flagged `(verify)` in §10 are seeded as read; owner corrects in UI. | None — UI-editable. |
| A8 | Receptionist uses a laptop (MacBook per transcript) with WhatsApp Web logged in with the salon's number. | If they use the owner's phone number instead, nothing changes technically. |
| A9 | English UI only. | Add Hindi/Gujarati strings later via Laravel lang files if needed. |
| A10 | "Powered by 2iT" footer on invoice page & PDF, editable in Settings. | — |

---

*End of plan.*
