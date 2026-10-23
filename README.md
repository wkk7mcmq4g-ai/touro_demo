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

### 2. Make a local copy of the database

Use a copy so you're never testing against the live data.

1. In phpMyAdmin on the server, select `kwi14599_myat` and open **Export**. Choose the **Custom**
   method, tick *Add CREATE PROCEDURE / FUNCTION / EVENT*, keep **structure and data**, then export.
2. Open HeidiSQL, connect to your local MariaDB (root) and run
   `CREATE DATABASE kwi14599_myat CHARACTER SET utf8mb4;`
3. Select that database, then go to *File → Run SQL file…* and pick the export.

The copy contains real customer data and password hashes, so keep it on your machine only.

### 3. Get the app running

```powershell
cd C:\Projects
git clone https://github.com/wkk7mcmq4g-ai/touro_myat.git
cd touro_myat
composer install
copy .env.example .env
php artisan key:generate
```

Open `.env` and set `DB_PASSWORD=` to your local MariaDB root password. The other database
settings already match the steps above. Then start the app:

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
