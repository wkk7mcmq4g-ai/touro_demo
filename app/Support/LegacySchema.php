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

    public static function load(Connection $connection): int
    {
        $sql = file_get_contents(self::path());

        if ($sql === false) {
            throw new RuntimeException('Cannot read '.self::path());
        }

        $statements = self::split($sql);

        foreach ($statements as $statement) {
            $connection->unprepared($statement);
        }

        return count($statements);
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
