<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Each user's table layouts (column order, hidden columns, widths), kept in the PHPRunner table
 * for user settings, MyAthensTransfersAPP_settings, so they follow the user to any browser.
 *
 * Rows use TYPE 100 (PHPRunner's own rows have other types and are left alone):
 * USERNAME = the login, TABLENAME = the setting's key, SEARCH = its JSON value.
 * While signed in, the layouts live in the session where Filament reads them.
 */
class TableLayouts
{
    public const TYPE = 100;

    /** Session keys that are saved: Filament's column manager state and our column widths. */
    public static function current(): array
    {
        $saved = [];

        foreach ((array) session('filament.tables', []) as $table => $state) {
            foreach (['columns', 'has_reordered_columns'] as $key) {
                if (is_array($state) && array_key_exists($key, $state)) {
                    $saved["filament.tables.{$table}.{$key}"] = $state[$key];
                }
            }
        }

        foreach ((array) session('myat.widths', []) as $component => $widths) {
            $saved["myat.widths:{$component}"] = $widths;
        }

        return $saved;
    }

    public static function load(User $user): void
    {
        $rows = DB::table('MyAthensTransfersAPP_settings')
            ->where('TYPE', self::TYPE)->where('USERNAME', $user->username)
            ->get(['TABLENAME', 'SEARCH']);

        foreach ($rows as $row) {
            $value = json_decode((string) $row->SEARCH, true);
            $key = (string) $row->TABLENAME;
            if ($value === null || ! self::allowedKey($key)) {
                continue;
            }
            // Component names contain dots, so widths are one array keyed by component.
            str_starts_with($key, 'myat.widths:')
                ? session()->put('myat.widths', [substr($key, 12) => $value] + (array) session('myat.widths', []))
                : session()->put($key, $value);
        }

        session()->put('myat.layouts_hash', md5(serialize(self::current())));
    }

    /** Writes the layouts that changed since they were loaded or last saved. */
    public static function save(User $user): void
    {
        $current = self::current();
        $hash = md5(serialize($current));

        if ($hash === session('myat.layouts_hash')) {
            return;
        }

        foreach ($current as $key => $value) {
            DB::table('MyAthensTransfersAPP_settings')->updateOrInsert(
                ['TYPE' => self::TYPE, 'USERNAME' => $user->username, 'TABLENAME' => $key],
                ['SEARCH' => json_encode($value, JSON_UNESCAPED_UNICODE), 'NAME' => 'table layout'],
            );
        }

        session()->put('myat.layouts_hash', $hash);
    }

    /** Only these keys are ever written back into the session from the database. */
    public static function allowedKey(string $key): bool
    {
        return (bool) preg_match('/^(filament\.tables\.[0-9a-f.]+\.(columns|has_reordered_columns)|myat\.widths:[A-Za-z0-9._\\\\-]+)$/', $key);
    }
}
