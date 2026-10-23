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

Each push builds the package, uploads it as **one zip** over FTPS and asks the server to unpack
it (`public/deploy.php`). A deploy takes a minute or two. Set it up once:

1. cPanel → *FTP Accounts* → *Add FTP Account*: login e.g. `deploy`, a long random password,
   **Directory** = `touro_myat` (the app folder only, so this login can reach nothing else).
2. Make a deploy token, a long random string (e.g. `openssl rand -hex 32`, or any password
   generator, 40+ letters and digits). Put it in the server's `touro_myat/.env`:
   ```
   DEPLOY_TOKEN=the-long-random-string
   ```
3. GitHub → the repo → *Settings* → *Secrets and variables* → *Actions* → *New repository
   secret*, four times:
   - `FTP_SERVER` = the FTP host whose certificate matches, e.g. `ns335.ipdns.gr`
   - `FTP_USERNAME` = the full login, e.g. `deploy@touro.gr`
   - `FTP_PASSWORD` = its password
   - `DEPLOY_TOKEN` = the same string as in the server's `.env`
4. The first time only, `public/deploy.php` is not on the server yet: extract the newest
   package by hand once (*Updating by hand* below). After that every push deploys by itself.

Your `.env` and `storage/` (logs, sessions) on the server are never touched. Files deleted from
the app are not removed from the server (harmless). To stop deploying, delete the `DEPLOY_TOKEN`
secret; without a token in `.env`, `deploy.php` answers 404.

## Updating by hand

Download the newest package and extract it over the old folder (*Extract* → overwrite).
Your `.env` is not in the package, so it stays. If you ever ran `php artisan config:cache` or
`route:cache` on the server, delete `bootstrap/cache/config.php` and `routes-*.php`.

## If something goes wrong

- *500 error*: look at `touro_myat/storage/logs/laravel.log`.
- *403 / blank page*: the subdomain's document root is not `touro_myat/public`.
- *"could not find driver"*: `pdo_mysql` is off for that PHP version (step 1).
