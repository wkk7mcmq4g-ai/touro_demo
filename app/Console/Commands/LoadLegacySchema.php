<?php

namespace App\Console\Commands;

use App\Support\LegacySchema;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Creates an empty copy of the MyAT database from database/legacy/schema.sql.
 *
 *   php artisan app:load-schema                    # into the .env database
 *   php artisan app:load-schema --database=myat_testing
 *
 * Refuses to touch a database that already has tables.
 */
class LoadLegacySchema extends Command
{
    protected $signature = 'app:load-schema {--database= : Database name (default: DB_DATABASE)}';

    protected $description = 'Load the MyAT schema (no data) into an empty database';

    public function handle(): int
    {
        $name = $this->option('database') ?: config('database.connections.mariadb.database');

        config(['database.connections.legacy_schema' => array_merge(
            config('database.connections.'.config('database.default')),
            ['database' => null],
        )]);

        DB::connection('legacy_schema')->statement(
            'CREATE DATABASE IF NOT EXISTS `'.str_replace('`', '', $name).'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
        );
        DB::purge('legacy_schema');

        config(['database.connections.legacy_schema.database' => $name]);
        $connection = DB::connection('legacy_schema');

        if (LegacySchema::isLoaded($connection)) {
            $this->components->warn("[{$name}] already has the MyAT tables; nothing done.");

            return self::SUCCESS;
        }

        $count = LegacySchema::load($connection);

        $this->components->info("Loaded {$count} statements into [{$name}].");

        return self::SUCCESS;
    }
}
