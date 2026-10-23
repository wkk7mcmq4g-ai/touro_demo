<?php

namespace App\Support;

use Illuminate\Database\Connection;
use RuntimeException;

/**
 * Loads database/legacy/schema.sql (tables, views, procedures, functions,
 * triggers, events) into an EMPTY database. Understands the `DELIMITER`
 * lines of phpMyAdmin/mysqldump exports, so no `mysql` client is needed.
 */
class LegacySchema
{
    public static function path(): string
    {
        return database_path('legacy/schema.sql');
    }

    public static function isLoaded(Connection $connection): bool
    {
        return $connection->getSchemaBuilder()->hasTable('transfers');
    }

    /**
     * @param  'all'|'without-triggers'|'triggers-only'  $part
     */
    public static function load(Connection $connection, string $part = 'all'): int
    {
        $sql = file_get_contents(self::path());

        if ($sql === false) {
            throw new RuntimeException('Cannot read '.self::path());
        }

        $sql = self::adaptToServer($sql, (string) $connection->selectOne('select version() as v')->v);

        $statements = array_values(array_filter(
            self::split($sql),
            fn (string $statement): bool => match ($part) {
                'without-triggers' => ! self::isTriggerOrEvent($statement),
                'triggers-only' => self::isTriggerOrEvent($statement),
                default => true,
            },
        ));

        foreach ($statements as $statement) {
            $connection->unprepared($statement);
        }

        return count($statements);
    }

    /**
     * MariaDB before 10.6 (e.g. XAMPP's 10.4) only knows `utf8`, not the `utf8mb3` name
     * that newer servers export. They are the same character set.
     */
    public static function adaptToServer(string $sql, string $serverVersion): string
    {
        if (preg_match('/^(\d+)\.(\d+)/', $serverVersion, $match)
            && str_contains(strtolower($serverVersion), 'mariadb')
            && version_compare("{$match[1]}.{$match[2]}", '10.6', '<')) {
            return str_replace('utf8mb3', 'utf8', $sql);
        }

        return $sql;
    }

    public static function isTriggerOrEvent(string $statement): bool
    {
        return (bool) preg_match('/^CREATE\s+(TRIGGER|EVENT)\b/i', ltrim($statement));
    }

    /**
     * @return list<string>
     */
    public static function split(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $delimiter = ';';

        foreach (preg_split('/\R/', $sql) as $line) {
            if (preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i', $line, $match)) {
                $delimiter = $match[1];

                continue;
            }

            if ($buffer === '' && (trim($line) === '' || str_starts_with(ltrim($line), '--'))) {
                continue;
            }

            $buffer .= $line."\n";

            if (str_ends_with(rtrim($line), $delimiter)) {
                $statement = trim(substr(rtrim($buffer), 0, -strlen($delimiter)));
                $buffer = '';

                if ($statement !== '' && ! preg_match('/^(START TRANSACTION|COMMIT)$/i', $statement)) {
                    $statements[] = $statement;
                }
            }
        }

        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }

        return $statements;
    }
}
