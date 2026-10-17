# Square Dealer Manager — SPEC (PHP 8.4 / Laravel)

Owner: M/s Khaza Enterprise (Square distributor code 4831) · Host: cPanel shared hosting, `mke.rockproviders.com` · Version: 1.0, 2026-10-17

This spec rewrites the original brief for PHP/Laravel. Every business rule, module, core rule, the golden test and the milestones are kept as written. Only the technology mapping changed. Assumptions and defaults live in [DECISIONS.md](DECISIONS.md); the delivery plan is in [PLAN.md](PLAN.md). In the code repo these become `docs/SPEC.md`, `docs/DECISIONS.md` and `docs/PLAN.md`.

---

## 1. Business context

- M/s Khaza Enterprise buys stock from Square Toiletries Ltd. (purchase invoices and Mushak 6.3 challans) and sells to retailers through sales representatives (SR) and routes. Many sales are on credit.
- The firm name **M/s Khaza Enterprise** is a setting (`firm_name`) and prints on every invoice, challan and report. Distributor code `4831` is also a setting.
- Invoices reach the app as Excel files that the owner uploads manually. The photographed samples (Square Invoice Report, Mushak 6.3) define the fields the Excel templates must cover. The owner's sample Excel files go in `docs/samples/` in the repo.
- Currency BDT, lakh/crore grouping (`12,34,567.89`), fiscal year July to June, English and Bangla UI.

## 2. Deployment target (hard constraint; design everything around it)

| Constraint | Laravel implementation |
|---|---|
| cPanel shared hosting (CloudLinux, LiteSpeed), subdomain `mke.rockproviders.com`. No root, no Docker, no terminal, no always-on processes. | Plain PHP request/response. No Octane, Reverb, Horizon, websockets, Supervisor or daemons. |
| Runtime | **PHP 8.4** (confirmed). Laravel 12.x or the current stable major at M0, pinned in `composer.lock`. |
| Confirmed extensions | bcmath, intl, mbstring, pdo_mysql, zip, gd, fileinfo, xml, openssl, curl. Only pure-PHP packages that need nothing beyond these (plus PHP built-ins such as ctype, tokenizer, dom, json, filter, session, iconv, zlib, which the env check verifies). |
| Forbidden | Anything needing compilation, PECL, system binaries or `exec()`: imagick, wkhtmltopdf, Browsershot/Puppeteer, `mysqldump` binary, Redis, Node on the server. |
| Web entry (was `passenger_wsgi.py`) | Subdomain **document root = Laravel `public/`** (`~/mke/app/public`). Fallback when the panel forces a docroot under `public_html`: an `index.php` + `.htaccess` shim in the docroot that loads the app from `~/mke/app` and calls `$app->usePublicPath(__DIR__)`. |
| Database | MySQL/MariaDB created in cPanel, `pdo_mysql`, InnoDB, `utf8mb4_unicode_ci`. SQLite only for local dev and fast unit tests. Schema changes only through Laravel migrations. |
| No terminal (was Execute python script) | **Protected admin Maintenance page** at `/maintenance` with buttons for: environment check, run migrations, create admin, backup now, run jobs now, clear/rebuild caches, view logs. Each button calls the matching artisan command in-process (`Artisan::call`). The same commands run from cron: `sdm:check`, `migrate --force`, `sdm:create-admin`, `sdm:backup`, `sdm:restore`, `sdm:recompute`. |
| Maintenance access | Requires `MAINTENANCE_TOKEN` from `.env`. Before any user exists the token alone opens it; afterwards owner login **and** token. The env-check view is stateless so it works before `APP_KEY` and the database exist. |
| Background work (was `run_jobs.py`) | One cPanel cron line every minute: `php artisan schedule:run`. The scheduler runs `queue:work database --stop-when-empty --max-time=50 --memory=96` (`withoutOverlapping`) plus nightly backup, scheduled Google refreshes, reminders and pruning. Queue driver = `database` (`jobs`, `failed_jobs`, `job_batches` tables). User-visible job status lives in `app_jobs` (type, status, progress, result file, error). No threads or processes outlive a request. |
| Config (was python-dotenv) | `.env` in `~/mke/shared/`, **outside the web root and outside the replaceable app folder**. `bootstrap/app.php` calls `useEnvironmentPath()` and `useStoragePath()` on `../shared`. cPanel environment variables are not relied on. |
| Files | Uploads, generated exports, backups and logs in `~/mke/shared/storage` (outside web root). Served only through authenticated, permission-checked routes (`Storage::download`). No `storage:link`. |
| Shared-hosting limits | Low memory (target < 128 MB per request), paginate every list (default 50), every request < 30 s, heavy work to the queue. Large reads stream with OpenSpout; large writes stream to disk. |
| Composer may be unavailable | `vendor/` is built **off-server** by GitHub Actions: `composer install --no-dev -o`, compiled CSS, vendored JS, zipped as a release asset. The server never runs Composer or Node. |
| Frontend | Server-rendered **Blade** + **HTMX** + **Alpine.js**. **Tailwind** compiled with the standalone CLI into one `public/css/app.css`, committed and shipped in the release zip. HTMX/Alpine vendored in `public/js/`. Bangla font (Noto Sans Bengali / Hind Siliguri woff2) self-hosted. |
| Deploy docs | `DEPLOY.md` with exact cPanel steps (subdomain, PHP version selector, MySQL database, cron, `.env`, first deploy, updates) and a rollback plan. See [PLAN.md](PLAN.md) §3. |

## 3. Core rules (implemented exactly)

1. Purchase document = stock in. Sales document = sales quantity and stock out.
2. Purchase VAT is paid by Square. A setting `vat_recoverable` (default true) excludes VAT from stock cost.
3. Free goods: bonus qty and combo-bonus lines reduce stock, create zero revenue, and their cost is reported as free-goods cost.
4. Costing is weighted average per product (optionally per batch with FEFO). Recompute the full ledger in date order after any create, edit, void or back-dated entry. On the same date, purchases come before sales.
5. Profit and loss = sales revenue (net of discount) − COGS − free-goods cost + net commission (if `count_commission` is on) − stock losses − operating expenses.
6. Commission is configurable: `commission_pct` (default 5), `commission_vat_pct` (default 0.62), net = difference (4.38). `commission_base` is `sales` or `purchase` (ex-VAT). All of these have effective dates; reports use the rate valid on each document date.
7. **Golden test** (an automated test case, `tests/Golden/GoldenTest.php`): buy 100 at 23.83 with 15% recoverable VAT; sell 40 plus 5 bonus at 30 →

| Figure | Expected |
|---|---|
| Revenue | 1200.00 |
| COGS | 953.20 |
| Free goods | 119.15 |
| Gross | 127.65 |
| Commission (base = sales) gross | 60.00 |
| Commission VAT part | 7.44 |
| Commission net | 52.56 |
| Profit before expenses | 180.21 |

### 3.1 Engine details (how the rules are computed)

- **Stock cost of a purchase line** = value ex-tax − line discount − allocated header discount + SD (if `sd_in_cost`, default on) + VAT (only if `vat_recoverable` is off). Purchase bonus qty enters stock at zero cost.
- **Ledger order** for one product: `doc_date`, then type rank (opening 0, purchase 1, sales return 2, adjustment-in 3, sale incl. bonus 4, purchase return/claim-out 5, loss/damage/adjustment-out 6), then posting time, then id. This implements "purchases before sales on the same date".
- **Weighted average**: keep running `qty` and `value`. Issue cost = round(value × issue_qty ÷ qty, 2); when issuing the full balance, issue cost = remaining value (no drift).
- **Recompute**: any create/edit/void/back-dated entry rebuilds the ledger of each affected product (or batch) from the earliest affected date. A property test proves this equals a full rebuild from scratch after random edits.
- **FEFO** (setting `costing_mode = batch_fefo`, per product `batch_tracked`): issues consume the earliest-expiry batch first, each batch carrying its own average.
- **Free goods** (bonus qty, combo-bonus lines): stock out at average cost; that cost goes to `free_goods_cost`, revenue 0.
- **Sales revenue** = invoiced amount as charged (VAT-inclusive TP, no output VAT split) − line discounts − header discounts (slab adjustment, combo bonus price discount, trade discount) ± `rounding_adjustment`. Header discounts are allocated to lines pro rata for margin-by-product.
- **Commission** = base × rate valid on the document date, per document, rounded to 2 dp; VAT part = base × `commission_vat_pct`; net = gross − VAT part.

## 4. Stack mapping

| Concern | Brief (Python) | Laravel |
|---|---|---|
| Framework | Flask (WSGI) | Laravel 12+ on PHP 8.4 |
| ORM / migrations | SQLAlchemy 2.0, Alembic | Eloquent, Laravel migrations |
| DB driver | PyMySQL | `pdo_mysql` |
| Validation | Pydantic v2 / Marshmallow | Form Requests + Validator |
| Exact numbers | `Decimal` | `brick/math` `BigDecimal` (uses bcmath); custom Eloquent casts `MoneyCast`, `QtyCast`, `RateCast`. **Never float**: DB returns DECIMAL as strings, arithmetic only via BigDecimal, `ROUND_HALF_UP`. |
| Column types | money DECIMAL(14,2), qty DECIMAL(14,3) | same; unit price/cost DECIMAL(14,4); internal running averages DECIMAL(18,6); percentages DECIMAL(7,4) |
| Password hashing | Werkzeug | Laravel `Hash` (bcrypt, cost 12; argon2 if available) |
| Templates | Jinja | Blade components |
| i18n | Flask-Babel | Laravel localization `lang/en`, `lang/bn` (+ `bn.json`); per-user locale; Bangla digits and lakh/crore formatting via a string-based formatter (no float) |
| Excel | openpyxl | **PhpSpreadsheet** (templates, styled exports, small files); **OpenSpout** (streaming read/write for large files) |
| PDF | Browser print; ReportLab for English-only | Print-styled Blade views saved as PDF from the browser (correct Bangla shaping); **dompdf** for English-only server-side PDFs (Drive uploads, scheduled jobs) |
| Google | google-api-python-client | **google/apiclient** with services trimmed to Drive, Sheets, Docs (`Google\Task\Composer::cleanup`) |
| 2FA | (unspecified) | **pragmarx/google2fa** (TOTP) + **bacon/bacon-qr-code** SVG renderer (no imagick) |
| QR on invoices | (unspecified) | bacon/bacon-qr-code SVG |
| Roles/permissions | (unspecified) | spatie/laravel-permission |
| DB backup | (scripts) | druidfi/mysqldump-php (pure PDO, no `mysqldump` binary), gzip, zip of uploads via `ZipArchive` |
| HTTP (SMS, WhatsApp) | requests | Laravel `Http` (Guzzle over curl) |
| Logging | file outside web root | Monolog daily JSON log in `shared/storage/logs` |
| Tests | pytest | PHPUnit (or Pest) with SQLite in-memory locally; MySQL service in GitHub Actions |

## 5. Modules

### 5.1 Auth and security
- Roles: owner, accountant, SR, data entry, viewer; per-role permissions editable by the owner.
- Secure session cookies (`Secure`, `HttpOnly`, `SameSite=Lax`), CSRF on every form and HTMX request, login rate limiting (`RateLimiter`, 5/min per user+IP), optional TOTP 2FA with recovery codes.
- Full audit log: who, when, IP, entity, action, old and new values (JSON), written by a model observer trait on every audited model.

### 5.2 Master data
- **Products**: code, Bangla and English name, brand, pack, unit and carton/dozen conversion, buy price, sell price, reorder level, aliases, batch and expiry flag, SD%, VAT%.
- **Retailers**: code, address, phone (normalised to `01XXXXXXXXX`), route, SR, credit limit, price list.
- Suppliers, SRs, routes, vehicles, price lists with effective dates, opening balances (stock, receivables, payables, cash, bank).
- Seed data: products from the sample purchase challan (`database/seeders/data/products-seed.csv`, 24 candidates).

### 5.3 Purchases and sales
- Multi-line documents with qty, bonus qty, unit price, line discount, VAT and SD percent, notes, attachments, SR, route, vehicle.
- Header fields for printed totals (subtotal, slab adjustment, combo bonus price discount, rounding adjustment, net total) and the sample header fields (BINs, addresses, delivery date, invoice type).
- Duplicate protection: same number, party and total.
- States: draft, posted, void.

### 5.4 Payments
- Methods: cash, bKash, Nagad, Rocket, bank, cheque, post-dated cheque (with due date and clearing status).
- Allocate partial payments to invoices; advances; rep collection on an imported invoice becomes a payment.
- Receivables and payables ledgers, ageing (0–30, 31–60, 61–90, 90+), retailer statements, credit-limit warnings at order entry.

### 5.5 Returns and claims
- Sales returns, damaged and expired returns, credit notes, claims against Square with status tracking (open, submitted, approved, settled, rejected).
- Schemes and incentives (target bonus, trade discount) recorded as income or cost lines.

### 5.6 Stock
- Ledger per product and batch; stock count and adjustment; loss and damage.
- Low-stock and near-expiry alerts.
- Comparison page against the owner's master software export (upload CSV or Excel, highlight differences).

### 5.7 Accounts
- Cash book, bank book, day book, daily closing, petty cash.
- Expense categories: van rent, fuel, toll, loading and unloading, godown rent, SR salary and TA-DA, mobile bill, trade licence, other.

### 5.8 Reports and dashboard
- Today and month sales, gross margin, net profit, stock value, dues, top products, top retailers.
- SR-wise and route-wise sales and collection, margin by product.
- VAT purchase and sales registers.
- Profit and loss by day, month and fiscal year (July–June).

### 5.9 Invoice import (Excel, manual upload)
- No AI and no external API. Owner uploads `.xlsx` (and `.csv`) for purchase or sales invoices.
- Downloadable Excel templates: (a) header sheet + lines sheet, (b) flat sheet with one row per line. Fields per [samples/field-analysis.md](samples/field-analysis.md) §2.
- Saved column-mapping profiles so exports from Square or the master software import without editing.
- One file can hold many invoices, grouped by invoice number.
- Owner chooses document type (purchase or sale) on upload; the app warns if the party looks wrong (M/s Khaza Enterprise on the buyer side = purchase).
- Parse with PhpSpreadsheet (small files) or OpenSpout (large files). Strip thousands separators and `%`; accept `dd/mm/yyyy` and `dd-mm-yyyy`; treat Amount text "Bonus" as a bonus row attached to the previous numbered line; skip section headings and "Sub Total" rows; normalise Bangla digits for matching.
- Validate: qty × price − discount = amount per line (tolerance `qty × 0.005`, minimum 0.01, because Square prints rounded unit prices); lines sum to the subtotal (±0.01); flag mismatches, duplicates (same number, party and total), unknown products, zero or negative quantities and bad dates. Store printed amounts and discounts as given; never recompute them from percentages.
- Match products by code, then name + pack, then the alias table, which learns from the owner's corrections. English challan names and Bangla invoice names link to the same SKU through aliases.
- Preview with editable rows and per-row errors; post only after confirmation; undo a whole import batch (voids all its documents and recomputes).
- Photos and PDFs can be attached to an invoice as proof but are not read.
- Import runs inside the request for files up to a few thousand rows (budget 20 s); larger files go through the jobs queue with a progress bar.

### 5.10 Printing
- A4 invoice and challan, 80 mm thermal receipt, Bangla and English, QR code (SVG), firm name M/s Khaza Enterprise in the header.

### 5.11 Reminders
- SMS or WhatsApp due reminders through a pluggable provider interface (`ReminderChannel` contract; drivers: log, generic HTTP SMS gateway, WhatsApp Cloud API). Sent from the queue.

## 6. Edit, delete, customize everywhere
- Every entity has create, edit, delete (soft), restore and bulk edit in the UI.
- Posted documents are voided, never hard-deleted. Editing a posted document recomputes everything and writes an audit entry.
- Prices, discounts, commission, VAT and rates can be overridden per line, customer, route or price list, with effective dates.
- Month-end lock; only the owner can unlock (audited).
- Settings screens for every business rule above, plus custom fields on products and retailers.

## 7. Export and Google integration
- Every table and report exports to Excel (.xlsx), PDF (print view) and CSV, honoring current filters and column choices. Large exports run as queued jobs and appear in a Downloads list.
- Settings > Connect Google: OAuth 2.0, scopes `drive.file`, `spreadsheets`, `documents`; refresh tokens encrypted with `APP_KEY` (Laravel `encrypted` cast); disconnect button; redirect URI `https://mke.rockproviders.com/google/callback`.
- Features: export any report to a Google Sheet (new or existing, optional scheduled refresh through the jobs queue); import price lists from a Sheet; save PDFs and Excel files to a chosen Drive folder; generate statements, invoices and reports as Google Docs from templates; nightly backup to Drive.

## 8. Settings (business rules, all editable)

| Key | Default | Effective-dated |
|---|---|---|
| `firm_name` | M/s Khaza Enterprise | no |
| `distributor_code` | 4831 | no |
| `vat_recoverable` | true | yes |
| `sd_in_cost` | true | yes |
| `count_commission` | true | yes |
| `commission_pct` | 5.00 | yes |
| `commission_vat_pct` | 0.62 | yes |
| `commission_base` | sales | yes |
| `costing_mode` | weighted_average (alt: batch_fefo) | no |
| `revenue_vat_inclusive` | true | yes |
| `import_line_tolerance_per_qty` | 0.005 | no |
| `fiscal_year_start_month` | 7 (July) | no |
| `default_locale` | en (alt: bn) | no |
| `near_expiry_days` | 60 | no |
| `backup_retention_days` | 14 | no |

## 9. Non-functional
- Test the costing and P&L engine heavily: recompute after random edits must equal rebuilding from scratch (seeded random property tests). Test key flows end to end (HTTP feature tests).
- Laravel migrations, seed data (products from the sample purchase challan), structured JSON logging to a file outside the web root, a protected health and version page (`/health`: DB, queue lag, last cron heartbeat, disk, version, commit).
- Nightly backup by cron: database export, zip of uploads, upload to Google Drive, keep 14 days, plus a restore command/Maintenance button and a tested restore procedure.
- Accessibility (WCAG 2.1 AA: labels, contrast, keyboard), mobile-first layouts for SR screens, installable PWA (manifest + service worker) with offline-tolerant entry (drafts queued in IndexedDB, synced with idempotency keys).
- `README.md` for local setup and `DEPLOY.md` for cPanel.

## 10. Milestones
- **M0** deploy smoke test: Laravel page on the subdomain, MySQL connection, `.env` loading, one cron job, one migration run from the Maintenance page; **stop and confirm it works before continuing**.
- **M1** setup, auth, roles, audit, i18n.
- **M2** master data.
- **M3** purchase and sales documents with stock and costing engine and golden test.
- **M4** payments, receivables, payables, cash and bank books.
- **M5** returns, claims, batch and expiry.
- **M6** P&L, dashboard, reports, month lock.
- **M7** export engine and Google integration.
- **M8** Excel invoice import with templates, column mapping and review screen.
- **M9** printing, reminders, PWA.
- **M10** hardening, backups, go-live.

Work in milestones, commit after each one, write tests, record every assumption in `docs/DECISIONS.md`, ask the owner only when blocked. Deliverables and acceptance checks per milestone: [PLAN.md](PLAN.md).
