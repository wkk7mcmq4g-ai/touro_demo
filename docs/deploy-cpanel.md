# Test copy on cPanel

A copy of the new back office on your hosting, next to PHPRunner, using the
**development database** (`kwi14599_myatdev`), never the live one.

## Once

1. **PHP version.** cPanel → *Domains* → create a subdomain, e.g. `new.myat.touro.gr`.
   Set its **Document Root** to `touro_myat/public` (the `public` folder inside the app,
   not the app folder itself). Then cPanel → *MultiPHP Manager* → tick the subdomain →
   PHP **8.3** or newer. In *MultiPHP INI Editor* (or *Select PHP Version → Extensions*)
   make sure `intl`, `pdo_mysql`, `mbstring`, `fileinfo` and `zip` are on.
2. **Get the package.** GitHub → the repo → *Actions* → latest **Package** run →
   *Artifacts* → download `touro_myat-…`. It is a zip containing `touro_myat.zip`.
3. **Upload.** cPanel → *File Manager* → your home folder (the one that contains
   `public_html`) → *Upload* `touro_myat.zip` → right-click → *Extract*. You get a
   `touro_myat` folder next to `public_html`, so the app's code is not web-reachable.
4. **Settings file.** In `touro_myat`, copy `.env.example` to `.env` and edit it:
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://new.myat.touro.gr
   APP_KEY=            ← paste the key from: php artisan key:generate --show (on your PC)
   DB_HOST=localhost
   DB_DATABASE=kwi14599_myatdev
   DB_USERNAME=...     ← a MySQL user with access to kwi14599_myatdev only
   DB_PASSWORD=...
   ELORUS_ENABLED=false
   MAIL_MAILER=log
   ```
   Keep `ELORUS_ENABLED=false` and `MAIL_MAILER=log` on the test copy: nothing is sent to
   Elorus, customers, drivers or the port; emails are written to `storage/logs` instead.
5. **Open** `https://new.myat.touro.gr/admin` and sign in with your PHPRunner login.

## Updating automatically (every push to main)

GitHub can upload each new version straight to the server over FTPS, so there is nothing
to download or extract. Set it up once:

1. cPanel → *FTP Accounts* → *Add FTP Account*: login e.g. `deploy`, a long random password,
   **Directory** = `touro_myat` (the app folder only, so this login can reach nothing else).
2. GitHub → the repo → *Settings* → *Secrets and variables* → *Actions* → *New repository
   secret*, three times:
   - `FTP_SERVER` = your FTP host (cPanel → *FTP Accounts* → *Configure FTP Client*, e.g. `ftp.touro.gr`)
   - `FTP_USERNAME` = the full login shown there, e.g. `deploy@touro.gr`
   - `FTP_PASSWORD` = its password
3. GitHub → *Actions* → **Package** → *Run workflow*. The first run uploads everything
   (vendor included, so it takes a while); later runs upload only changed files.

From then on every push to `main` builds and deploys. Your `.env` and `storage/` (logs,
sessions) on the server are never touched. To stop deploying, delete the `FTP_SERVER` secret.

## Updating by hand

Download the newest package and extract it over the old folder (*Extract* → overwrite).
Your `.env` is not in the package, so it stays. If you ever ran `php artisan config:cache` or
`route:cache` on the server, delete `bootstrap/cache/config.php` and `routes-*.php`.

## If something goes wrong

- *500 error*: look at `touro_myat/storage/logs/laravel.log`.
- *403 / blank page*: the subdomain's document root is not `touro_myat/public`.
- *"could not find driver"*: `pdo_mysql` is off for that PHP version (step 1).
