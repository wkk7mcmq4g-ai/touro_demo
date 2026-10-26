# Test copy on cPanel

The **Demo showcase** (a copy of MyAT shown to prospects) on your hosting, using an
**anonymised copy of MyAT's database** (`kwi14599_demo`), never a live one.

Before the first start: in phpMyAdmin, select the demo copy and run
`database/legacy/demo/anonymise.sql` (it refuses to run on the live databases). It replaces
people's names, phones and e-mails, deletes attachments, locks the copied logins and adds the
demo logins `demo` (Admin), `office` (Entry), `driver` and `partner` (portal), password `Demo2026`.

## Once

1. **PHP version.** cPanel → *Domains* → create a subdomain, e.g. `new.demo.touro.gr`.
   Set its **Document Root** to `touro_demo/public` (the `public` folder inside the app,
   not the app folder itself). Then cPanel → *MultiPHP Manager* → tick the subdomain →
   PHP **8.3** or newer. In *MultiPHP INI Editor* (or *Select PHP Version → Extensions*)
   make sure `intl`, `pdo_mysql`, `mbstring`, `fileinfo` and `zip` are on.
2. **Get the package.** GitHub → the repo → *Actions* → latest **Package** run →
   *Artifacts* → download `touro_demo-…`. It is a zip containing `touro_demo.zip`.
3. **Upload.** cPanel → *File Manager* → your home folder (the one that contains
   `public_html`) → *Upload* `touro_demo.zip` → right-click → *Extract*. You get a
   `touro_demo` folder next to `public_html`, so the app's code is not web-reachable.
4. **Settings file.** In `touro_demo`, copy `.env.example` to `.env` and edit it:
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://new.demo.touro.gr
   APP_KEY=            ← paste the key from: php artisan key:generate --show (on your PC)
   DB_HOST=localhost
   DB_DATABASE=kwi14599_demo
   DB_USERNAME=...     ← a MySQL user with access to kwi14599_demo only
   DB_PASSWORD=...
   ELORUS_ENABLED=false
   TELEGRAM_ENABLED=false
   MAIL_MAILER=log
   PHPRUNNER_URL=
   PHPRUNNER_ROOT=
   ```
   The Demo keeps all five as above, always: its data is invented and there is no PHPRunner
   app behind it (MyAT's address would open its live app and real files).
   Keep `ELORUS_ENABLED=false` and `MAIL_MAILER=log` on the test copy: nothing is sent to
   Elorus, customers, drivers or the port; emails are written to `storage/logs` instead.
   To see real emails on the test copy, set `MAIL_MAILER=smtp` with the mailbox password **and**
   `MAIL_ALWAYS_TO=your@address`: every email then goes only to you (Cc/Bcc dropped), with a
   yellow strip on top showing who it would have gone to.
5. **Open** `https://new.demo.touro.gr/admin` and sign in as `demo` / `Demo2026`.

## Updating automatically (every push to main)

Each push builds the package, uploads it as **one zip** over FTPS and asks the server to unpack
it (`public/deploy.php`). A deploy takes a minute or two. Set it up once:

1. cPanel → *FTP Accounts* → *Add FTP Account*: login e.g. `deploy`, a long random password,
   **Directory** = `touro_demo` (the app folder only, so this login can reach nothing else).
2. Make a deploy token, a long random string (e.g. `openssl rand -hex 32`, or any password
   generator, 40+ letters and digits). Put it in the server's `touro_demo/.env`:
   ```
   DEPLOY_TOKEN=the-long-random-string
   ```
3. GitHub → the repo → *Settings* → *Secrets and variables* → *Actions* → *New repository
   secret*, four times:
   - `FTP_SERVER` = the FTP host whose certificate matches, e.g. `ns335.ipdns.gr`
   - `FTP_USERNAME` = the full login, e.g. `deploy@touro.gr`
   - `FTP_PASSWORD` = its password
   - `DEPLOY_TOKEN` = the same string as in the server's `.env`
   Then the *Variables* tab → *New repository variable*:
   - `DEPLOY_URL` = the site's deploy address, e.g. `https://new.demo.touro.gr/deploy.php`
     (without it the package is still built, but not deployed)
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

- *500 error*: look at `touro_demo/storage/logs/laravel.log`.
- *403 / blank page*: the subdomain's document root is not `touro_demo/public`.
- *"could not find driver"*: `pdo_mysql` is off for that PHP version (step 1).
