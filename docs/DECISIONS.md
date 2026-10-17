# Decisions and assumptions log

Every assumption made for Square Distributor Manager. Newest entries go at the bottom; superseded entries stay, marked **Superseded by #n**. In the code repo this file is `docs/DECISIONS.md`.

Format: **#. Title** — date · Decision · Reason · How to change.

---

## Stack and hosting

**1. Stack switched from Python/Flask to PHP/Laravel** — 2026-10-17
- Decision: build on PHP 8.4 + Laravel (12.x or the current stable major at M0, pinned in `composer.lock`). The Python/Flask design in the brief is dropped.
- Reason: PHP is native on every cPanel host and needs no Passenger app setup. The owner asked for the best fit for shared hosting. The host confirmed PHP 8.4 with the needed extensions.
- Change: a full rewrite. The business rules and tests in the spec are stack-neutral, so they carry over.

**2. Only pure-PHP packages limited to the confirmed extensions** — 2026-10-17
- Decision: allowed extensions are bcmath, intl, mbstring, pdo_mysql, zip, gd, fileinfo, xml, openssl, curl, plus PHP built-ins. Packages: laravel/framework, brick/math, phpoffice/phpspreadsheet, openspout/openspout, google/apiclient, pragmarx/google2fa, bacon/bacon-qr-code, spatie/laravel-permission, dompdf/dompdf, druidfi/mysqldump-php. Nothing that needs `exec()`, PECL, Redis or system binaries.
- Reason: no root, no compiler, and `exec()` is often disabled.
- Change: `composer.json` pins `config.platform.php` to 8.4. The CI build job runs on PHP 8.4 with only these extensions enabled, so a package that needs anything else fails the build. Add a package only when that job passes.

**3. Assumed built-in extensions** — 2026-10-17
- Decision: assume ctype, tokenizer, dom, json, filter, session, iconv, simplexml, xmlreader, xmlwriter, zlib are present (Laravel, PhpSpreadsheet and OpenSpout need them). They were not on the confirmed list.
- Reason: they are compiled into standard cPanel PHP builds (EasyApache / alt-php).
- Change: the M0 env check lists each one. If any is missing, enable it in cPanel > Select PHP Version > Extensions, or ask the host.

**4. Subdomain document root = Laravel `public/`** — 2026-10-17
- Decision: app in `~/mke/app`, persistent data in `~/mke/shared`, subdomain docroot `~/mke/app/public`.
- Reason: keeps code, `.env`, uploads and logs outside the web root. Replaces `passenger_wsgi.py`.
- Change: if cPanel forces the docroot under `public_html`, use the shipped `deploy/shim/` (an `index.php` and `.htaccess` that load `~/mke/app` and set the public path).

**5. Maintenance page replaces "Execute python script"** — 2026-10-17
- Decision: `/maintenance` has buttons for env check, migrate, create admin, backup, restore, run jobs, clear caches and view logs. Each one calls an artisan command in-process. Access needs `MAINTENANCE_TOKEN`; once an owner exists it also needs owner login. The env-check view works without `APP_KEY` or a database and can generate a key.
- Reason: the owner may have no terminal, and web access must not become a back door.
- Change: set `MAINTENANCE_ENABLED=false` in `.env` to switch the page off entirely; cron and artisan still work.

**6. Background work through one cron line** — 2026-10-17 · **Amended by #48** (everything runs in-process)
- Decision: cron runs `php artisan schedule:run` every minute. The scheduler runs `queue:work database --stop-when-empty --max-time=50` (no overlap) plus scheduled tasks. The `app_jobs` table shows status to users. The PHP binary path is shown on the env-check page for copy-paste (for example `/opt/cpanel/ea-php84/root/usr/bin/php` or `/opt/alt/php84/usr/bin/php`).
- Reason: there are no always-on processes. 50 s fits inside the one-minute window.
- Change: if cron is unavailable, the Maintenance "Run jobs now" button processes the queue for up to 20 s per click (degraded mode, no nightly tasks).

**7. `.env` and storage in `~/mke/shared`** — 2026-10-17
- Decision: `bootstrap/app.php` resolves `../shared` relative to the app folder for `.env` and `storage/`. cPanel environment variables are not used.
- Reason: updates replace the whole `app/` folder without touching config, uploads, logs or backups.
- Change: edit the two paths in `bootstrap/app.php`.

**8. `vendor/` built off-server** — 2026-10-17
- Decision: GitHub Actions builds a release zip (`composer install --no-dev -o`, compiled Tailwind CSS, vendored HTMX/Alpine, no tests or dev files). The owner uploads it through File Manager. The server never runs Composer or Node.
- Reason: Composer and Terminal may be unavailable, and shared hosts run out of memory during installs.
- Change: if Composer becomes available, the same repo still works with `composer install --no-dev` on the server.

**9. Deploy by folder swap, no symlinks** — 2026-10-17
- Decision: to update, rename `app` → `app-prev`, extract the new zip as `app`, then press Migrate. To roll back, swap the names back.
- Reason: File Manager cannot create symlinks, and `symlink()` is often disabled.
- Change: an in-app "upload update" button may be added in M10 if extraction fits the host's limits.

**10. Frontend without a server build** — 2026-10-17
- Decision: Blade + HTMX + Alpine. Tailwind is compiled with the standalone CLI to `public/css/app.css`, committed to the repo. Bangla font is self-hosted.
- Reason: no Node on the server, and no external CDN dependency for offline/PWA use.
- Change: rebuild CSS locally or in CI.

**11. Queue, cache, session drivers** — 2026-10-17 · **Deferred to M1 by #46** (M0 uses file/sync) · **Sessions and cache superseded by #70** (they stay on files)
- Decision: queue = database, cache = database, session = database, mail = SMTP (cPanel mailbox) or log.
- Reason: no Redis or Memcached on shared hosting. A database cache also keeps rate limiting consistent.
- Change: `.env` keys `QUEUE_CONNECTION`, `CACHE_STORE`, `SESSION_DRIVER`.

**12. Backups without `mysqldump`** — 2026-10-17
- Decision: druidfi/mysqldump-php (PDO) writes a gzipped SQL dump; `ZipArchive` zips uploads; both upload to Google Drive. 14 days are kept locally and on Drive. Restore runs through `sdm:restore` / a Maintenance button that needs a typed confirmation; phpMyAdmin import is the documented fallback.
- Reason: `exec()` and the `mysqldump` binary are usually unavailable.
- Change: setting `backup_retention_days`.

**13. Server-side PDFs English-only via dompdf** — 2026-10-17
- Decision: user-facing PDFs are print-styled pages saved from the browser. dompdf is used only for English server-generated PDFs (Drive uploads, scheduled jobs).
- Reason: dompdf, like ReportLab, does not shape Bangla conjuncts correctly.
- Change: none planned. Bangla Google Docs output covers server-side Bangla documents.

**14. google/apiclient trimmed** — 2026-10-17
- Decision: keep only the Drive, Sheets and Docs services through `Google\Task\Composer::cleanup`.
- Reason: the full services package is tens of MB and thousands of files, which strains inode quotas.
- Change: list more services in `composer.json` `extra.google/apiclient-services`.

**15. Tests run in CI, not on the server** — 2026-10-17
- Decision: PHPUnit with SQLite in-memory for unit tests, and a MySQL 8 service in GitHub Actions for feature and engine tests. A release is built only when all tests pass.
- Reason: no terminal on the host, and SQLite-only tests can hide MySQL DECIMAL and collation behaviour.
- Change: `.github/workflows/ci.yml`.

## Numbers

**16. Exact decimals, never float** — 2026-10-17
- Decision: brick/math `BigDecimal` for all arithmetic, wrapped in `Money`, `Qty` and `Rate` value objects with Eloquent casts. Rounding is `HALF_UP` at the points the spec defines. Column types: money DECIMAL(14,2), qty DECIMAL(14,3), unit price/cost DECIMAL(14,4), running averages DECIMAL(18,6), percentages DECIMAL(7,4). A CI check (PHPStan rule or grep) fails on `(float)`, `floatval` or `round(` inside `app/Domain`.
- Reason: brief requirement. Unit prices need 4 dp (the true challan unit cost is 23.8260; the combo policy amount 419.7312 has 4 dp).
- Change: the column widths are in the migrations.

**17. Lakh/crore and Bangla digits by a string formatter** — 2026-10-17
- Decision: a custom formatter groups the decimal string (`12,34,567.89`) and maps digits to Bangla (০–৯) for the bn locale. It does not use `NumberFormatter::format`, which takes a float.
- Reason: avoids float conversion.
- Change: `app/Domain/Money/Formatter.php`.

## Business-rule defaults (owner had no answers; each one is a setting)

**18. SD is included in stock cost** — 2026-10-17
- Decision: setting `sd_in_cost` defaults to on, so stock cost = value ex-tax + SD. VAT stays excluded while `vat_recoverable` is on.
- Reason: supplementary duty is normally not creditable. The challan shows SD of 5%, 10% and 15% on Lipgel, Revive and Spring.
- Change: Settings > Costing > "Include SD in cost". The change is effective-dated and triggers a recompute.

**19. Sales revenue is the invoiced VAT-inclusive trade price** — 2026-10-17
- Decision: revenue = the invoiced amount as charged. No output VAT is split out for the distributor. Setting `revenue_vat_inclusive` defaults to true.
- Reason: the samples show the distributor sells at the same VAT+SD-inclusive TP that Square uses, and Square pays the VAT at source.
- Change: Settings > Revenue. If set to false, revenue is shown ex-VAT using the product VAT%.

**20. Slab adjustment and combo bonus price discount are invoice-level discounts** — 2026-10-17
- Decision: both are stored as header discounts that reduce revenue and are allocated to lines pro rata. The 4-dp "Combo Bonus Policy (Price Disc.)" is stored for information; the 2-dp slab adjustment is what gets posted.
- Reason: in the sample, 419.7312 = exactly 4% × lines 2+3+4, and the slab adjustment 419.73 is that figure rounded.
- Change: Settings > Import > "Header discount source" (slab adjustment / combo policy / both).

**21. Whole-taka rounding is its own `rounding_adjustment` line** — 2026-10-17
- Decision: rounding_adjustment = printed net total − (total − header discounts). In the sample it is −0.13. It is part of net revenue and shown as its own row in P&L.
- Reason: Square prints 21,297.00 where the arithmetic gives 21,297.13, and the amount in words confirms 21,297. One sample cannot tell truncation from rounding, so the app stores the printed value rather than applying a rule.
- Change: Settings > Revenue > "Report rounding as" (revenue / other income-expense).

**22. Petroleum jelly 50 ml at 62.30 and 57.81 are separate products** — 2026-10-17
- Decision: two SKUs (challan TP 62.30 versus the invoice "৫২ পিস" at 57.81). The 15 ml variants also stay separate.
- Reason: different prices and pack text; merging wrongly would corrupt the average cost.
- Change: Products > Merge (moves aliases and ledger entries, then recomputes).

## Sample findings

**23. Import line tolerance is about qty × 0.005** — 2026-10-17
- Decision: a line passes when |qty × printed price − discount − amount| ≤ max(0.01, qty × 0.005). Subtotal check: ±0.01. Line VAT/SD checks: ±0.01. Header VAT total versus the sum of lines: ±0.01 (warning, not error).
- Reason: Square prints unit prices rounded to 2 dp while the value is computed from the unrounded price. A fixed ±0.01 would flag 16 of 17 challan lines; the largest gap is −5.76 on 1,440 qty. The printed VAT total is 0.01 below the sum of its lines.
- Change: setting `import_line_tolerance_per_qty`.

**24. Store printed amounts; do not recompute from percentages** — 2026-10-17
- Decision: imported line discounts, values, SD, VAT and totals are stored exactly as printed. Percentages are informational.
- Reason: lotion discounts are about 7.69%, and no single rule reproduces both lines to the paisa.
- Change: none.

**25. Purchase cost uses the printed value, not qty × printed price** — 2026-10-17
- Decision: stock cost comes from the line's printed value ex-tax (for example 34,309.44 for 1,440 soap, which is 23.8260 per unit).
- Reason: the value is what Square charges; the printed unit price is rounded.
- Change: none.

**26. Golden test uses the rounded 23.83** — 2026-10-17
- Decision: the golden test keeps the brief's figures exactly (buy 100 at 23.83). A second test checks the real challan line 1 (1,440 for 34,309.44, average 23.8260).
- Reason: the brief fixes the golden values; the second test covers real data.
- Change: none.

**27. No product codes in the documents** — 2026-10-17
- Decision: matching order is code (if present), then normalised name + pack, then aliases. Normalising strips case, punctuation and spaces and maps Bangla digits to ASCII. English challan names and Bangla invoice names are linked through aliases learned from corrections.
- Reason: neither sample prints product codes.
- Change: if the owner supplies Square codes (from the master software export), code matching takes over automatically.

**28. Free goods in two forms** — 2026-10-17
- Decision: "Bonus" rows (no serial; the Amount column says "Bonus") attach to the previous numbered line as bonus_qty. "Invoice Combo Bonus" section rows become `combo_bonus` lines with amount 0. Both reduce stock at average cost and count as free-goods cost.
- Reason: the sample shows both forms; 42 free pieces are worth 1,829.04 at the printed prices.
- Change: none.

**29. Rep collection becomes a payment** — 2026-10-17
- Decision: a non-zero `rep_collection` on an imported sales invoice creates a cash receipt allocated to that invoice, attributed to the SR.
- Reason: the sample header carries it as cash collected at delivery.
- Change: Settings > Import > "Post rep collection as payment".

**30. Seed products are candidates** — 2026-10-17
- Decision: the 24 products in `products-seed.csv` are seeded with English names from the challan. Bangla names are suggested transliterations (or as printed for sales-only items) and are flagged for owner review.
- Reason: several Bangla names were low-confidence reads, and the challan line 12 qty/price (96 / 186.96) was inferred from totals.
- Change: edit products in the UI.

## Engine

**31. Ledger order within a day** — 2026-10-17
- Decision: opening, purchase, sales return, adjustment-in, sale (incl. bonus), purchase return/claim-out, loss/adjustment-out; then posting time, then id.
- Reason: the brief fixes purchases before sales; putting other inbound movements before outbound ones follows the same logic.
- Change: `app/Domain/Costing/LedgerOrder.php`.

**32. Average-cost issue rounding** — 2026-10-17
- Decision: issue cost = round(stock value × qty ÷ stock qty, 2); issuing the full balance takes the remaining value.
- Reason: no paisa drift; a full rebuild gives identical results.
- Change: none.

**33. Negative stock allowed with warning** — 2026-10-17
- Decision: a sale beyond the available stock posts with a warning and is costed at the last known average (or the product buy price if there is none). A later back-dated purchase fixes it on recompute. Setting `block_negative_stock` defaults to off.
- Reason: back-dated challans often arrive after the sales are entered.
- Change: Settings > Stock.

**34. Purchase bonus qty enters at zero cost** — 2026-10-17
- Decision: free goods received from Square increase stock qty at zero value, which lowers the average.
- Reason: no cost was paid for them; rule 3 covers the sales side only.
- Change: setting `purchase_bonus_cost` (zero / at list price as other income).

**35. Sales returns cost** — 2026-10-17
- Decision: a return linked to an invoice comes back at that sale's issue cost; an unlinked return comes back at the current average. Damaged or expired returns go straight to loss.
- Reason: reverses the original COGS correctly.
- Change: none.

**36. Recompute scope and timing** — 2026-10-17
- Decision: rebuild only the affected products from the earliest affected date, inside the request. If the estimate exceeds 15 s (large imports, rule changes), it is queued, and reports show a "costing update pending" banner until it is done.
- Reason: the 30 s request limit. A property test proves the result equals a full rebuild.
- Change: `config/sdm.php` `recompute_inline_limit`.

**37. Commission defaults** — 2026-10-17
- Decision: `count_commission` defaults to on; the base is net sales revenue after all discounts; it is computed per document with the rate valid on the document date.
- Reason: the golden test includes net commission in profit (180.21).
- Change: Settings > Commission (effective-dated rows).

**38. Header discounts on purchases** — 2026-10-17
- Decision: a purchase header discount lowers stock cost, allocated to lines pro rata by value; the last line takes the rounding remainder.
- Reason: consistent with the sales-side allocation; the totals reconcile exactly.
- Change: none.

## Security and access

**39. Maintenance token and first admin** — 2026-10-17
- Decision: create the first owner from the Maintenance page (token needed). Once an owner exists, creating admins there also needs owner login.
- Reason: there is no terminal to run `create_admin`.
- Change: rotate `MAINTENANCE_TOKEN` in `.env` at any time.

**40. 2FA optional, recommended for owner** — 2026-10-17 · **Amended by #72**
- Decision: TOTP is optional per user. The owner is prompted to enable it at go-live.
- Reason: brief says "optional 2FA".
- Change: Settings > Security > "Require 2FA for roles".

## Open items (not yet decided)

**41. GitHub repository name and owner** — 2026-10-17 · **Superseded by #43**
- Decision: pending. M0 cannot ship a release zip until the repo exists.
- Reason: the release zip is built by GitHub Actions.
- Change: the coordinator records the repo when the owner supplies it.

**42. Real Excel exports from Square / master software** — 2026-10-17
- Decision: pending. The templates are based on the photographed samples only.
- Reason: the column-mapping profiles need real headers.
- Change: add the files to `docs/samples/` and create mapping profiles in M8.

## M0 deploy smoke test

**43. Repository is `nurmdbd888/square_mke`, public** — 2026-10-17
- Decision: code lives in the public GitHub repo `nurmdbd888/square_mke`; default branch `main`. Supersedes #41.
- Reason: supplied by the owner. Public means cPanel Git Version Control can clone over HTTPS without keys, and the release zip downloads without a GitHub login.
- Change: if the repo becomes private, Way B in `DEPLOY.md` needs a deploy key (cPanel SSH Access) and the zip must be sent to the owner. Never commit `.env` or any secret: the repo is world-readable.

**44. Laravel 13 with PHP 8.4 as the floor** — 2026-10-17
- Decision: Laravel 13.x (13.37 at M0, current stable) with `config.platform.php = 8.4.0` (per #2), so `composer.lock` resolves Symfony 8 and requires PHP 8.4 at runtime. The coordinator asked for code "compatible with PHP 8.2+": the app's own code uses nothing newer than PHP 8.1 syntax, but Laravel 13 itself needs PHP 8.3+, so the deployed app needs the host's 8.4.
- Reason: the host confirmed PHP 8.4. Laravel 12 (the last major that runs on 8.2) is past its bug-fix window (Aug 2026).
- Change: to run on PHP 8.3, set `config.platform.php` to `8.3.0` and run `composer update` (pulls Symfony 7.4 LTS), and set `sdm.min_php` to `8.3.0`.

**45. One migration in M0; Laravel's default tables removed** — 2026-10-17
- Decision: the only migration is `smoke_heartbeats`. The skeleton's `users`, `cache` and `jobs` migrations, the `User` model and its factory were removed. M1 adds users, sessions, cache and jobs tables with auth.
- Reason: plan M0 asks for exactly one migration run from the Maintenance page.
- Change: M1 migrations.

**46. M0 uses file sessions, file cache and the sync queue** — 2026-10-17 · **Change superseded by #70**
- Decision: config defaults and both `.env` examples use `SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`. #11 (database drivers) takes effect in M1, once its tables exist.
- Reason: the home and Maintenance pages must work before the first migration; database sessions or cache would crash every page on a fresh install.
- Change: switch the three `.env` keys after M1's migrations have run.

**47. Maintenance page is stateless and token-protected** — 2026-10-17 · **Amended by #74** (owner login once an owner exists)
- Decision: `/maintenance` runs in its own middleware group without sessions, cookie encryption or CSRF middleware. Login posts `MAINTENANCE_TOKEN`; on success the browser gets cookie `sdm_maintenance` = expiry + HMAC-SHA256(expiry, token): 2 hours, HttpOnly, SameSite=Strict, Secure on HTTPS. POST buttons carry a form token derived from the same cookie. Changing the token logs everyone out. Login is limited to 5 failures per IP per 10 minutes (file cache); if the cache folder is unwritable the limit is skipped and a warning is logged, so the owner can still reach the page that reports the problem. Token shorter than 20 characters: page locked (403). `MAINTENANCE_ENABLED=false`: 404. Owner login on top of the token (#5, #39) arrives in M1.
- Reason: the page must work before `APP_KEY` and the database exist (spec §2), with no terminal fallback.
- Change: `config/sdm.php` `maintenance.*`.

**48. Scheduled work runs in-process, never as a child process** — 2026-10-17
- Decision: `routes/console.php` uses `Schedule::call(fn () => Artisan::call(...))`, not `Schedule::command()`, which starts a new PHP process through `proc_open`. All later scheduled work (queue worker, backups, reminders) must follow the same pattern. CI runs the whole test suite with `exec, shell_exec, system, passthru, proc_open, popen` disabled. Amends #6.
- Reason: shared hosts often disable these functions; `Schedule::command()` would then silently do nothing.
- Change: none planned.

**49. Heartbeat design** — 2026-10-17
- Decision: `sdm:heartbeat` inserts one `smoke_heartbeats` row per run (`ran_at`, `source`, `php_version`, `php_binary`, `hostname`) and deletes rows older than 7 days (about 10,000 rows at most). A heartbeat is "fresh" if it is under 3 minutes old. The env check warns when the cron's PHP major.minor differs from the website's.
- Reason: the owner must see that cron works and that it uses PHP 8.4, not the server's default PHP.
- Change: `config/sdm.php` `heartbeat.*`.

**50. Cron PHP path is detected, not guessed by the owner** — 2026-10-17
- Decision: the Maintenance page builds the cron command from the first existing executable among: the CLI next to the web binary (`…/lsphp` → `…/php`, `…/sbin/php-fpm` → `…/bin/php`), `/opt/cpanel/ea-phpXY/root/usr/bin/php`, `/opt/alt/phpXY/usr/bin/php`, `/usr/local/bin/ea-phpXY`, `/usr/bin/phpX.Y`; fallback `/usr/local/bin/php`. Output goes to `/dev/null`; `DEPLOY.md` shows how to log it temporarily.
- Reason: on cPanel the plain `php` in cron is often an older default version.
- Change: `app/Support/Maintenance/CronCommand.php`.

**51. Shared folder detection** — 2026-10-17
- Decision: `bootstrap/app.php` uses `../shared` for `.env` and `storage/` only when that folder contains `.env` or `storage/`; otherwise Laravel's defaults inside the project (local development). A real server environment variable `SDM_SHARED_PATH` overrides the location (not needed on cPanel).
- Reason: implements #7 without breaking local checkouts.
- Change: `app/Support/SharedPath.php`.

**52. Missing `APP_KEY` shows a setup page, not an error** — 2026-10-17
- Decision: a global middleware answers every non-maintenance request with a 503 "Setup is not finished yet" page that links to `/maintenance` while `APP_KEY` is missing or invalid. "Generate APP_KEY" writes a new key into the loaded `.env` only if neither the running config nor the file already holds a valid key; it never rotates a key. If the file is missing or read-only it shows the line to paste.
- Reason: without a key Laravel crashes on cookie encryption; the owner cannot run `key:generate`. Rotating a key would make encrypted data unreadable.
- Change: none.

**53. Two deploy methods with one server layout; zip is primary** — 2026-10-17
- Decision: both methods produce `~/mke/app`, `~/mke/app-prev`, `~/mke/shared` (#4, #9).
  - **Way A (primary): release zip + File Manager folder swap**, as in the plan. Works on every cPanel.
  - **Way B (optional, faster updates): cPanel Git Version Control.** The repo is cloned to `~/repositories/square_mke` with branch `release` checked out; "Update from Remote" + "Deploy HEAD Commit" runs `deploy/cpanel/deploy.sh` through `.cpanel.yml`. The script copies the build to `~/mke/app-new`, then swaps `app` → `app-prev`, `app-new` → `app`; creates `shared/` and `shared/.env` from the example on the first deploy and adds any new storage folders; never touches existing `shared/`. It uses only bash and coreutils (no rsync, php, composer or node).
- Reason: Way B is two clicks per update and keeps cPanel's PHP settings (#55), but cPanel documents that accounts without shell access may only clone and view repositories, so Way B cannot be the only path.
- Change: either way can be used at any time; they can be mixed because the layout is the same.

**54. The `release` branch** — 2026-10-17
- Decision: GitHub Actions appends one commit per build to branch `release` (never force-pushed, because cPanel pulls with `--ff-only`). It holds the built app: `vendor/` (no dev packages), `public/css/app.css`, `version.json` and `.cpanel.yml`. `main` has no `.cpanel.yml` (the template is `deploy/cpanel/cpanel.yml`), so cPanel refuses to deploy `main`, which has no `vendor/`. Builds run on tag push `vX.Y.Z` and on "Run workflow" (any ref); running it with an older tag is the Git rollback path. Pull requests that touch the build only produce the zip as a workflow artifact.
- Reason: no Composer or Node on the server (#8); history on the branch doubles as a release log.
- Change: `.github/workflows/release.yml`, `scripts/build-release.sh`, `scripts/publish-release-branch.sh`.

**55. cPanel-written files in the docroot are preserved by Way B** — 2026-10-17
- Decision: `deploy.sh` carries over the `BEGIN/END cPanel-generated` blocks of `public/.htaccess` (MultiPHP handler and INI directives), `public/.user.ini`, `public/php.ini` and `public/.well-known/` (AutoSSL). With Way A these are lost when `app` is replaced, so `DEPLOY.md` tells the owner to re-check the PHP version in MultiPHP Manager after each zip update.
- Reason: MultiPHP Manager stores the PHP version in the docroot's `.htaccess`; losing it silently downgrades PHP.
- Change: none.

**56. Release contents and versioning** — 2026-10-17
- Decision: `git archive` of the tagged commit (so only committed files ship), minus `export-ignore` paths (`tests/`, `docs/`, `scripts/`, `.github/`, `phpunit.xml`, `README.md`, `.env.example`), plus `composer install --no-dev -o`. `DEPLOY.md` and `deploy/` ship. The zip is about 8 MB and 7,500 files. `version.json` holds the tag (or `0.0.0-dev.<sha>`), commit and build time; the home and Maintenance pages show it. Following the plan's `v0.N.0` rule, M0's first tag is `v0.0.0`; fixes bump the patch number.
- Reason: the owner needs to see which version is live.
- Change: `.gitattributes`, `scripts/build-release.sh`.

**57. Tailwind standalone CLI, no Node anywhere** — 2026-10-17 · **Amended by #80**
- Decision: `scripts/build-css.sh` downloads the pinned Tailwind CLI (v4.3.3) into `.tools/` and compiles `resources/css/app.css` to the committed `public/css/app.css`. Vite, `package.json` and `resources/js` from the skeleton were removed. CI fails if the committed CSS is stale. HTMX and Alpine are vendored in M1. Tailwind v4 needs no `tailwind.config.js`; the theme lives in `resources/css/app.css`.
- Reason: #10 and spec §2.
- Change: bump `TAILWIND_VERSION` in the script and rebuild.

**58. Bangla font not yet self-hosted** — 2026-10-17 · **Superseded by #78**
- Decision: M0 uses a system font stack with Bangla fallbacks (Noto Sans Bengali, Hind Siliguri, Nirmala UI, Vrinda). The self-hosted woff2 font arrives in M1 (plan).
- Reason: keep M0 small; Android, Windows and macOS all ship a Bangla font.
- Change: M1.

**59. Locale by cookie in M0** — 2026-10-17 · **Superseded by #78**
- Decision: English default, Bangla optional, chosen with `/locale/{en|bn}` and stored in an unencrypted cookie `sdm_locale` (1 year) so it also works on the stateless Maintenance page. Only UI strings are translated (`lang/bn.json`); the technical detail text on the Maintenance checklist stays English. Per-user locale comes in M1.
- Reason: plan M1 owns the full i18n; M0 only wires it.
- Change: M1.

**60. Firm name and distributor code from config until M1** — 2026-10-17 · **Superseded by #77**
- Decision: `config/sdm.php` reads `SDM_FIRM_NAME` (default "M/s Khaza Enterprise") and `SDM_DISTRIBUTOR_CODE` (default 4831). M1's settings table overrides them.
- Reason: no settings table in M0.
- Change: `.env`.

**61. Database compatibility** — 2026-10-17
- Decision: `DB_CONNECTION=mysql` for both MySQL and MariaDB. `Schema::defaultStringLength(191)` for old utf8mb4 index limits. The env check shows "MySQL" or "MariaDB" plus the version from `SELECT VERSION()`. CI tests MySQL 8.0, MariaDB 10.6 and 10.11, and checks the detected server type.
- Reason: the host's server type and version are not confirmed yet.
- Change: if the env check shows a very old server (MySQL < 5.7, MariaDB < 10.3), tell the developer.

**62. M0 security baseline** — 2026-10-17
- Decision: `public/.htaccess` refuses every dotfile except `.well-known/`; `robots.txt` and a `noindex` meta tag keep the site out of search engines; in production with an `https://` `APP_URL`, URLs are forced to HTTPS. Public pages never show database errors or full server paths (the locked Maintenance page shows only `shared/.env`). `DEPLOY.md` asks for `.env` permission 600.
- Reason: the app holds business data; full hardening is M10.
- Change: M10.

**63. CI limits extensions to the confirmed list** — 2026-10-17
- Decision: CI's PHP is set up with `extensions: none` plus the confirmed list and built-ins (#2, #3), plus `pdo_sqlite`/`sqlite3` for tests and `phar`/`posix` for Composer and PHPUnit.
- Reason: a package needing another extension fails CI before it reaches the server.
- Change: `.github/workflows/ci.yml` `PHP_EXTENSIONS`.

**64. Docroot shim walks up to find the app** — 2026-10-17
- Decision: `deploy/shim/index.php` searches parent folders of the docroot for `mke/app/artisan` and calls `usePublicPath()` with the docroot. The owner copies `app/public/*` plus the shim files into the docroot after every update (neither deploy method does this automatically).
- Reason: no file editing needed for the owner if cPanel forces the docroot under `public_html` (#4).
- Change: only used if `DEPLOY.md` Step 3 fails.

**65. No backup button in M0** — 2026-10-17
- Decision: until M10's backup feature, `DEPLOY.md` asks the owner to export the database in phpMyAdmin before every update.
- Reason: plan puts backups in M10.
- Change: M10.

**66. Safety net for a document root set to `~/mke`** — 2026-10-17
- Decision: the zip ships `~/mke/.htaccess` (from `deploy/root.htaccess`; the Git deploy's `deploy.sh` writes the same file on every deploy and keeps any cPanel PHP handler block in it). It turns off folder listings, refuses `shared/`, `app/` (except `app/public`), `app-*` and dotfiles, lets AutoSSL challenges through, does trailing-slash redirects itself, and rewrites everything else to `app/public/`. `shared.example/`, `shared.example/storage/` and `storage/` carry a deny-all `.htaccess`, and the Maintenance page writes any that are missing (installs from v0.0.0). The document-root check stays amber, with a banner, until cPanel points at `app/public`.
- Reason: the owner's subdomain was set to `~/mke`, which listed `app/` and `shared/` and made `shared/storage` (logs) downloadable. Access is refused with mod_rewrite `[F]`, not `Require`: `Require` in `~/mke` would be inherited by `app/public`, and some LiteSpeed builds only read rewrite rules from `.htaccess`. Tested on Apache 2.4 and OpenLiteSpeed with both document roots.
- Change: none planned; the real fix is the cPanel setting (DEPLOY.md, "Problems and fixes").

**67. cPanel Git on the `release` branch is the primary deploy path; the zip is the fallback** — 2026-10-17
- Decision: supersedes the "zip is primary" part of #53. The owner has cPanel Git™ Version Control working on `release`, so `DEPLOY.md` now describes the Git way first (first install and updates: **Update from Remote** → **Deploy HEAD Commit**) and keeps the zip way as the fallback for hosts that refuse deployment. The folder layout, rollback by renaming `app-prev`, and "republish an older tag with Actions → Release → Run workflow" are unchanged. The release tree carries `deploy/root.htaccess`; `deploy.sh` rewrites `~/mke/.htaccess` from it on every deploy, outside the `app` → `app-prev` swap, so the safety net (#66) is never lost and a cPanel PHP handler block in it is kept. The release workflow runs `scripts/test-deploy.sh` against the built tree (first deploy, two updates, and a v0.0.0-style install without the safety files) before the branch is published.
- Reason: two clicks per update, no file uploads, and cPanel's PHP settings survive updates (#55). The zip stays because cPanel lets accounts without shell access clone but not deploy.
- Change: if the Git deploy ever stops working on this host, `DEPLOY.md`'s zip way is complete on its own.

## M1 setup, auth, roles, audit, i18n

**68. Product name is Square Distributor Manager, never translated** — 2026-10-17
- Decision: the product is renamed from "Square Dealer Manager" to "Square Distributor Manager" in code, config defaults, both `.env` examples, views, docs and tests. The name stays in English in the Bangla interface too (`lang/bn.json` maps it to itself; a test enforces it). The session cookie (`sdm_session`) and cache prefix (`sdm-cache-`) are fixed names instead of being derived from `APP_NAME`, and the authenticator-app issuer is a constant.
- Reason: owner request. Fixed cookie and cache names mean editing `APP_NAME` never signs everyone out or empties the cache; the live `.env`'s `APP_NAME` now only sets the sender name of password-reset e-mails.
- Change: `APP_NAME` in `.env` (optional); the in-app name is the `__('Square Distributor Manager')` string.

**69. Sign-in by user name or e-mail** — 2026-10-17
- Decision: every user has a unique lower-case user name (letters, digits, `.`, `_`, `-`); e-mail is optional and unique. Passwords are 10–128 characters (bcrypt). No "remember me": sessions end after `SESSION_LIFETIME` (120 minutes) without activity. Wrong user name and wrong password give the same message, and an unknown user name still costs one bcrypt check so timing does not reveal valid names. The session id changes at sign-in and the session is destroyed at sign-out. Users created by someone else (and passwords reset by the owner) are temporary: the user must choose a new password before any other page opens. A password change signs the user out of other browsers (`auth.session` middleware).
- Reason: staff such as SRs may have no e-mail; the plan asks for secure sessions.
- Change: `Password::defaults()` in `AppServiceProvider`.

**70. Sessions and cache stay on files; the queue stays sync** — 2026-10-17 · supersedes the session and cache part of #11 and the "Change" line of #46
- Decision: `SESSION_DRIVER=file`, `CACHE_STORE=file` and `QUEUE_CONNECTION=sync` remain the defaults after M1. M1's migrations still create the `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches` and `failed_jobs` tables, so switching later is one `.env` line. The queue moves to `database` with the first milestone that queues work.
- Reason: file sessions keep the sign-in page, its "database is not ready" notice and the Maintenance page's owner check working while the database is down or not migrated. This is one server, so file locks keep rate limiting consistent, which was #11's reason for a database cache. The owner does not need to edit `.env` for this release.
- Change: set the three `.env` keys to `database` (after **Run migrations**).

**71. Sign-in rate limits** — 2026-10-17
- Decision: 5 failed attempts per minute per user name + IP; the 6th attempt within the minute is refused even with the right password, and a successful sign-in clears that counter. A second limit of 20 failures per minute per IP across all user names stops one machine from guessing many accounts. The two-factor step has the same limits. Lockouts are audited (`auth.lockout`). "Forgot password" and "reset password" posts are limited to 5 per minute. The Maintenance phrase keeps its own limit (#47).
- Reason: plan M1 acceptance ("the 6th failed login within a minute is blocked").
- Change: `App\Support\Auth\LoginThrottle`.

**72. Two-factor sign-in (TOTP)** — 2026-10-17 · amends #40
- Decision: optional per user, set up from **My profile**: `pragmarx/google2fa` (RFC 6238, 30-second codes, ±1 step of clock drift) and an inline SVG QR code from `bacon/bacon-qr-code` (no image extension). The new secret stays in the session until the user types a working code. The secret and 8 one-time recovery codes are stored encrypted with `APP_KEY`. A code that was already accepted, or an older one, is refused (replay protection via `two_factor_last_step`). After the password step only the user id waits in the session (5 minutes); nobody is signed in until the code is right. Turning 2FA off, or making new recovery codes, needs the current password. The setting `require_2fa_roles` sends users of those roles to their profile until 2FA is on. The owner can turn 2FA off for another user; an owner without another owner uses recovery (#74). The "prompt the owner" in #40 is a step in `DEPLOY.md`, not a forced screen.
- Reason: brief says optional 2FA; works with Google or Microsoft Authenticator.
- Change: Settings → Security.

**73. Roles and permissions** — 2026-10-17 · amended by #92
- Decision: `spatie/laravel-permission` with five roles: `owner`, `accountant`, `sr`, `data_entry`, `viewer`. Each user has exactly one role. The owner passes every check (`Gate::before`), so the permission matrix cannot lock the owner out. Permissions are listed in `App\Support\Access\PermissionRegistry` and created by a migration (later milestones add theirs the same way). A permission's default roles are granted only when the permission is first created, so the owner's later edits in **Roles and permissions** survive updates. M1 defaults: accountant has `health.view`, `users.view`, `settings.view`, `audit.view`; viewer has `settings.view`; SR and data entry have none yet (their screens arrive with purchases and sales). `users.manage`, `roles.manage` and `settings.manage` are owner-only by default; a non-owner given `users.manage` still cannot create, edit, reset or deactivate an owner, or make anyone an owner. The last owner cannot be demoted or deactivated, and nobody can deactivate themselves.
- Reason: plan M1; one role per user keeps the matrix readable for the owner.
- Change: **Roles and permissions** page.

**74. Owner lock on the Maintenance page** — 2026-10-17 · implements #5 and #39, amends #47
- Decision: while no owner exists, the secret phrase alone opens `/maintenance`, and the page shows **Create the owner account**. Once an owner exists, every Maintenance request also needs the visitor to be signed in to the app as an owner; otherwise a 403 page links to the sign-in page, which returns there. If the users table cannot be read (database down, not migrated), the phrase alone works again, so the owner can still see what is wrong. The Maintenance routes start a session only when `APP_KEY` is valid and the session folder is usable; they still have no CSRF middleware and keep their own form token (#47). A signed-in owner can add more owners there. `MAINTENANCE_OWNER_RECOVERY=true` in `.env` lifts the owner lock and shows a form to reset an owner's password (it also turns off that owner's 2FA); `DEPLOY.md` says to set it back to `false` straight away. Generate key, migrate, clear caches, create owner and recovery are all audited with source `maintenance`.
- Reason: there is no terminal; the phrase alone must not stay enough once the app holds data, and someone with cPanel access must be able to recover.
- Change: `config/sdm.php` `maintenance.owner_recovery`.

**75. Users are deactivated, not deleted; password reset** — 2026-10-17
- Decision: **Deactivate** soft-deletes the user. They cannot sign in, their audit history and later their documents keep pointing at them, the user name stays reserved, and **Reactivate** restores them. The users list has active, inactive and all filters, search, a role filter and 50 rows per page. "Forgot your password?" sends a reset link only when mail is set up (`MAIL_MAILER` is not `log`, `array` or empty) and the user has an e-mail address; the answer on screen is the same either way. Otherwise the owner sets a temporary password on the Users page.
- Reason: the host's mail setup is unknown; history must stay intact.
- Change: SMTP lines in `.env` (`deploy/shared.example/.env.example`).

**76. Audit log** — 2026-10-17
- Decision: one append-only `audit_logs` table: when, user id and user name (kept if the user is renamed or deactivated), source (`web`, `maintenance`, `console`), event, record type and id, old and new values as JSON, IP, user agent, and "METHOD path" (no query string). Models using the `Auditable` trait log `created`, `updated` (changed fields only), `deleted` and `restored`; passwords, remember tokens and 2FA secrets are stored as `[hidden]` or skipped. Sign-in, sign-out, failed sign-in, lockout, password changes and resets, 2FA changes, role changes, permission-matrix changes and Maintenance actions are explicit events. One settings save writes one row listing only the changed keys. A failed audit write is logged as a warning and never stops the user's action. The viewer (owner and accountant by default) filters by user, event (or event group), record type and id, source and date range, 50 rows per page, with a before/after table per entry. No pruning yet.
- Reason: plan M1 "full audit log (who, when, IP, old/new JSON)".
- Change: retention and export come with M10 (backups and hardening).

**77. Settings store with effective-dated rows** — 2026-10-17 · supersedes #60
- Decision: each setting is declared in code (`App\Settings\SettingsRegistry`: key, type, default, group, label, help, validation, and whether it is effective-dated). The `settings` table holds only what the owner changed, as JSON. A plain setting has one row (`effective_from` empty); an effective-dated setting has one row per start date, and the value on a date is the row with the latest start on or before it, else the default. Reads never throw: before migrations or with the database down every setting returns its default. M1 registers firm name, distributor code (defaults still from `SDM_FIRM_NAME` and `SDM_DISTRIBUTOR_CODE`), default language, fiscal-year start month (7) and `require_2fa_roles`. Business-rule settings (VAT, commission, …) are registered by the milestones that read them. Only future-dated rows can be removed; a past rate is changed by adding a new row from today.
- Reason: plan M1; rates must apply by document date, not by the day they were typed.
- Change: **Settings** page.

**78. Language per user; Bangla font self-hosted** — 2026-10-17 · supersedes #58 and #59
- Decision: the language is the signed-in user's choice (saved by the switch in the top bar or on **My profile**), else the `sdm_locale` cookie, else the **Default language** setting, else `APP_LOCALE`. UI strings live in `lang/bn.json`, with role, permission, setting-group and audit-event names in `lang/{en,bn}/*.php` and Bangla validation messages in `lang/bn/validation.php`. A test fails if any `__()` string lacks a Bangla translation or loses a placeholder. Technical detail text on the Maintenance checklist stays English (#59). Noto Sans Bengali 400, 600 and 700 are served from `public/fonts` as woff2 (Bengali code points only, about 45 KB each, SIL Open Font License in `OFL.txt`), so Bangla looks the same on every phone; Latin text keeps the system font.
- Reason: plan M1 "English/Bangla switch per user, self-hosted Bangla font".
- Change: add strings to `lang/bn.json`.

**79. Number formatting and fiscal year** — 2026-10-17
- Decision: `App\Domain\Money\Formatter` works on decimal strings with `brick/math` (never floats): lakh/crore grouping (`1234567.89` → `12,34,567.89`), round half up, Bangla digits in Bangla (`১২,৩৪,৫৬৭.৮৯`), `৳` amounts, and short forms ("1.23 crore", "১২.৩৫ লাখ"). Blade helpers: `format_number()`, `format_money()`, `local_digits()`. `App\Domain\Calendar\FiscalYear` gives the July–June year containing a date, labelled `2026-27` (`২০২৬-২৭` in Bangla); the start month is the setting `fiscal_year_start_month`. Dates on screen are `dd-mm-yyyy`.
- Reason: plan M1; Bangladesh fiscal year.
- Change: none.

**80. HTMX and Alpine vendored** — 2026-10-17 · amends #57
- Decision: `public/js/htmx.min.js` (2.0.7) and `public/js/alpine.min.js` (3.15.0) are committed, loaded with `defer` and a file-time version query. `public/js/app.js` adds the CSRF token to every HTMX request and reloads the page if a request comes back 401 or 419 (signed out or expired). List filters use HTMX and get back only the table; every list is paginated (50 rows). Shared button, input, card and table styles are Tailwind `@utility` classes in `resources/css/app.css`.
- Reason: #10, no Node or CDN on the server.
- Change: replace the files and update the version numbers here.

**81. CSRF and cookie settings** — 2026-10-17
- Decision: every app form has `@csrf`, and Laravel's `PreventRequestForgery` middleware also accepts a request whose browser marks it `Sec-Fetch-Site: same-origin`. Tests switch the check back on (Laravel skips it in tests) and prove a forged cross-site post gets 419. The session cookie is HttpOnly and SameSite=Lax, and Secure whenever `SESSION_SECURE_COOKIE=true` or, if that line is missing, when `APP_URL` starts with `https://`. The locale cookie is unencrypted and HttpOnly; the Maintenance cookie is unchanged (#47).
- Reason: plan M1 acceptance.
- Change: `config/session.php`.

**82. `sdm:create-admin` shares the Maintenance code** — 2026-10-17
- Decision: `php artisan sdm:create-admin` (for development, or a host with a terminal) asks for the password without echoing it and calls the same `App\Actions\CreateOwner` as the Maintenance page, so the validation and audit row (source `console`) are identical. The Maintenance page does not run it through `Artisan::call`.
- Reason: one code path; the password never passes through command-line arguments.
- Change: none.

**83. M1 and the folder safety net ship as v0.1.0** — 2026-10-17
- Decision: the safety net (#66, #67) was merged after v0.0.0 but not tagged; it ships with M1 in v0.1.0, following the plan's `v0.N.0` rule. `DEPLOY.md` says "versions from v0.1.0" for the safety file and has one combined update section for v0.0.0 → v0.1.0.
- Reason: one release for the owner to deploy.
- Change: if a different tag is used, update those two places in `DEPLOY.md`.

**84. Sample product codes and the 24-product seeder** — 2026-10-17
- Decision: the sample file has no printed product codes, so the candidate ids (`P01`–`P17`, `S01`–`S07`) are stored in `products.code`. The seeder reads `database/seeders/data/products-seed.csv` (the same rows as `docs/samples/products-seed.csv`) and runs from a migration, so Maintenance → Run migrations loads the 24 products with no terminal. Running it again updates those codes in place. Each Bangla name is also saved as a product alias.
- Reason: plan M2; the owner has no terminal.
- Change: ship a new migration if the sample list changes. Do not overwrite a code the owner has since edited.

**85. Buy price and exact buy price** — 2026-10-17
- Decision: `buy_price` is the printed ex-VAT price (Chaka Ball Soap `23.83`). `buy_price_exact` keeps the unrounded figure (`23.8260`) for later costing. Both are `decimal(14,4)`. The product sell price is `decimal(14,4)` and is only a fallback when a price list has no row. Blank sample buy prices (the S-series, seen only on a sales invoice) stay null. A blank SD is stored as 0 and a blank VAT as 15, the column defaults. Money is never a float (`brick/math`).
- Reason: the challan prints 23.83; the later golden test needs 23.8260.
- Change: none.

**86. Phone numbers** — 2026-10-17
- Decision: retailer, supplier and sales-rep phones are stored as digits. A leading `880` is dropped when the rest is long enough, and a 10-digit number starting with 1 gains a leading 0. So `1711459077`, `8801711459077` and `+880 1711-459077` all become `01711459077`. Empty input is stored as null. Anything else is kept as its digits.
- Reason: plan M2 phone normaliser.
- Change: `App\Domain\Phone\Normalizer`.

**87. Piece, dozen and carton** — 2026-10-17
- Decision: `App\Domain\Units\Converter` turns a quantity into pieces. A dozen is 12. A carton uses the product's `units_per_carton`; a carton with no factor is refused. The result is a decimal string, scale 2, half up. The product unit defaults to `Pcs`.
- Reason: plan M2.
- Change: none.

**88. Price lists are effective-dated sell prices** — 2026-10-17
- Decision: a price list has rows of product, price and `effective_from`. The price on a date is the row with the latest `effective_from` on or before that date; the newest id wins a tie. If the list has no such row, the product's own sell price is used. Saving the same product and date again replaces that row. Lists are soft-deleted; the prices stay attached and come back when the list is restored.
- Reason: plan M2, "price lookup returns the price valid on a given date".
- Change: none.

**89. Sales reps, routes, suppliers and vehicles are master records** — 2026-10-17
- Decision: a sales rep is a row in `sales_reps` (name, phone, optional link to a user), not the SR role by itself. Routes live in the `routes` table as `App\Models\SalesRoute`, because a class named `Route` would clash with the router. Suppliers have a BIN. Vehicles are unique by registration. All four are soft-deleted and can be restored.
- Reason: someone in the field may have no login, and a login may not be a sales rep.
- Change: none.

**90. Opening balances are stored, not posted** — 2026-10-17
- Decision: opening stock, receivable, payable, cash and bank are rows in `opening_balances` (date, product or party, quantity, amount, note). M2 does not post them into stock or a ledger. The party is chosen by an exact code or name, so the form does not load every retailer into the page.
- Reason: plan M2 lists opening balances; stock and the books arrive in later milestones.
- Change: the posting milestone reads this table.

**91. Custom fields** — 2026-10-17
- Decision: `custom_fields` defines a key, label, type (text or number) and sort order for a product or a retailer. Values live in the row's `custom` JSON. A save keeps only keys that are still defined. Removing a definition does not delete answers already stored.
- Reason: plan M2; the owner can add a question without a migration.
- Change: Masters → Custom fields.

**92. Master-data permissions, lists, column chooser and bulk edit** — 2026-10-17 · amends #73
- Decision: new permissions, granted to the default roles only the first time the permission is created (#73). `products.view` and `retailers.view` go to the accountant, SR, data entry and viewer. `products.manage` and `retailers.manage` go to data entry. `masters.view` goes to the accountant and data entry. `masters.manage` goes to data entry. The owner still passes every check. Product and retailer lists show 50 rows, with search, an active / deactivated / all filter, and a column chooser remembered in the session. Bulk edit changes one field on at most 100 selected rows and writes one audit row per record. Deactivate is a soft delete; Restore brings the row back. The dashboard "What is ready" line now includes master data.
- Reason: plan M2, including the list of 10,000 retailers.
- Change: Roles and permissions.

**93. Purchase and sales documents** — 2026-10-17
- Decision: one `documents` table with `type` purchase or sale, statuses draft / posted / void. Lines hold qty, bonus qty, printed line value, discounts, SD and VAT amounts. Posted documents are voided, never hard-deleted. Soft-delete removes a draft. Duplicate key is number + party + net total among non-void rows.
- Reason: plan M3; printed amounts are stored (#24).
- Change: Purchases and Sales screens.

**94. Weighted-average stock ledger** — 2026-10-17
- Decision: `stock_ledger_entries` are rebuilt per product from the earliest affected date. Sort is `LedgerOrder` (#31). Issue cost uses #32. Purchase stock value uses printed line value ± discounts ± SD (`sd_in_cost`) ± VAT only when `vat_recoverable` is off (#25, #2). Purchase bonus qty enters at zero cost by default (#34). Oversell is allowed and costed at the last average (#33).
- Reason: core rules 1–4 and the golden test.
- Change: `App\Domain\Costing\Recompute`.

**95. Sale revenue, free goods and commission** — 2026-10-17
- Decision: sale revenue is qty × unit price − line and header discounts ± rounding (VAT-inclusive TP, no output VAT split). Bonus qty and combo-bonus lines are free-goods cost at issue cost, revenue 0. Commission uses effective-dated `commission_pct` / `commission_vat_pct` / `commission_base` / `count_commission` (#37). Profit before expenses = revenue − COGS − free goods + net commission.
- Reason: core rules 3, 5, 6 and 7.
- Change: `App\Domain\Profit`.

**96. Inline vs queued recompute** — 2026-10-17
- Decision: recompute runs in the request when the estimated posted-line count is ≤ `config('sdm.recompute_inline_limit')` (default 2000). Larger work dispatches `RecomputeProductsJob` on the database queue. The minute schedule now also runs `queue:work database --stop-when-empty` in-process. A cache flag shows “costing update pending” on stock pages until the queue is empty.
- Reason: #36 and the 30 s shared-hosting limit.
- Change: Settings and Stock screens.

**97. Document permissions and nav** — 2026-10-17 · amends #92
- Decision: `purchases.view` → accountant, data entry, viewer; `purchases.manage` → accountant, data entry; `sales.view` → accountant, SR, data entry, viewer; `sales.manage` → accountant, SR, data entry; `stock.view` → accountant, data entry, viewer. Nav adds Purchases, Sales and Stock. The dashboard ready line now includes purchases, sales and stock costing.
- Reason: plan M3.
- Change: Roles and permissions.

**98. Receipts, payments and allocations** — 2026-10-17
- Decision: one `payments` table with `kind` receipt or payment, methods cash / bKash / Nagad / Rocket / bank / cheque / pdc / petty, statuses draft / posted / void. Allocations live in `payment_allocations` (payment + document + amount). Unallocated amount is an advance (`is_advance`). Posted payments are voided, never hard-deleted; a draft is soft-deleted. Amounts use `brick/math` (#24).
- Reason: plan M4.
- Change: Payments screens.

**99. Post-dated cheques enter the books only when cleared** — 2026-10-17
- Decision: a PDC is posted with `clearing_status = pending` and a due date. It does not move cash/bank books, party balances or open-invoice remaining until marked cleared (`cleared_at`). The book date is the cleared date. Ordinary cheques count on the payment date.
- Reason: plan M4 acceptance.
- Change: `Payment::countsInBooks()` / `bookDate()`; Clear action on the payment page.

**100. Receivables, payables and ageing** — 2026-10-17
- Decision: outstanding = opening balance + posted invoices − allocated − advances (PDC counted only when cleared, #99). Ageing buckets are 0–30 / 31–60 / 61–90 / 90+ by invoice date. Retailer statements show buckets and open invoices. A credit-limit warning (flash) appears when a posted sale leaves the retailer over `retailers.credit_limit`; it does not block the sale.
- Reason: plan M4.
- Change: Receivables, Payables, Statement; `App\Domain\Payments\CreditLimit`.

**101. Cash book, bank book, day book and daily closing** — 2026-10-17
- Decision: cash methods are cash, bKash, Nagad, Rocket, petty; bank methods are bank, cheque, pdc (after clear). Day book = cash + bank movements for the range. Daily closing stores opening/in/out/closing for cash and bank; the next day's opening is the last closing. Opening balances (#90) seed the books when no closing exists yet.
- Reason: plan M4 acceptance.
- Change: Cash / Bank / Day book and Daily closing screens.

**102. Petty cash and expense categories** — 2026-10-17
- Decision: a payment with no supplier may carry an `expense_categories` row (seeded: van rent, fuel, toll, loading, godown rent, SR salary, mobile, trade licence, other). Petty is a cash-book method. Categories are soft-deleted.
- Reason: plan M4 brief list.
- Change: migration seeds categories; payment form.

**103. Payment and accounts permissions** — 2026-10-17 · amends #97
- Decision: `payments.view` → accountant, SR, data entry, viewer; `payments.manage` → accountant, SR, data entry; `accounts.view` → accountant, data entry, viewer; `accounts.manage` → accountant (daily closing). Nav adds Payments, Receivables, Payables, Cash book, Bank book, Day book, Daily closing. The dashboard ready line now includes payments and the books.
- Reason: plan M4.
- Change: Roles and permissions.

**104. Stock notes, returns and batch ledger** — 2026-10-17
- Decision: `stock_notes` / `stock_note_lines` cover sales returns (linked lines restore at original issue cost; damaged/expired post `loss`), stock count, adjustment and loss. Posted notes feed the same stock ledger as documents; linked return value uses the sale line’s issue cost per unit. Purchase lines may carry `batch_no` / expiry; when `costing_mode = batch_fefo` and the product tracks expiry, sales issue FEFO by batch.
- Reason: plan M5 acceptance.
- Change: Returns screens, stock ledger rebuild, optional batch on purchase lines.

**105. Claims and stock comparison** — 2026-10-17
- Decision: claims use statuses open → submitted → approved → settled (or rejected at most steps). CSV comparison upload matches product code (then name), flags qty differences and unknown products; `near_expiry_days` drives the near-expiry alert on the stock page.
- Reason: plan M5.
- Change: Claims, Compare with export, stock alerts.

**106. Universal entity import/export** — 2026-10-17
- Decision: every master list exposes **Export** (CSV of template columns) and **Import** (download template → upload `.csv`/`.xls`/`.xlsx` → preview with row errors → choose **update** or **skip** duplicates → confirm → undo batch). Entities: products, retailers, suppliers, routes, sales reps, vehicles, price lists, price list items. Permissions reuse existing `*.manage` (no separate import permission). Soft-deleted rows can be restored on update. Mapping profiles stored per entity. **PDF is not used** for generic import v1 (stock PDF stays on stock comparison only). When filenames match owner exports (`ProductList*`, `SalesRegister*`), products/retailers imports auto-map Crystal column headers. Samples in `docs/samples/master-exports/`.
- Reason: owner scope change — templates beat auto-detect; M6 paused.
- Change: `/imports/{entity}`, list page actions, PhpSpreadsheet reader.

**107. P&L and net profit** — 2026-10-17
- Decision: `Summary` reports revenue, COGS, free goods, gross, commission and `profit_before_expenses` from posted sales. Net profit subtracts posted operating expenses (payments with an expense category) and posted stock-note `stock_loss_total` in the date range.
- Reason: plan M6 acceptance (golden + 50 expense = 130.21 net profit).
- Change: P&L report, dashboard net profit.

**108. Month-end lock and reports permissions** — 2026-10-17
- Decision: `month_locks` blocks posting and edits in a locked calendar month for every role. Locking requires `reports.manage`; only the owner may unlock (audited as `month_lock.unlocked`). Document, payment and stock-note writers call `MonthLockService::assertWritable()` on the document/payment date. `reports.view` → accountant, data entry, viewer; `reports.manage` → accountant. Dashboard metrics and P&L require `reports.view`.
- Reason: plan M6 and spec §6.
- Change: Reports nav, month locks screen, validation on writers.

**109. Export engine and Downloads** — 2026-10-17
- Decision: table/report exports use `ExportRegistry` sources with current filters and chosen columns. Formats: CSV and XLSX via OpenSpout streaming, PDF via dompdf (English print layout). Exports above `export_inline_limit` (500 rows estimate) dispatch `RunTableExportJob`; finished files and status live in `app_jobs` and **Downloads**. Initial wired keys: `products`, `retailers`, `reports_pl`.
- Reason: plan M7 acceptance (filtered rows, queued large exports).
- Change: Export menu on lists/reports, `/downloads`.

**110. Google OAuth and scheduled Sheets** — 2026-10-17
- Decision: one firm `google_connections` row; OAuth scopes `drive.file`, `spreadsheets`, `documents`; refresh/access tokens use Laravel `encrypted` cast. Settings shows Connect/Disconnect and optional Drive folder id. Reports can push to a new or existing Sheet; `google_sheet_subscriptions` refresh daily via `RefreshGoogleSheetJob` from the minute schedule. Client credentials from `GOOGLE_*` env (redirect `/google/callback`).
- Reason: plan M7 and spec §7.
- Change: Settings → Google integration, `google/apiclient` (trimmed services).

**111. Excel invoice import (M8)** — 2026-10-17
- Decision: `/imports/invoices` accepts purchase or sale uploads (.csv/.xlsx/.xls) in **Header+Lines** or **flat** layout (templates downloadable). Column mapping reuses `import_mapping_profiles` entities `purchase_invoice` / `sale_invoice`. Parser normalises Bangla digits, bonus/combo rows and sample date formats; purchase lines warn when `unit×price` differs from value within `max(0.01, qty×0.005)`. Preview stores editable rows; confirm posts via `DocumentWriter`; undo voids posted documents in the batch. Product match: code → name/pack → alias; corrections learn aliases. Large files queue `ProcessDocumentImportJob` above `document_import_inline_limit` (500).
- Reason: plan M8 and `docs/samples` golden challan + sales invoice.
- Change: Purchases/Sales **Import from Excel**, `document_import_*` tables.

**112. Field collector receipts and slip photos** — 2026-10-17
- Decision: new role **`collector`** with `payments.view`, `payments.collect`, and `retailers.view`. `payments.collect` (also on SR) allows **receipt-only** create/edit/post via gate `payments.receipt-write`; supplier payments, void, and PDC clear stay on `payments.manage`. Receipt form accepts up to **5 slip photos** (`accept="image/*"`, `capture="environment"` on mobile) stored on disk `local` (`storage/app/private/payments/{id}/`); served only through authenticated `payments.attachments.show` (never public docroot). Allocation UI shows party **balance due**, per-invoice **Full** pay, and **balance after** for partial amounts; engine rules unchanged (partial allocation, advance, ageing).
- Reason: owner request for mobile collectors photographing slips and recording partial collections without full back-office access.
- Change: `payment_attachments` table, collector role migration sync, receipt form UX.

**113. Printing, reminders and PWA (M9)** — 2026-10-17
- Decision: browser-print Blade layouts for **A4 tax invoice**, **A4 challan** (purchase and sale), and **80 mm thermal** sale receipt, with firm name, distributor code, Bangla/English product names and inline **SVG QR** (BaconQrCode). Due reminders use a `ReminderChannel` contract with **log**, **HTTP SMS** and **WhatsApp Cloud** drivers; `sdm:send-due-reminders` runs daily from `Schedule::call` and respects `reminder_enabled`, `reminder_due_days` and open-invoice ageing. Offline SR sales: `manifest.webmanifest`, `sw.js` shell cache, IndexedDB drafts on the sale form, and `POST /sales/sync-draft` with **`Idempotency-Key`** stored in `sales_sync_keys`.
- Reason: plan M9 acceptance (print, fake reminder driver, idempotent offline sync).
- Change: print routes on document show, reminder settings group, PWA assets.

**114. Invoice number vs receipt number** — 2026-10-17
- Decision: sales and purchase document forms label the document field **Invoice number** (still stored in `documents.number`). Customer **receipt** forms label the payment field **Receipt number** with help text that it is not the sales invoice; supplier **payment** forms use **Payment number** (voucher/bank reference, not purchase invoice). Retailer receipts add a **Pay against invoice** dropdown listing posted sales with open balance (same rows as the allocation table); choosing one highlights the row and prefills **This payment** with the full remaining amount, while partial pay and multi-invoice splits stay in the table.
- Reason: owner UX — collectors were confusing receipt voucher numbers with invoice numbers when collecting against due sales.
- Change: `payments/_open_invoices`, receipt/payment form labels.
