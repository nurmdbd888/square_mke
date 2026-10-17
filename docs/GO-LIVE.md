# Go-live checklist (M/s Khaza Enterprise)

Use this after v0.5.x is deployed and opening balances are entered. Keep running the legacy master software in parallel for **one week** before cut-over.

## Before parallel week

- [ ] Production deploy from `release` branch (Git Way B in `DEPLOY.md`).
- [ ] Maintenance → **Run migrations** → **Clear caches**; confirm version on sign-in page.
- [ ] Owner account exists; **two-factor sign-in** enabled for owner (and accountant if required).
- [ ] **Settings**: firm name, distributor code, fiscal year July–June, weekly holiday Tuesday, Google connected for exports/backups (optional but recommended).
- [ ] **Cron** every minute: `php artisan schedule:run` (copied from Maintenance page).
- [ ] **Backup now** on Maintenance; confirm file under `shared/storage/backups/` and on Google Drive when connected.
- [ ] Staff users created with correct roles; collectors tested on receipt + slip photos.
- [ ] Opening balances: stock, cash/bank, party balances per training.

## Parallel week (legacy + SDM)

- [ ] Enter new sales, purchases and receipts in **both** systems (or agreed split by module).
- [ ] Daily: compare **stock value** and **top product quantities** between legacy and SDM dashboard.
- [ ] Weekly: P&L month-to-date on **business days** (exclude Tuesday holidays) vs legacy.
- [ ] Log unexplained differences in a shared sheet; fix mapping/settings before cut-over.

## Cut-over

- [ ] Final **Backup now** before last legacy-only day.
- [ ] Announce read-only on legacy; SDM becomes system of record.
- [ ] After 48 hours: spot-check audit log, day book, and receivables ageing.

## Owner sign-off

- [ ] No unexplained stock or P&L gaps > agreed tolerance.
- [ ] Restore drill completed once (scratch DB or staging) using `DEPLOY.md` restore section.
- [ ] Owner name, date, and signature (notebook or e-mail to implementer).
