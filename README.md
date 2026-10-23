# MyAthens Transfers – back office

The new back office for MyAthens Transfers, built with **Laravel 13** and **Filament 5**. It replaces
the PHPRunner app one module at a time and uses the **same MariaDB/MySQL database**, so both apps
can run side by side during the switch-over.

**Done so far:** login with the existing PHPRunner usernames and passwords, the PHPRunner user
groups and permissions, a dashboard (today / tomorrow / pending / sales this month), and the Users
screen.

## Running it on your PC (Windows)

### 1. Install the tools (once)

1. **Laravel Herd** (free): <https://herd.laravel.com/windows>. It installs PHP and Composer.
   Afterwards, `php -v` in a new terminal should show PHP 8.3 or newer.
2. **MariaDB** for a local copy of the database: <https://mariadb.org/download/> (11.4, MSI
   installer). Remember the root password you choose. The installer also adds **HeidiSQL**, which
   you can use to import the database.
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
   **Custom** and set *Format-specific options* to **data** (not structure). Export it. Then in
   HeidiSQL, connect to your local MariaDB, select `kwi14599_myat` and use
   *File → Run SQL file…* on the export.
3. **Triggers and the daily event.**
   ```powershell
   php artisan app:load-schema --triggers-only
   ```

The copy contains real customer data and password hashes, so keep it on your machine only.

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
