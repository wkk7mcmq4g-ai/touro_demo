# Demo Transfers (showcase of the MyAT back office)

**This repo is the Demo showcase**, copied from `touro_myat` (remote `upstream`) at `f4fa728`, for
showing the system to prospects. Differences from MyAT (`claude/demo-diff-list.md` in the New Touro
project): made-up identity in `config/business.php` and images (`public/myat/mark.svg`, icons,
`resources/legacy/images/demo-*.png`); `config/partners.php` empty; deploy folder `touro_demo`.
Its database is an anonymised copy of MyAT's (`database/legacy/demo/anonymise.sql`, run once in
phpMyAdmin). Keep mail on `log`, Elorus/Telegram off and `PHPRUNNER_URL` empty on its server.
Everything below is MyAT's and applies as it is.

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
- **Generated columns**: many legacy columns are `GENERATED ALWAYS` (e.g. on `transfers`: issue flags,
  Assets/Income, voucher `*_Calc`, `TransferTime_sort`). Never write them; list them in the model's
  `GENERATED` constant so `saving` strips them (see `App\Models\Transfer`).
- **Blank = `''`, not NULL**: triggers test `= ''` (PHPRunner saved empty strings). Models list such text
  columns in `BLANK_AS_EMPTY_STRING`.
- **Files are blobs** in the database (e.g. `myat_transfers_files.Attachment`); read/write them there so
  PHPRunner keeps working. Never select blob columns in lists.
- **No outside calls**: no font CDNs, no ui-avatars (local initials avatar), nothing that sends staff or
  customer data to third parties.
- **No secrets in git.** Elorus/Telegram/SMTP credentials go in `.env`.
- **Business identity is config, not code.** Names, ΜΗ.Τ.Ε., contact details, colours, logo files and
  voucher images come from `config/business.php` / `.env` (`BUSINESS_*`); voucher pages write them as
  `{business.<key>}` and images as `business:<key>`. Never hard-code MyAT's details again
  (`tests/Feature/BusinessConfigTest.php` checks). This repo is the baseline (`baseline-v1`) the other
  businesses' apps are copied from.

## Layout

- `app/Models/*` generated from the DB by `php artisan app:generate-models` (views use `ReadOnlyModel`).
- `app/Filament/Resources/<Name>/` one folder per resource (`Schemas/`, `Tables/`, `Pages/`).
- Theme: `public/myat/theme.css` (plain CSS over Filament's `fi-*` classes, no build step), Manrope in
  `public/myat/fonts`, logo view `resources/views/filament/brand/logo.blade.php` (inlines `business.logo.mark`); set up in
  `App\Filament\Theme`, which also sets the theme's colour variables from `config/business.php`.
- `database/legacy/schema.sql` structure-only export; `php artisan app:load-schema` loads it into an
  empty database.
- Reference material for porting (pages, fields, lookups, events, button code) was extracted from the
  `.phpr`; ask George for the spec bundle if it is not at hand.

## Tests

`php artisan test` builds `myat_testing` from the schema automatically (needs a DB user that may
create it). The users table is MyISAM, so tests clean up their own rows (`Tests\TestCase`).
