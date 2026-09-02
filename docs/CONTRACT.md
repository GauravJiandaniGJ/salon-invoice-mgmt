# Shared Contract — Wow Salon (phases 2–5)

Owned by the Orchestrator. Agents implement this exactly. If something here is wrong or
missing, stop and report it — do not invent contract details.

Already built & committed (do not redo): Phase 0 (auth, roles, sidebar, settings table,
`Setting::get()`), Phase 1 (catalog, `/services` page), all migrations + models + factories
for customers / invoices / invoice_items / expenses, `App\Support\PhoneNumber`,
`routes/web.php` (complete), `resources/js/types/index.ts` (complete), `resources/js/lib/money.ts`.

## Ownership

| Path | Owner |
|---|---|
| `routes/web.php`, `resources/js/types/index.ts`, `app/Models/*`, migrations, factories, `config/salon.php`, `tests/Pest.php`, `HandleInertiaRequests` | Orchestrator (read-only for agents; minimal *additive* edits allowed only if unavoidable — list them in your report) |
| `app/Http/Controllers/Billing/*`, `app/Http/Requests/Billing/*`, `app/Services/{InvoiceNumber,InvoiceTotals,InvoiceService}.php`, `tests/Feature/Billing/*`, `tests/Unit/{PhoneNumber,InvoiceTotals,InvoiceNumber}Test.php` | **B1 backend-billing** |
| `app/Http/Controllers/Public/*`, `app/Services/PdfRenderer.php`, `app/Services/WhatsApp/*`, `app/Console/Commands/RegenerateInvoicePdf.php`, `resources/views/public/*`, `resources/views/pdf/*`, `app/Providers/AppServiceProvider.php`, `tests/Feature/Output/*`, `tests/Unit/MessageTemplateTest.php`, **plus** `InvoiceController@show/markSent/void/pdf` (see B1/B2 split below) | **B2 backend-output** |
| `app/Http/Controllers/{DashboardController,ExpenseController,ReportController}.php`, `app/Http/Requests/ExpenseRequest.php`, `app/Services/ReportService.php`, `app/Services/CsvExporter.php`, `resources/views/pdf/daily-statement.blade.php`, `tests/Feature/{Expenses,Reports,Dashboard}/*`, `tests/Unit/ReportServiceTest.php` | **B3 backend-reports** |
| `app/Http/Controllers/Settings/*`, `app/Http/Requests/Settings/*`, `database/seeders/DemoDataSeeder.php`, `database/seeders/DatabaseSeeder.php`, `config/backup.php`, `routes/console.php`, `docs/DEPLOY.md`, `docs/SALON-USER-GUIDE.md`, `README.md`, `tests/Feature/Settings/*` | **B4 backend-settings-ops** |
| `resources/js/pages/bills/*`, `resources/js/pages/invoices/*`, `resources/js/pages/customers/*`, `resources/js/components/billing/*`, `resources/js/lib/invoiceTotals.ts`, `resources/js/lib/phone.ts` | **F1 frontend-billing** |
| `resources/js/pages/Dashboard.vue`, `resources/js/pages/expenses/*`, `resources/js/pages/reports/*`, `resources/js/pages/settings/*`, `resources/js/components/{FlashToasts,EmptyState,Pagination,MoneyInput,DateInput,PaymentModeChips,StatCard}.vue`, `resources/js/layouts/app/AppSidebarLayout.vue` (toasts only), `resources/js/lib/{dates,format}.ts` | **F2 frontend-ops** |

Rules: never `git commit`; never run `migrate:fresh` on `database/database.sqlite` (tests use in-memory SQLite);
run only your own test files while developing (`php artisan test tests/Feature/Billing`), the full suite once at the end.
Frontend agents verify with `npm run build` (retry once if it fails on a manifest race with the other frontend agent).
Everything is Inertia unless marked `(json)` / `(blade)` / `(file)`.

## Conventions

- Money: rupees, `decimal(10,2)`; JSON props are **numbers** (cast with `(float)`), never strings.
- Dates in props: `invoice_date`/`expense_date` = `YYYY-MM-DD`; datetimes = ISO-8601 (`->toISOString()`), timezone Asia/Kolkata (`config('app.timezone')`).
- Redirect-after-write with `->with('success', '...')` or `->with('error', '...')` → shows as a toast (shared prop `flash`).
- Validation: Form Requests; errors are standard Inertia `errors` keyed exactly as the payload field (nested: `customer.phone`, `items.0.unit_price`).
- Payment modes: `cash|upi|card|other`. Round-off: totals rounded to nearest rupee.
- Authorisation: `role:owner` middleware on owner routes (already in routes). Per-record checks (expenses edit-own) inside controllers → `abort(403)`.

## Calculation rules (must match in PHP `App\Services\InvoiceTotals` and TS `resources/js/lib/invoiceTotals.ts`)

```
line_total      = round2(unit_price * quantity)
subtotal        = round2(sum(line_total))
discount_amount = type == 'percent' ? round2(subtotal * value / 100) : (type == 'flat' ? round2(value) : 0)
                  clamp to [0, subtotal]
taxable         = subtotal - discount_amount
tax_amount      = round2(taxable * tax_rate / 100)          (tax_rate = Setting tax_rate, default 0)
raw_total       = taxable + tax_amount
total           = round(raw_total, 0)   // nearest rupee, PHP round() half-up; JS: Math.round(raw_total + 1e-9)
round_off       = round2(total - raw_total)
```
`InvoiceTotals::calculate(array $items, ?string $discountType, float $discountValue, float $taxRate): array{subtotal,discount_amount,tax_amount,round_off,total,items: [{...item, line_total}]}`.

`InvoiceNumber::next(string $prefix): string` → `"{prefix}-{NNNN}"`, N = (max numeric suffix over all invoices) + 1, zero-padded to 4, grows past 9999. Must be called inside the `DB::transaction` that inserts the invoice; unique index is the backstop (retry once on collision).

`public_code`: 10 chars `[A-Za-z0-9]`, unique (loop until unique).

## Phone rules — `App\Support\PhoneNumber` (built)

`normalise()` throws `InvalidArgumentException` on bad input; `display()` → `+91 98765 43210`; `masked()` → `98XXXX3210`. Frontend mirror in `resources/js/lib/phone.ts` (`normalisePhone(input): string|null`, `displayPhone(normalised)`).

## WhatsApp — `App\Services\WhatsApp`

```php
interface WhatsAppSender { public function send(Invoice $invoice, string $message): ?string; } // returns URL (wame) or null
class WaMeLinkSender implements WhatsAppSender  // 'https://wa.me/'.$phone.'?text='.rawurlencode($message)
class MessageTemplate { public function render(string $template, Invoice $invoice): string; }
```
Bound in `AppServiceProvider` from `config('salon.whatsapp_driver')` (`wame` only for now; `cloud` → throw `RuntimeException('Cloud API sender not implemented')`).
Placeholders: `{greeting}` (Good morning <12:00, Good afternoon <17:00, else Good evening, IST), `{customer_name}` (first name), `{salon_name}`, `{invoice_number}`, `{total}` (Indian grouping, no decimals when whole), `{invoice_link}`, `{date}` (`2 Sep 2026`), `{items}` (max 3 descriptions then `+n more`).
Public link = `rtrim(Setting::get('app_url'), '/').'/i/'.$public_code`. `app_url_missing` = setting empty or contains `localhost`.

## PDF — `App\Services\PdfRenderer`

`render(Invoice $invoice): string` (stores to `local` disk at `invoices/{invoice_number}.pdf`, sets `pdf_path`, returns path); `download(Invoice): Response` (regenerates if file missing; `Content-Disposition: inline; filename="{salon_name_slug}-{invoice_number}.pdf"`); `dailyStatement(array $report): string` (binary PDF). DomPDF, A4 portrait, tables only, DejaVu Sans, inline CSS; void → large diagonal "VOID" watermark. Template `resources/views/pdf/invoice.blade.php`; public HTML `resources/views/public/invoice.blade.php`; shared partial `resources/views/partials/invoice-body.blade.php` may be used by both.

Command: `php artisan invoice:regenerate-pdf {id}`.

## Pages & props

### `GET /dashboard` → `Dashboard` — props `DashboardData` (`today`, `month` (null for staff), `recent_invoices` last 10 `InvoiceRow`)

### `GET /bills/new` → `bills/New`
Props: `catalog: CatalogCategory[]` (active categories & services only, ordered), `staff_members: StaffMemberOption[]` (active),
`payment_modes: PaymentMode[]`, `tax_rate: number`, `today: 'YYYY-MM-DD'`, `can_edit_date: boolean` (owner),
`prefill: BillPrefill | null` — filled when `?customer_id=` (customer only) or `?duplicate={invoice_id}` (everything).

### `POST /invoices` — payload `BillPayload` → `redirect()->route('invoices.show', $invoice)` with success flash.
Validation: `customer.phone` valid per PhoneNumber (custom rule / closure, message from the exception); `customer.name` required **only if** no customer exists with that phone (`required_without` style check in `withValidator`); `customer.gender` nullable in `female|male|other`; `staff_member_id` nullable exists; `invoice_date` nullable date, ignored unless owner; `items` required array min 1; `items.*.service_id` nullable exists:services,id; `items.*.description` required string max 160; `items.*.unit_price` required numeric min 0; `items.*.quantity` required numeric gt 0 max 999; `discount_type` nullable in flat|percent; `discount_value` numeric min 0; percent ≤ 100; flat ≤ subtotal (message "Discount cannot exceed the subtotal"); `payment_mode` in enum; `payment_status` in enum; `notes` nullable max 1000.
`InvoiceService::create(array $validated, User $user): Invoice` — DB transaction: find-or-create customer (update name if provided & different? **No** — keep existing name; only set gender if customer has none) → number → insert invoice + items (description = provided; when `service_id` given and description empty use `Service::display_name`) → totals → public_code → `PdfRenderer::render` → update customer `last_visit_at`, `total_spent += total` → return.
`InvoiceService::void(Invoice $invoice, User $by, string $reason): void` — sets status/void fields, `total_spent -= total`, regenerates PDF, logs.
**B1 builds `create()`/`void()` and `InvoiceController@index/exportCsv`; B2 builds `PdfRenderer` and `InvoiceController@show/markSent/void/pdf`.** B1 must call `app(PdfRenderer::class)->render($invoice)` — B2 owns that class; until it exists B1 may stub nothing: B1 writes `InvoiceService` to call `PdfRenderer` and B2 ships the class. To keep B1 tests green independently, B1 may `$this->mock(PdfRenderer::class)` in its tests. Both put `InvoiceController` methods in the **same file** `app/Http/Controllers/Billing/InvoiceController.php`: B1 creates the file with `index` + `exportCsv` and leaves a clearly marked region `// ---- B2: show / markSent / void / pdf ----`; B2 fills it in (use Edit, not Write).

### `GET /customers/lookup?phone=` (json) → `CustomerLookupResponse`. Invalid phone → `{found:false, customer:null, normalised_phone:null, error:"..."}` with HTTP 200.
`GET /customers/lookup?q=` (json, 2+ chars) → `{found:false, customer:null, normalised_phone:null, error:null, matches: CustomerLookup[]}` — name contains (case-insensitive) OR phone digits contain the digits of `q`; max 8; ordered by `last_visit_at` desc (nulls last). Under 2 chars → `matches: []`. The New Bill customer block shows these as a suggestions dropdown while typing in either the phone or the name field.

### `GET /invoices` → `invoices/Index` — props `filters: InvoiceFilters` (defaults: from/to = first/last day of current month, others ''), `invoices: Paginated<InvoiceRow>` (25/page, newest first, `withQueryString`). `q` matches customer name/phone (digits) or invoice number. `sent` = `sent|unsent` on `whatsapp_sent_at`.
`GET /invoices/export.csv` (file, owner) — same filters, columns: `Invoice, Date, Customer, Phone, Items, Subtotal, Discount, Tax, Total, Payment, Status, Sent`.

### `GET /invoices/{invoice}` → `invoices/Show` — props `invoice: InvoiceDetail`, `whatsapp_url: string|null`, `whatsapp_message: string`, `public_url: string`, `pdf_url: '/invoices/{id}/pdf'`, `app_url_missing: boolean`, `can_void: boolean` (owner && issued).
`POST /invoices/{id}/mark-sent` (json) → `{ whatsapp_sent_at: string }` — idempotent (does not overwrite an existing timestamp). Logs.
`POST /invoices/{id}/void` (owner) payload `{reason: string required max 200}` → back with success. 422 flash error if already void.
`GET /invoices/{id}/pdf` (file) — auth'd PDF stream (same as public).

### `GET /customers` → `customers/Index` — props `filters: {q: string}`, `customers: Paginated<CustomerRow>` (25/page, by last_visit_at desc nulls last). `visits` = count of issued invoices.
### `GET /customers/{customer}` → `customers/Show` — props `customer: CustomerDetail`, `invoices: Paginated<InvoiceRow>` (10/page).
### `PATCH /customers/{customer}` payload `{name, phone, gender, notes}` — phone normalised + unique (ignore self).

### `GET /expenses?month=YYYY-MM` → `expenses/Index` — props `month`, `expenses: ExpenseRow[]` (that month, newest first), `totals: {total, by_mode: ByMode}`, `categories: string[]` (config list ∪ distinct used), `payment_modes`. `can_edit` = owner || own entry.
`POST /expenses` payload `ExpensePayload`; `PATCH /expenses/{id}`; `DELETE /expenses/{id}` — back with flash; 403 when not allowed.

### Reports — `App\Services\ReportService`
`daily(CarbonInterface $date): array` (= `DailyReport`), `monthly(string $ym): array` (= `MonthlyReport`), `services(CarbonInterface $from, CarbonInterface $to): array` (= `ServicesReport`), `dashboard(User $user): array` (= `DashboardData`). Use `invoice_date` for ranges; only `status = issued` counts; voided listed separately in daily. Plain arrays, numbers as floats.
`GET /reports/daily?date=` → `reports/Daily` — props `report: DailyReport`, `can_pick_date: boolean` (owner; staff always gets today regardless of `date`).
`GET /reports/daily/pdf?date=` (file) — `PdfRenderer::dailyStatement`. `GET /reports/daily.csv?date=` (file).
`GET /reports/monthly?month=` → `reports/Monthly` — props `report: MonthlyReport`. `.csv` = per-day table.
`GET /reports/services?from&to` → `reports/Services` — props `report: ServicesReport` (defaults: current month). `.csv`.
CSV via `App\Services\CsvExporter::stream(string $filename, array $header, iterable $rows): StreamedResponse`.

### `GET /settings` → `settings/Index` — props `settings: SalonSettings`, `next_invoice_number: string`, `users: SettingsUserRow[]`, `staff_members: SettingsStaffRow[]`, `whatsapp_placeholders: string[]`.
`PATCH /settings` payload = `SalonSettings` minus `logo_url` (all optional-ish: `salon_name` required; `tax_rate` numeric 0–100; `invoice_prefix` required alpha 1–6 uppercase; `salon_whatsapp_number` nullable valid phone; `app_url` nullable url). Saves each via `Setting::set`.
`POST /settings/logo` multipart `{logo: image png/jpg ≤ 2MB}` → stored on `public` disk `logos/…`, `Setting::set('logo_path', …)`. `DELETE /settings/logo`.
`GET /settings/whatsapp-preview?template=` (json) → `{message: string}` rendered with a sample/latest invoice (fake data if none).
`POST /settings/users` `{name, email unique, role, password min 8}`; `PATCH /settings/users/{user}` `{name?, role?, is_active?, password?}` — cannot deactivate/demote yourself; at least one active owner must remain.
`POST /settings/staff-members` `{name}`; `PATCH /settings/staff-members/{id}` `{name?, is_active?}`.

### Public (blade, no auth, `throttle:60,1`)
`GET /i/{code}` — mobile-first HTML; unknown code → 404 view `resources/views/public/invoice-not-found.blade.php` ("This invoice link is not valid"); OG tags (`og:title` = "{salon} – Invoice {number} – ₹{total}"), `robots noindex`; void banner; Download PDF button → `/i/{code}/pdf`.
`GET /i/{code}/pdf` (file) — inline PDF.

## Frontend behaviour notes

- New Bill (F1): phone input autofocus; ≥10 digits → debounce 300 ms → lookup; found → fill name + "Last visit … · ₹…" + link to `/customers/{id}`; not found → name required. Audience toggle Women/Men/All (default from gender: female→women, male→men, else all); category chips; search ≥2 chars over `group_name + name + category name`; click → add/increment; `price_max` services prompt inline for the price; custom line; discount toggle flat/%; tax row hidden while `tax_rate == 0`; payment chips; Enter in search adds first result; `Ctrl/⌘+S` saves; submit via `router.post('/invoices', payload)`; show server validation errors.
- Invoice Show (F1): Send on WhatsApp = `<a :href="whatsapp_url" target="_blank" rel="noopener" @click="markSent">`; warning banner when `app_url_missing`; Copy link (`navigator.clipboard`), Download PDF (`pdf_url`), Print (`window.print()` on the page, print CSS hides chrome), Duplicate (`/bills/new?duplicate={id}`), Void (owner; dialog with reason). Show sent status/time, billed by, staff.
- F2 provides shared components listed in ownership; F1 may import them **only after F2 reports** — to stay unblocked F1 should use plain Tailwind + existing `components/ui/*` and not depend on F2 components.
- Toasts (F2): watch `usePage().props.flash`; auto-dismiss 4 s.
- Empty states everywhere; loading via `form.processing` / `router.on('start'|'finish')`.
