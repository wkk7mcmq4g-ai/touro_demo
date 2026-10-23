# MyAthens Transfers back office

Laravel 13 + Filament 5 rebuild of the PHPRunner app `Touro_MyAT_v2026`. It runs on the
**existing MariaDB database** (`kwi14599_myat`) side by side with the old app, screen by screen.

## Ground rules

- **The database is shared with the live PHPRunner app.** No Laravel migrations that alter legacy
  tables, no Laravel tables in it (sessions/cache are `file`, queue is `sync`). Schema changes are a
  deliberate decision, made in phpMyAdmin first, then `database/legacy/schema.sql` is refreshed.
- **Business logic in the database stays there** (24 triggers, ~30 procedures/functions, the
  `Daily_Notifications` event). Call procedures rather than re-implementing them in PHP.
- **Logins are the PHPRunner users** (`MyAthensTransfersAPP_users`, username + bcrypt). Never
  rehash or rewrite a password on login (`App\Auth\PhpRunnerUserProvider`).
- **Permissions are PHPRunner's masks** (`config/permissions.php`, letters A/E/D/S/P/I). Resources
  use `App\Filament\Concerns\UsesPhpRunnerPermissions` with `$permissionTable`. Only `Admin` and
  `Entry` use this panel; drivers/partners/suppliers get their own portal later.
- **Legacy names stay as they are**: table and column names (`TransferID`, `cID_Customer`, ...) are
  used verbatim in models; no renaming layer.
- **No secrets in git.** Elorus/Telegram/SMTP credentials go in `.env`.

## Layout

- `app/Models/*` generated from the DB by `php artisan app:generate-models` (views use `ReadOnlyModel`).
- `app/Filament/Resources/<Name>/` one folder per resource (`Schemas/`, `Tables/`, `Pages/`).
- `database/legacy/schema.sql` structure-only export; `php artisan app:load-schema` loads it into an
  empty database.
- Reference material for porting (pages, fields, lookups, events, button code) was extracted from the
  `.phpr`; ask George for the spec bundle if it is not at hand.

## Tests

`php artisan test` builds `myat_testing` from the schema automatically (needs a DB user that may
create it). The users table is MyISAM, so tests clean up their own rows (`Tests\TestCase`).
