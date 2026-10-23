<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Column lists of legacy tables, read once per request.
 */
class LegacyColumns
{
    /** @var array<string, list<string>> */
    private static array $columns = [];

    /**
     * @param  list<string>  $except
     * @return list<string>
     */
    public static function except(string $table, array $except): array
    {
        self::$columns[$table] ??= Schema::getColumnListing($table);

        return array_values(array_map(
            fn (string $column): string => "{$table}.{$column}",
            array_diff(self::$columns[$table], $except),
        ));
    }
}
