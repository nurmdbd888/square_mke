# Square Dealer Manager

Purchases, sales, stock, dues and profit for **M/s Khaza Enterprise**, a distributor of
Square Toiletries Ltd. (distributor code 4831). Laravel 13 on PHP 8.4, MySQL/MariaDB,
built for cPanel shared hosting at `mke.rockproviders.com` with no terminal on the server.

- Requirements: [docs/SPEC.md](docs/SPEC.md)
- Milestones and acceptance: [docs/PLAN.md](docs/PLAN.md)
- Every assumption and decision: [docs/DECISIONS.md](docs/DECISIONS.md)
- Server install for the owner: [DEPLOY.md](DEPLOY.md)

Current milestone: **M0, deploy smoke test** (home page, Maintenance page, one migration, cron heartbeat).

## Local setup

You need PHP 8.4 with the extensions in `config/sdm.php` (`required_extensions`), Composer 2, Git and curl.
No Node.js is needed. Local development uses SQLite.

```bash
git clone https://github.com/nurmdbd888/square_mke.git && cd square_mke
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan serve            # http://localhost:8000
```

- Maintenance page: <http://localhost:8000/maintenance>, secret phrase = `MAINTENANCE_TOKEN` in `.env`.
- Cron locally: `php artisan schedule:work` (runs the every-minute heartbeat), or once: `php artisan schedule:run`.
- Same checklist in the terminal: `php artisan sdm:check`.

To use MySQL/MariaDB locally, set `DB_CONNECTION=mysql` and the `DB_*` lines in `.env`.

### CSS

Tailwind CSS v4 is compiled with the standalone CLI (downloaded once into `.tools/`):

```bash
scripts/build-css.sh           # writes public/css/app.css (commit it)
scripts/build-css.sh --watch   # while editing Blade views
```

`public/css/app.css` is committed because the server cannot build it. CI fails if it is stale.

### Tests and style

```bash
php artisan test               # SQLite in memory
vendor/bin/pint                # code style
```

Run against MySQL/MariaDB like CI does:

```bash
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=testing DB_USERNAME=root DB_PASSWORD=root \
SDM_EXPECT_DB_SERVER=MariaDB vendor/bin/phpunit
```

CI (`.github/workflows/ci.yml`) runs Pint, the CSS check and PHPUnit on SQLite, MySQL 8.0,
MariaDB 10.6 and 10.11, with `exec`/`proc_open` disabled as on shared hosting.

## Releases

The server never runs Composer or Node. GitHub Actions (`.github/workflows/release.yml`) builds:

- the `release` branch (the owner's main deploy path): `app/` contents (with `vendor/`, compiled CSS,
  `version.json`) plus `.cpanel.yml`, for cPanel Git Version Control. `deploy/cpanel/deploy.sh` swaps
  `app-new` → `app` → `app-prev`, keeps `~/mke/shared`, and writes the `~/mke/.htaccess` safety net
  from `deploy/root.htaccess`.
- `mke-vX.Y.Z.zip` attached to the GitHub Release (fallback): the same `app/`, `shared.example/`
  (`.env.example`, storage skeleton, deny-all `.htaccess`) and the root `.htaccess` safety net.

To release: merge to `main`, then push a tag (`git tag v0.0.0 && git push origin v0.0.0`), or run
**Actions → Release → Run workflow** with a ref. Running it with an older tag is the rollback path for
the Git method. Pull requests that touch the build produce the zip as a workflow artifact only.

Build locally: `scripts/build-release.sh v0.0.0-local` (output in `build/`).

## Layout

```
app/Console/Commands/      sdm:heartbeat, sdm:check
app/Http/Controllers/      Home, Maintenance, Locale
app/Http/Middleware/       SetLocale (cookie), EnsureAppKey (setup page before APP_KEY exists)
app/Support/               SharedPath (~/mke/shared), AppVersion
app/Support/Maintenance/   MaintenanceGate (stateless token login), EnvironmentCheck, DatabaseProbe,
                           MigrationStatus, AppKey, CronCommand
bootstrap/app.php          routing, middleware groups, ../shared for .env and storage
config/sdm.php             firm name, distributor code, maintenance and heartbeat settings
database/migrations/       smoke_heartbeats
deploy/shared.example/     production .env.example and storage skeleton
deploy/cpanel/             deploy.sh and cpanel.yml for the release branch
deploy/shim/               index.php/.htaccess if the docroot must live in public_html
lang/bn.json               Bangla strings
resources/views/           Blade views (layouts, home, maintenance)
routes/                    web.php, maintenance.php (stateless), console.php (schedule)
scripts/                   build-css.sh, build-release.sh, publish-release-branch.sh
docs/                      SPEC, PLAN, DECISIONS, samples/
```

Server layout: `~/mke/app` (code, docroot `app/public`), `~/mke/app-prev` (rollback), `~/mke/shared`
(`.env`, `storage/`). `bootstrap/app.php` switches to `../shared` when that folder holds `.env` or `storage/`.
