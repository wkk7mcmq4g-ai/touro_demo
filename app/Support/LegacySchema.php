<?php

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
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

        // Windows/macOS servers ignore table-name case: drop the case-only duplicates there.
        if ((int) $connection->selectOne('select @@lower_case_table_names as l')->l > 0) {
            $statements = self::withoutCaseDuplicates($statements);
        }

        $loaded = 0;

        foreach ($statements as $statement) {
            if (preg_match('/^CREATE\s+EVENT\b/i', $statement)) {
                // Copies must never run the live jobs (e.g. Daily_Notifications): create them disabled.
                $statement = preg_replace('/\bENABLE(\s+DO)\b/i', 'DISABLE$1', $statement);

                try {
                    $connection->unprepared($statement);
                    $loaded++;
                } catch (QueryException $exception) {
                    // 1577: the server has the event scheduler disabled; the event is optional here.
                    if (($exception->errorInfo[1] ?? null) !== 1577) {
                        throw $exception;
                    }
                }

                continue;
            }

            $connection->unprepared($statement);
            $loaded++;
        }

        return $loaded;
    }

    /**
     * The live (Linux) database has tables whose names differ only in case, e.g.
     * `MyAthensTransfersAPP6ugmembers` and `myathenstransfersapp6ugmembers` (unused
     * PHPRunner leftovers). Keeps the first spelling of each and drops the statements
     * that create or alter a later one.
     *
     * @param  list<string>  $statements
     * @return list<string>
     */
    public static function withoutCaseDuplicates(array $statements): array
    {
        $firstSpelling = [];
        $duplicates = [];

        foreach ($statements as $statement) {
            if (preg_match('/^CREATE TABLE `([^`]+)`/i', $statement, $match)) {
                $lower = strtolower($match[1]);

                if (isset($firstSpelling[$lower]) && $firstSpelling[$lower] !== $match[1]) {
                    $duplicates[$match[1]] = true;
                } else {
                    $firstSpelling[$lower] ??= $match[1];
                }
            }
        }

        return array_values(array_filter(
            $statements,
            fn (string $statement): bool => ! (preg_match('/^(?:CREATE|ALTER) TABLE `([^`]+)`/i', $statement, $match)
                && isset($duplicates[$match[1]])),
        ));
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
