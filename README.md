# MyAthens Transfers – back office

The new back office for MyAthens Transfers, built with **Laravel 13** and **Filament 5**. It replaces
the PHPRunner app one module at a time and uses the **same MariaDB/MySQL database**, so both apps
can run side by side during the switch-over.

**Done so far:**
- Login with the existing PHPRunner usernames and passwords; PHPRunner user groups and permissions.
- Dashboard (today / tomorrow / pending / sales this month) and the Users screen.
- **Transfers**: list with Today / Tomorrow / Next 5 days / Pending / All, PHPRunner's colours and TBD
  warnings, filters and search; the edit form with all lookups and PHPRunner's date rules; tabs for
  Notes & issues, Price lines, Charges and Files (files stay in the database, as in PHPRunner).

## Running it on your PC (Windows)

### 1. Install the tools (once)

1. **Laravel Herd** (free): <https://herd.laravel.com/windows>. It installs PHP and Composer.
   Afterwards, `php -v` in a new terminal should show PHP 8.3 or newer.
2. **A local database server.** If you have **XAMPP**, use its MySQL (it is MariaDB): start it in the
   XAMPP Control Panel; root has no password, which matches `.env.example`. Otherwise install
   MariaDB 11.4 from <https://mariadb.org/download/> and put its root password in `.env`.
   Don't let XAMPP's PHP 8.2 be the `php` on your PATH; Herd's must come first (`php -v` ≥ 8.3).
3. **Git**: <https://git-scm.com/download/win>, if you don't have it yet.

### 2. Get the app running

```powershell
cd C:\Projects
git clone https://github.com/wkk7mcmq4g-ai/touro_myat.git
cd touro_myat
composer install
copy .env.example .env
php artisan key:generate
```

Open `.env` and set `DB_PASSWORD=` to your local MariaDB root password. The other database
settings already match the steps below.

### 3. Make a local copy of the database

Use a copy so you're never testing against the live data. Do it in three steps:

1. **Structure, without triggers.** The structure comes from this repo. phpMyAdmin's own export of
   the views is broken, and triggers must not fire while you import data.
   ```powershell
   php artisan app:load-schema --without-triggers
   ```
2. **Data.** In phpMyAdmin on the server, select `kwi14599_myat`, open **Export**, choose
   **Custom** and set *Format-specific options* to **data** (not structure). In the table list,
   untick the five unused PHPRunner tables with `6ug` in their name (Windows can't hold both
   spellings of them). Export it. Then import
   it into the local `kwi14599_myat`: with XAMPP, open <http://localhost/phpmyadmin>, select
   `kwi14599_myat` → **Import**; with MariaDB's HeidiSQL, *File → Run SQL file…*.
3. **Triggers and the daily event.**
   ```powershell
   php artisan app:load-schema --triggers-only
   ```

The copy contains real customer data and password hashes, so keep it on your machine only.
The `Daily_Notifications` job is created **disabled** in copies, so your PC never sends the
real daily notifications.

### 4. Start it

```powershell
php artisan serve
```

Go to <http://127.0.0.1:8000/admin> and sign in with your normal PHPRunner username and password.
Only the **Admin** and **Entry** groups can use the back office.

### Updating to the latest version

```powershell
git pull
composer install
php artisan optimize:clear
```

## Tests

```powershell
php artisan test
```

The tests build a separate, empty `myat_testing` database from `database/legacy/schema.sql`, so they
never touch your data. The same tests run on GitHub on every push.

## How it relates to the old app

| PHPRunner | Here |
|---|---|
| Users table `MyAthensTransfersAPP_users`, user groups | Same table; groups in `App\Enums\UserGroup` |
| Group permissions (Add/Edit/Delete/List/Print/Import) | `config/permissions.php`, copied from the project |
| Login / logout events (`access_log`) | `App\Listeners\LogAccess` |
| Table events and custom buttons | Ported module by module into resources and actions |
| Triggers, procedures, daily event in MySQL | Unchanged, still in the database |

To add a model for a new table or view: `php artisan app:generate-models --table=<name>`.
