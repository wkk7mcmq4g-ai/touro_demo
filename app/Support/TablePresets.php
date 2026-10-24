<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Each user's saved exports (which columns) and saved filters, per table, kept in the PHPRunner
 * settings table MyAthensTransfersAPP_settings so they follow the user to any browser:
 * TYPE 101 = export, 102 = filters; USERNAME = the login, TABLENAME = the table (Livewire
 * component name), NAME = the name the user gave, SEARCH = the JSON. PHPRunner's rows use other
 * types and are left alone.
 */
class TablePresets
{
    public const EXPORT = 101;

    public const FILTERS = 102;

    private static function username(): string
    {
        return (string) auth()->user()?->username;
    }

    /** @return list<array{id: int, name: string, data: array}> */
    public static function list(int $type, string $table): array
    {
        return DB::table('MyAthensTransfersAPP_settings')
            ->where('TYPE', $type)->where('USERNAME', self::username())->where('TABLENAME', $table)
            ->orderBy('NAME')->get(['ID', 'NAME', 'SEARCH'])
            ->map(fn ($row): array => ['id' => (int) $row->ID, 'name' => (string) $row->NAME, 'data' => (array) json_decode((string) $row->SEARCH, true)])
            ->all();
    }

    /** Saves under the name; the same name again replaces it. */
    public static function save(int $type, string $table, string $name, array $data): int
    {
        $name = mb_substr(trim($name), 0, 80);
        abort_if($name === '' || self::username() === '', 422, 'Δώστε ένα όνομα.');

        $key = ['TYPE' => $type, 'USERNAME' => self::username(), 'TABLENAME' => $table, 'NAME' => $name];
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $id = DB::table('MyAthensTransfersAPP_settings')->where($key)->value('ID');

        if ($id) {
            DB::table('MyAthensTransfersAPP_settings')->where('ID', $id)->update(['SEARCH' => $json]);

            return (int) $id;
        }

        return (int) DB::table('MyAthensTransfersAPP_settings')->insertGetId($key + ['SEARCH' => $json], 'ID');
    }

    /** @return array{id: int, name: string, data: array}|null */
    public static function find(int $type, string $table, int $id): ?array
    {
        return collect(self::list($type, $table))->firstWhere('id', $id);
    }

    public static function delete(int $type, string $table, int $id): void
    {
        DB::table('MyAthensTransfersAPP_settings')
            ->where('ID', $id)->where('TYPE', $type)->where('USERNAME', self::username())->where('TABLENAME', $table)
            ->delete();
    }
}
