# Square Dealer Manager — Plan

Stack: PHP 8.4 + Laravel on cPanel shared hosting, `mke.rockproviders.com`. Requirements: [SPEC.md](SPEC.md). Assumptions: [DECISIONS.md](DECISIONS.md).

Rules for every milestone: work on a branch, add tests, CI must pass (unit + MySQL feature tests), update `docs/DECISIONS.md`, merge, tag a release (`v0.N.0`), and the owner deploys the release zip. **M0 stops for owner confirmation before M1 starts.**

---

## 1. Milestones

### M0 — Deploy smoke test
Deliverables
- Laravel skeleton with shared-path bootstrap (`.env` and `storage/` from `~/mke/shared`), `deploy/shim/` fallback.
- Home page: "Square Dealer Manager · M/s Khaza Enterprise", app version, DB status, last cron heartbeat.
- `/maintenance` (token-protected): env check (PHP version, extensions, writable folders, `.env` loaded, DB connection, `APP_KEY`, PHP binary path and ready-to-paste cron line), Generate APP_KEY, Run migrations.
- One migration: `smoke_heartbeats`. One scheduled task writes a heartbeat row every minute.
- GitHub Actions: `ci.yml` (tests) and `release.yml` (builds `mke-vX.Y.Z.zip` holding `app/` and `shared.example/`).
- Draft `DEPLOY.md` (first install section) and `README.md`.

Acceptance
- Owner completes the smoke test in §4 and sends a screenshot of the Maintenance page, all green.
- `https://mke.rockproviders.com/.env`, `/../shared/` and `/storage/` all return 404/403.
- HTTPS padlock shows (AutoSSL).

### M1 — Setup, auth, roles, audit, i18n
Deliverables
- Create-owner from Maintenance; login/logout; password reset (SMTP or owner reset); login rate limiting; optional TOTP 2FA + recovery codes.
- Roles owner, accountant, SR, data entry, viewer; permission matrix editor.
- Audit log (who, when, IP, old/new JSON) with a viewer and filters.
- Settings store with effective-dated rows; firm name, distributor code.
- English/Bangla switch per user; Bangla font; lakh/crore and Bangla-digit formatter; fiscal year helper (July–June).
- App layout (Blade + HTMX + Alpine + Tailwind), mobile-first navigation, `/health` page.

Acceptance
- Feature tests: each role sees only permitted pages; the 6th failed login within a minute is blocked; 2FA login works; CSRF rejects forged posts.
- Changing a setting creates an audit row with old and new values.
- Formatter tests: `1234567.89` → `12,34,567.89` and `১২,৩৪,৫৬৭.৮৯`; FY of 2026-10-17 = 2026-27.

### M2 — Master data
Deliverables
- CRUD + soft delete + restore + bulk edit for products, aliases, retailers, suppliers, SRs, routes, vehicles, price lists (effective dates), opening balances; custom fields on products and retailers.
- Product seeder from the 24 sample products; phone normaliser; unit/carton/dozen conversion.
- Paginated, filterable lists with column chooser.

Acceptance
- Seeder loads 24 products with correct SD%/VAT%; Chaka Ball Soap buy price 23.83.
- Price lookup returns the price valid on a given date; restore brings back a soft-deleted retailer.
- Lists stay under 30 s and 128 MB with 10,000 seeded retailers.

### M3 — Purchase and sales documents, stock and costing engine, golden test
Deliverables
- Multi-line purchase and sales documents (qty, bonus qty, price, line discount, VAT%, SD%, notes, attachments, SR, route, vehicle), header discounts, rounding adjustment, draft/posted/void, duplicate protection.
- `app/Domain/Costing`: weighted-average ledger, FEFO mode, ordered recompute from the earliest affected date, queued recompute for large changes.
- `app/Domain/Profit`: revenue, COGS, free-goods cost, effective-dated commission.
- Stock ledger page per product.

Acceptance
- **Golden test passes exactly**: revenue 1200.00, COGS 953.20, free goods 119.15, gross 127.65, commission 60.00 / 7.44 / 52.56, profit before expenses 180.21.
- Real-data test: challan line 1 (1,440 for 34,309.44) gives average 23.8260; SD lines include SD in cost.
- Property test (≥ 500 seeded random create/edit/void/back-date sequences): incremental recompute = full rebuild, to the paisa.
- Same-date purchase after sale in entry order is still costed first. Duplicate number+party+total is rejected. Editing a posted document writes an audit entry.

### M4 — Payments, receivables, payables, cash and bank books
Deliverables
- Receipts/payments by cash, bKash, Nagad, Rocket, bank, cheque, post-dated cheque; partial allocation; advances.
- Receivable and payable ledgers, ageing 0–30/31–60/61–90/90+, retailer statements, credit-limit warning.
- Cash book, bank book, day book, daily closing, petty cash, expense categories (brief list).

Acceptance
- Allocation test: 3 invoices, 1 partial payment and 1 advance give correct balances and ageing buckets.
- Day book totals = cash book + bank book movements; daily closing carries the balance forward.
- A post-dated cheque counts only after it is marked cleared.

### M5 — Returns, claims, batch and expiry
Deliverables
- Sales returns (linked/unlinked), damaged and expired returns, credit notes; claims against Square with status workflow; schemes and incentives as income/cost lines.
- Batch and expiry capture, FEFO issues, stock count and adjustment, loss/damage, low-stock and near-expiry alerts.
- Comparison page against the master software export (CSV/XLSX upload, differences highlighted).

Acceptance
- A linked return restores stock at the original issue cost; a damaged return posts a stock loss.
- FEFO test issues the earliest expiry first; the near-expiry alert lists batches within `near_expiry_days`.
- The comparison flags a seeded qty difference and an unknown product.

### M6 — P&L, dashboard, reports, month lock
Deliverables
- P&L by day, month and fiscal year per core rule 5; dashboard (today/month sales, gross margin, net profit, stock value, dues, top products/retailers).
- SR-wise and route-wise sales and collection, margin by product, VAT purchase and sales registers.
- Month-end lock; owner-only unlock (audited).

Acceptance
- P&L for the golden scenario + 50.00 expense = 130.21 net profit.
- Posting or editing into a locked month is refused for all roles; owner unlock writes an audit entry.
- Report totals reconcile with ledgers (test compares P&L revenue to the sum of sales documents).

### M7 — Export engine and Google integration
Deliverables
- Excel (.xlsx), CSV and print-view PDF export on every table and report, honoring filters and chosen columns; large exports queued to Downloads.
- Connect Google (OAuth, `drive.file` + `spreadsheets` + `documents`, encrypted refresh token, disconnect).
- Report to Sheet (new/existing, scheduled refresh), price list import from Sheet, save files to a Drive folder, Google Docs from templates.

Acceptance
- An export of a filtered list contains exactly the filtered rows and columns; a 50,000-row export completes through the queue within memory limits.
- OAuth round trip works on the live subdomain; the token is stored encrypted; disconnect revokes it.
- A scheduled Sheet refresh runs from cron.

### M8 — Excel invoice import
Deliverables
- Downloadable templates (header + lines; flat); upload `.xlsx`/`.csv`; doc type choice with party warning; column-mapping profiles.
- Parser rules from the samples (separators, `%`, two date formats, Bonus rows, combo-bonus section, Sub Total rows, Bangla digits).
- Validation (tolerance `max(0.01, qty × 0.005)`), product matching (code → name+pack → alias, learning from corrections), editable preview with per-row errors, confirm-to-post, undo whole batch; queue for large files.

Acceptance
- Transcribed challan: 17 lines, 0 line errors, 1 header VAT warning (−0.01), totals 5,188 qty / 463,810.63 value / 4,197.39 SD.
- Transcribed sales invoice: 9 lines + 1 bonus + 3 combo-bonus, slab adjustment 419.73, rounding −0.13, net 21,297.00.
- Re-importing the same file flags duplicates; undoing a batch restores stock and P&L to the pre-import state; a correction creates an alias that matches on the next import.
- A 3,000-row file imports in-request in under 20 s; a 20,000-row file goes through the queue.

### M9 — Printing, reminders, PWA
Deliverables
- A4 invoice and challan, 80 mm thermal receipt, Bangla and English, SVG QR code, firm name in the header.
- Reminder provider interface with log, HTTP SMS and WhatsApp Cloud drivers; due-reminder schedule.
- PWA manifest and service worker; offline SR order drafts in IndexedDB, synced with idempotency keys.

Acceptance
- Print views render Bangla correctly in Chrome "Save as PDF" (visual check by owner); the thermal layout fits 80 mm.
- Reminder test uses a fake driver; the scheduled run sends only to retailers past the due threshold.
- Offline: an order made in airplane mode syncs once, with no duplicate after a retry.

### M10 — Hardening, backups, go-live
Deliverables
- Nightly backup (gzipped SQL dump + uploads zip), upload to Drive, 14-day retention; restore command and Maintenance button; tested restore procedure in `DEPLOY.md`.
- Security pass (headers, CSP, session settings, permission audit), accessibility pass, performance pass (indexes, N+1), JSON logs, error pages.
- Final `DEPLOY.md` (first install, updates, rollback, restore) and `README.md`; data entry of opening balances; owner training checklist.

Acceptance
- A restore drill on a scratch database reproduces identical report totals.
- Backup files older than 14 days are pruned locally and on Drive.
- Owner signs off on go-live after one week of parallel running against the master software (stock comparison shows no unexplained differences).

---

## 2. Repository structure

```
square-dealer-manager/
├── app/
│   ├── Console/Commands/       # sdm:check, sdm:create-admin, sdm:backup, sdm:restore, sdm:recompute
│   ├── Domain/                 # framework-light business logic, unit tested
│   │   ├── Money/              # Money, Qty, Rate (brick/math), Formatter (lakh/crore, Bangla digits)
│   │   ├── Costing/            # LedgerOrder, WeightedAverage, Fefo, Recomputer
│   │   ├── Profit/             # PnlCalculator, CommissionCalculator
│   │   ├── Documents/          # posting, voiding, duplicate check, discount allocation
│   │   ├── Payments/           # allocation, ageing
│   │   ├── Import/             # Readers (PhpSpreadsheet, OpenSpout, CSV), Mapping, Validator, Matcher, BatchPoster
│   │   ├── Export/             # table/report exporters (xlsx, csv, print)
│   │   ├── Google/             # OAuth, Sheets, Drive, Docs
│   │   ├── Reminders/          # ReminderChannel contract + drivers
│   │   └── Backup/             # dumper, zipper, retention, restore
│   ├── Http/{Controllers,Middleware,Requests}/
│   ├── Jobs/                   # queued work: exports, imports, recompute, sync, reminders, backup
│   ├── Models/  Policies/  Observers/ (Audit)  Casts/  Providers/
├── bootstrap/app.php           # resolves ../shared for .env and storage
├── config/sdm.php              # app-specific defaults
├── database/{migrations,seeders,factories}/  seeders/data/products-seed.csv
├── deploy/
│   ├── shim/{index.php,.htaccess}   # fallback when docroot must live in public_html
│   ├── shared.example/              # .env.example + storage skeleton
│   └── cron.txt
├── lang/{en,bn}/  lang/bn.json
├── public/                     # subdomain document root
│   ├── index.php  .htaccess  manifest.webmanifest  sw.js
│   ├── css/app.css             # compiled Tailwind (committed)
│   ├── js/{htmx.min.js,alpine.min.js,app.js}
│   └── fonts/
├── resources/{views,css}/      # Blade (layouts, components, modules, print/), Tailwind source
├── resources/import-templates/ # purchase/sale header+lines and flat .xlsx
├── routes/{web.php,maintenance.php,console.php}   # console.php holds the schedule
├── tests/{Unit,Feature,Golden,Property}/
├── docs/{SPEC.md,DECISIONS.md,PLAN.md,samples/}
├── .github/workflows/{ci.yml,release.yml}
├── DEPLOY.md  README.md  composer.json  composer.lock  tailwind.config.js
```

Server layout:

```
/home/USER/mke/
├── app/          # release zip contents (replaced on each update); subdomain docroot = app/public
├── app-prev/     # previous release, kept for rollback
└── shared/       # persistent: .env, storage/{app/private,logs,backups,framework}
```

---

## 3. Deployment approach (no terminal)

**Build (GitHub Actions).** Every tag `vX.Y.Z` runs tests on PHP 8.4 + MySQL, then `composer install --no-dev -o`, compiles CSS, removes tests and dev files, and attaches `mke-vX.Y.Z.zip` (`app/` + `shared.example/`) to the GitHub Release. The server never runs Composer or Node.

**First install** (once; detailed in §4)
1. cPanel: create the MySQL database and user; point the subdomain docroot to `~/mke/app/public`; select PHP 8.4.
2. File Manager: upload and extract the zip into `~/mke`; rename `shared.example` → `shared`; fill in `shared/.env`.
3. Browser: `/maintenance` → env check → Generate APP_KEY → Run migrations → Create owner.
4. cPanel Cron Jobs: paste the one-minute line shown on the env-check page.

**Update** (each release)
1. Maintenance → **Backup now** (wait for "done").
2. File Manager: upload the new zip to `~/mke`; delete the old `app-prev`; rename `app` → `app-prev`; extract the zip (creates a new `app/`; ignore `shared.example`).
3. Maintenance → **Run migrations** → **Clear caches**. Check that the version on the home page changed.

**Rollback**
1. File Manager: rename `app` → `app-bad`, `app-prev` → `app`.
2. If the release changed the database: Maintenance → Restore → pick the backup taken in update step 1 (or import it in phpMyAdmin).
3. Migrations are additive (new tables/columns only; destructive changes wait one release), so step 2 is rarely needed.

**Cron line** (exact path shown by the env check):

```
* * * * * /opt/cpanel/ea-php84/root/usr/bin/php /home/USER/mke/app/artisan schedule:run >> /dev/null 2>&1
```

---

## 4. M0 smoke test — plain-language steps for the owner

You need: cPanel login, the release zip file we send you (or download from GitHub Releases), about 30 minutes. Write down every name and password you create.

1. **Make the database.** cPanel → *MySQL Databases*. Create a database named `mke` (cPanel will show it as something like `rockprov_mke`). Create a user `mke` with a strong password. Under "Add user to database", pick both, tick **ALL PRIVILEGES**, save. Write down the full database name, full user name and password.
2. **Choose PHP 8.4.** cPanel → *Select PHP Version* (or *MultiPHP Manager*). Set PHP 8.4 for `mke.rockproviders.com`.
3. **Point the subdomain.** cPanel → *Domains* (or *Subdomains*). Find `mke.rockproviders.com` → Manage → set the document root to `mke/app/public` → save. If cPanel refuses a folder outside `public_html`, tell us; we will send the small "shim" version.
4. **Upload the app.** cPanel → *File Manager*. Open your home folder, create a folder `mke`, open it, click *Upload*, choose the zip. When it finishes, right-click the zip → *Extract*. You should now see folders `app` and `shared.example`.
5. **Make the settings file.** Rename `shared.example` to `shared`. In File Manager → *Settings* (top right), tick **Show Hidden Files**. Open `shared`, right-click `.env.example` → *Rename* to `.env`, then right-click → *Edit* and fill in:
   - `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` from step 1
   - `MAINTENANCE_TOKEN` = a long secret phrase only you know (at least 20 characters)
   - Save.
6. **Turn on the padlock.** cPanel → *SSL/TLS Status* → if `mke.rockproviders.com` is not green, click *Run AutoSSL* and wait a few minutes.
7. **Open the Maintenance page.** In your browser go to `https://mke.rockproviders.com/maintenance` and type your secret phrase. You see a checklist.
8. **Create the app key.** If "APP_KEY" is red, click **Generate key**. (If it says it cannot write the file, copy the key it shows into `APP_KEY=` in `.env` with File Manager and save.) Reload: it turns green.
9. **Check the list.** PHP 8.4, every extension, folders writable, settings file loaded, database connected: all green. If something is red, take a screenshot and send it to us.
10. **Run the first migration.** Click **Run migrations**. You should see a message listing the tables created, including `smoke_heartbeats`.
11. **Add the cron job.** Copy the line in the "Cron command" box. cPanel → *Cron Jobs* → Common Settings: **Once Per Minute** → paste the line into *Command* → *Add New Cron Job*.
12. **Wait two minutes**, then reload the Maintenance page. "Last cron heartbeat" should say less than 2 minutes ago, in green.
13. **Open the home page** `https://mke.rockproviders.com`. You should see "Square Dealer Manager · M/s Khaza Enterprise", a version number, "Database: OK" and "Cron: OK".
14. **Safety check.** Open `https://mke.rockproviders.com/.env`. It must show "Not Found" or "Forbidden", never your settings.
15. **Send us** a screenshot of the Maintenance page and the home page. **We stop here until you confirm.**

If any step fails, do not retry randomly. Send a screenshot of the error and the step number.
