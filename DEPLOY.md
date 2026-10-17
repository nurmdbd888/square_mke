# Putting Square Dealer Manager on mke.rockproviders.com

This guide is for the owner of M/s Khaza Enterprise. You do not need a terminal,
Composer or any programming. Everything happens in **cPanel** and your **web browser**.

You need: your cPanel login, about 30–45 minutes, and a notebook (paper is fine)
to write down every name and password you create.

**Words used in this guide**

| Word | Meaning |
|---|---|
| USER | your cPanel user name. It is shown in cPanel on the right ("Current User"), and File Manager shows your home folder as `/home/USER`. |
| home folder | the top folder you see when File Manager opens (`/home/USER`). It is **not** `public_html`. |
| release zip | a file named like `mke-v0.0.0.zip` that holds the finished app. |

**What the server will look like when you are done**

```
/home/USER/
└── mke/
    ├── app/          the program (replaced on each update)
    │   └── public/   the only folder visitors can reach (the subdomain points here)
    ├── app-prev/     the previous version, kept so you can go back
    └── shared/       your settings (.env), logs and uploads; never replaced
```

---

## Part 1 — First install

### Step 1. Make the database

1. cPanel → **Databases** → **MySQL® Databases** (or **Manage My Databases**).
2. Under **Create New Database**, type `mke` and click **Create Database**.
   cPanel adds your prefix, so the real name looks like `USER_mke`. Write it down.
3. Click **Go Back**. Under **MySQL Users** → **Add New User**: user name `mke`,
   click **Password Generator**, tick "I have copied this password", click **Use Password**,
   then **Create User**. Write down the full user name (`USER_mke`) and the password.
4. Click **Go Back**. Under **Add User To Database**, pick the user and the database you just made, click **Add**.
5. On the next screen tick **ALL PRIVILEGES** and click **Make Changes**.

### Step 2. Choose PHP 8.4

Your cPanel has one of these two screens (some hosts have both; use the one you have):

- **Select PHP Version** (CloudLinux):
  1. Set **Current PHP version** to **8.4** and click **Set as current**.
  2. Open the **Extensions** tab and make sure these are ticked: `bcmath`, `ctype`, `curl`, `dom`,
     `fileinfo`, `gd`, `iconv`, `intl`, `mbstring`, `openssl`, `pdo`, `pdo_mysql`, `simplexml`,
     `tokenizer`, `xml`, `xmlreader`, `xmlwriter`, `zip`. Changes save by themselves.
- **MultiPHP Manager**: tick `mke.rockproviders.com` (after Step 3), choose **PHP 8.4** on the right, click **Apply**.

The Maintenance page (Step 7) tells you if anything is still missing.

### Step 3. Create the subdomain and point it at the app

1. cPanel → **Domains** → **Domains** → **Create A New Domain**
   (older cPanel: **Domains** → **Subdomains**).
2. Domain: `mke.rockproviders.com`.
3. **Untick** "Share document root (/home/USER/public_html) with …".
4. Document Root: type `mke/app/public` (cPanel shows it as `/home/USER/mke/app/public`).
5. Click **Submit** (or **Create**).

If the subdomain already exists: **Domains** → find `mke.rockproviders.com` → **Manage** →
change **New Document Root** to `mke/app/public` → **Update**.

If cPanel refuses a folder outside `public_html`, stop and tell us. We will send the
"shim" instructions (Appendix B).

> cPanel may create a few small items in `mke/app/public` (for example `cgi-bin`). That is fine.

### Step 4. Put the app on the server

There are two ways. **Way A (zip)** works on every cPanel. **Way B (Git)** saves time on
every later update, but only works if your host allows it. If unsure, use Way A.

#### Way A — upload the zip (always works)

1. Download the newest `mke-vX.Y.Z.zip` from
   <https://github.com/nurmdbd888/square_mke/releases> (under **Assets**), or use the zip we send you.
2. cPanel → **Files** → **File Manager**. You are in your home folder.
3. If there is already a folder `mke` (Step 3 usually creates it), open it. Otherwise click **+ Folder**,
   name it `mke`, then open it. An `app` folder made by cPanel in Step 3 can stay; the zip fills it in.
4. Click **Upload**, choose the zip, wait for the green 100% bar, then click
   **Go Back to "/home/USER/mke"**.
5. Right-click the zip → **Extract** → **Extract Files** → **Close**. You now see `app` and `shared.example`
   (plus a hidden safety file `.htaccess`; leave it there).
6. Right-click `shared.example` → **Rename** → `shared`.
7. You can delete the zip now (right-click → **Delete**).

#### Way B — cPanel Git Version Control (faster updates, if your host allows it)

1. cPanel → **Files** → **Git™ Version Control** → **Create**.
2. Keep **Clone a Repository** switched on.
   - Clone URL: `https://github.com/nurmdbd888/square_mke.git`
   - Repository Path: `repositories/square_mke`
   - Repository Name: `square_mke`
3. Click **Create** and wait until the clone finishes.
4. Click **Manage** next to `square_mke`. In **Checked-Out Branch** choose **release** and click **Update**.
5. Open the **Pull or Deploy** tab and click **Deploy HEAD Commit**.
   This creates `/home/USER/mke/app` and `/home/USER/mke/shared` (with a `.env` file to fill in).

If there is no **Deploy HEAD Commit** button, or it is greyed out and says deployment is not
available, your host does not allow it. Use Way A instead; nothing is broken.

### Step 5. Fill in the settings file (.env)

1. File Manager → top right **Settings** → tick **Show Hidden Files (dotfiles)** → **Save**.
2. Open `mke/shared`.
   - Way A: right-click `.env.example` → **Rename** → `.env`.
     (A spare copy of the example stays in `mke/app/deploy/shared.example/`.)
   - Way B: `.env` is already there.
3. Right-click `.env` → **Edit** → **Edit**. Fill in only these lines and leave everything else alone:

   ```
   DB_DATABASE=USER_mke          (full database name from Step 1)
   DB_USERNAME=USER_mke          (full user name from Step 1)
   DB_PASSWORD=the password from Step 1
   MAINTENANCE_TOKEN=a long secret phrase only you know, at least 20 characters
   ```

   Example of a good secret phrase: `blue-mango-river-4831-shop-lamp`. No spaces, no `#`.
   If your password contains `#` or spaces, put it in double quotes: `DB_PASSWORD="ab#c d"`.
4. Click **Save Changes**, then **Close**.
5. Right-click `.env` → **Change Permissions** → set **600** (only Owner Read and Write ticked) → **Change Permissions**.

Keep a copy of this file in a safe place (not on the server). Never send it by e-mail or chat.

### Step 6. Turn on the padlock (HTTPS)

1. cPanel → **Security** → **SSL/TLS Status**. If `mke.rockproviders.com` does not show a green padlock,
   tick it and click **Run AutoSSL**. It can take a few minutes.
2. cPanel → **Domains** → **Domains**: switch **Force HTTPS Redirect** **on** for `mke.rockproviders.com`.

### Step 7. Open the Maintenance page

1. In your browser, go to <https://mke.rockproviders.com/maintenance>.
2. Type your secret phrase (`MAINTENANCE_TOKEN`) and click **Open**. You see a checklist.
3. If **APP_KEY** is red, click **Generate APP_KEY**, then **Check again**. It turns green.
   If the page says it cannot write the file, it shows a line starting with `APP_KEY=base64:`.
   Copy that whole line into `.env` (replace the empty `APP_KEY=` line) with File Manager and save.
4. Look at the list. PHP 8.4, every extension, folders writable, settings file loaded and
   database connected should all be **OK** (green). If anything says **Fix** (red),
   take a screenshot and send it to us.
5. Click **Run migrations**. The result box says **Finished successfully** and lists
   `create_smoke_heartbeats_table … DONE`.

### Step 8. Add the cron job (the app's alarm clock)

1. On the Maintenance page, find the **Cron command** box and click **Copy**.
   It looks like `/opt/alt/php84/usr/bin/php /home/USER/mke/app/artisan schedule:run >> /dev/null 2>&1`.
2. cPanel → **Advanced** → **Cron Jobs**.
3. Under **Add New Cron Job** → **Common Settings**, choose **Once Per Minute (\* \* \* \* \*)**.
4. Paste into **Command** and click **Add New Cron Job**.
5. Wait two minutes, then click **Check again** on the Maintenance page.
   **Last cron heartbeat** should be green and say "less than a minute ago" or "1 minute ago".
   **Cron PHP version** should also be green.

### Step 9. Check that everything works

1. Open <https://mke.rockproviders.com>. You see **M/s Khaza Enterprise**, a version number,
   **Database: Connected** and **Cron** with a green OK. Click **বাংলা** at the top: the page switches to Bangla.
2. Safety checks. Each of these must show **Forbidden** or **Not Found**, never your settings:
   - <https://mke.rockproviders.com/.env>
   - <https://mke.rockproviders.com/storage/>
   - <https://mke.rockproviders.com/../shared/.env>
3. Send us a screenshot of the Maintenance page and of the home page.
   **We stop here until you confirm.**

If a step fails, do not retry randomly. Send a screenshot of the error and the step number.

---

## Part 2 — Updates (each new version)

Before every update, save a copy of the database:
cPanel → **Databases** → **phpMyAdmin** → click `USER_mke` on the left → **Export** tab →
**Export** (Quick, SQL). Keep the downloaded `.sql` file. (From M10 the Maintenance page has a **Backup now** button.)

### Update with Way A (zip)

1. Download the new `mke-vX.Y.Z.zip` from <https://github.com/nurmdbd888/square_mke/releases>.
2. File Manager → open `mke`.
3. If `app-prev` exists, right-click it → **Delete** (tick "Skip the trash" if offered).
4. Right-click `app` → **Rename** → `app-prev`.
5. **Upload** the zip into `mke`, then right-click it → **Extract**. A new `app` folder appears.
   Delete the new `shared.example` folder and the zip. **Never touch `shared`.**
6. If you use **MultiPHP Manager**: open it and check `mke.rockproviders.com` still shows **PHP 8.4**;
   if not, choose 8.4 and **Apply** (replacing the folder can reset it).
7. Maintenance page → **Run migrations** → **Clear caches**.
8. Open the home page and check the version number changed.

### Update with Way B (Git)

1. cPanel → **Git™ Version Control** → **Manage** next to `square_mke` → **Pull or Deploy** tab.
2. Click **Update from Remote**, wait, then click **Deploy HEAD Commit**.
   (The previous version is moved to `mke/app-prev` automatically, and your PHP version setting is kept.)
3. Maintenance page → **Run migrations** → **Clear caches**.
4. Open the home page and check the version number changed.

---

## Part 3 — Going back to the previous version (rollback)

### Quick rollback (works for both ways)

1. File Manager → open `mke`.
2. Right-click `app` → **Rename** → `app-bad`.
3. Right-click `app-prev` → **Rename** → `app`.
4. Open the home page: the old version number is back.
5. Only if the bad version changed the database and the old one now shows errors:
   phpMyAdmin → `USER_mke` → **Import** → choose the `.sql` file you saved before the update → **Go**.
   (New versions only add tables and columns, so this is rarely needed.)
6. Tell us what went wrong. You can delete `app-bad` once we have fixed it.

### Going back further (any older version)

- Way A: download the older zip from the Releases page and follow "Update with Way A".
- Way B: ask us to rebuild the older version (GitHub → **Actions** → **Release** → **Run workflow** →
  enter the old version such as `v0.0.0`). Then do "Update with Way B".

---

## Part 4 — Problems and fixes

| What you see | What to do |
|---|---|
| "Setup is not finished yet" on the home page | Do Step 7 (Generate APP_KEY). |
| Maintenance page says "The Maintenance page is locked" | `MAINTENANCE_TOKEN` in `mke/shared/.env` is empty or shorter than 20 characters. Fix it and reload. |
| "Too many attempts" | Wait 10 minutes, then type the phrase carefully. |
| "Shared folder … not found" (amber) | The folder is not called exactly `shared`, or it is not next to `app` inside `mke`. Rename it. |
| Database: "Access denied" | Wrong `DB_USERNAME` or `DB_PASSWORD`, or Step 1.4–1.5 (add user to database, ALL PRIVILEGES) was skipped. |
| Database: "Unknown database" | `DB_DATABASE` must be the full name with the prefix (`USER_mke`). |
| Database: "No such file or directory" | Change `DB_HOST=localhost` to `DB_HOST=127.0.0.1` in `.env`. |
| PHP version red, or the site shows "Composer dependencies require PHP >= 8.4" | Step 2 again (choose 8.4 for this subdomain). |
| An extension is red | Step 2, Extensions tab; or ask your host to enable it. |
| A folder is "not writable" | File Manager → right-click the folder → **Change Permissions** → **755**. |
| Last cron heartbeat stays amber | Check the cron command was pasted completely (Step 8). To see the error, change `>> /dev/null 2>&1` at the end to `>> /home/USER/mke/shared/storage/logs/cron.log 2>&1`, wait two minutes, open that file, send it to us, then change it back. |
| Cron PHP version amber | The cron uses a different PHP. Copy the command from the Maintenance page again (it shows the right path). |
| Blank page or "500 error" | File Manager → `mke/shared/storage/logs/` → open the newest `laravel-….log` and send us the last part. |
| "Index of /" page listing `app` and `shared`, **404 Not Found** on `/maintenance`, the site only works at `/app/public/`, or the Maintenance page says "The subdomain points at the wrong folder" | The subdomain's document root is wrong. See [the next section](#index-of--listing-or-404-on-maintenance-wrong-document-root). |

### "Index of /" listing, or 404 on /maintenance (wrong document root)

The subdomain must point at `/home/USER/mke/app/public`. If it points at `/home/USER/mke`,
visitors can see your folders. Versions from v0.0.1 put a safety file (`mke/.htaccess`) in place
that hides the folders and keeps the site working, but the Maintenance page stays amber until
you fix the setting. Fix it like this:

1. cPanel → **Domains** → **Domains**. Find `mke.rockproviders.com` and click **Manage**.
2. In **New Document Root**, the box after `/home/USER/` must say exactly `mke/app/public`
   (no `/` at the start or end). Click **Update**.

   Older cPanel without a **Manage** button: cPanel → **Domains** → **Subdomains**. In the list
   at the bottom, find `mke.rockproviders.com`, click the pencil icon next to its **Document Root**,
   type `mke/app/public` and click **Change**.
3. cPanel → **MultiPHP Manager**: tick `mke.rockproviders.com`, choose **PHP 8.4**, click **Apply**.
   (The PHP version is stored in the document root folder, so it must be set again after moving it.
   If you use **Select PHP Version** instead, nothing to do.)
4. Wait one minute, then open <https://mke.rockproviders.com/> in a private/incognito window.
   You should see the home page. <https://mke.rockproviders.com/shared/> must **not** show a list of files.
5. Open the Maintenance page: "Web document root is the public folder" is green.

If cPanel will not save `mke/app/public`, tell us (Appendix B).

---

## Appendix A — Where things are

| Thing | Place |
|---|---|
| Settings (database password, secret phrase) | `/home/USER/mke/shared/.env` |
| Error logs | `/home/USER/mke/shared/storage/logs/` |
| Uploads and backups (later milestones) | `/home/USER/mke/shared/storage/app/private/`, `/home/USER/mke/shared/storage/backups/` |
| Program files | `/home/USER/mke/app/` (never edit; updates replace it) |
| Cron instructions | `/home/USER/mke/app/deploy/cron.txt` |
| Safety file for a wrong document root | `/home/USER/mke/.htaccess` (hidden; updates replace it) |

**Never** delete `shared`, rename `.env`, or change `APP_KEY` once the app holds data:
encrypted data would become unreadable.

## Appendix B — If cPanel will not accept `mke/app/public` (shim)

Only use this if Step 3 fails. The subdomain then lives in a folder inside `public_html`,
for example `public_html/mke` (whatever cPanel created).

1. Do Steps 1, 2 and 4 (Way A) as normal, so `/home/USER/mke/app` and `/home/USER/mke/shared` exist.
2. File Manager → open `mke/app/public` → **Select All** → **Copy** → to the subdomain's folder
   (for example `/home/USER/public_html/mke`).
3. Open `mke/app/deploy/shim` → copy `index.php` and `.htaccess` into the subdomain's folder,
   replacing the files there.
4. Continue with Step 5. After every update, repeat steps 2 and 3.

The shim `index.php` finds `/home/USER/mke/app` by itself; you do not need to edit it.
