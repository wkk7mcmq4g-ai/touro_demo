<?php

namespace App\Console\Commands;

use App\Support\LegacySchema;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Creates an empty copy of the MyAT database from database/legacy/schema.sql.
 *
 *   php artisan app:load-schema                                   # into the .env database
 *   php artisan app:load-schema --database=myat_testing --fresh   # drop and rebuild a test DB
 *
 * Without --fresh it refuses to touch a database that already has tables.
 * --fresh only works on databases whose name ends in `_testing`.
 *
 * Local copy with real data (triggers must not fire while importing):
 *   php artisan app:load-schema --without-triggers   # 1. tables, views, procedures
 *   (import a data-only export from phpMyAdmin)       # 2. data
 *   php artisan app:load-schema --triggers-only       # 3. triggers and the daily event
 */
class LoadLegacySchema extends Command
{
    protected $signature = 'app:load-schema
        {--database= : Database name (default: DB_DATABASE)}
        {--fresh : Drop and recreate the database first (only for *_testing databases)}
        {--without-triggers : Leave out triggers and events (before importing data)}
        {--triggers-only : Only add triggers and events (after importing data)}';

    protected $description = 'Load the MyAT schema (no data) into an empty database';

    public function handle(): int
    {
        $name = str_replace('`', '', (string) ($this->option('database') ?: config('database.connections.mariadb.database')));

        if ($this->option('fresh') && ! str_ends_with($name, '_testing')) {
            $this->components->error("Refusing to drop [{$name}]: --fresh only works on databases ending in _testing.");

            return self::FAILURE;
        }

        config(['database.connections.legacy_schema' => array_merge(
            config('database.connections.'.config('database.default')),
            ['database' => null],
        )]);

        $server = DB::connection('legacy_schema');

        if ($this->option('fresh')) {
            $server->statement("DROP DATABASE IF EXISTS `{$name}`");
        }

        $server->statement("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        DB::purge('legacy_schema');

        config(['database.connections.legacy_schema.database' => $name]);
        $connection = DB::connection('legacy_schema');

        $part = match (true) {
            (bool) $this->option('triggers-only') => 'triggers-only',
            (bool) $this->option('without-triggers') => 'without-triggers',
            default => 'all',
        };

        if ($part === 'triggers-only') {
            if (! LegacySchema::isLoaded($connection)) {
                $this->components->error("[{$name}] has no MyAT tables yet; run with --without-triggers first.");

                return self::FAILURE;
            }

            if ($connection->selectOne('select count(*) as n from information_schema.TRIGGERS where TRIGGER_SCHEMA = ?', [$name])->n > 0) {
                $this->components->warn("[{$name}] already has triggers; nothing done.");

                return self::SUCCESS;
            }
        } elseif (LegacySchema::isLoaded($connection)) {
            $this->components->warn("[{$name}] already has the MyAT tables; nothing done.");

            return self::SUCCESS;
        }

        $count = LegacySchema::load($connection, $part);
        DB::purge('legacy_schema');

        $this->components->info("Loaded {$count} statements into [{$name}].");

        return self::SUCCESS;
    }
}
