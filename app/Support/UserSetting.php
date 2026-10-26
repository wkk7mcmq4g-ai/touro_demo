<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * One small per-user value (JSON) in the PHPRunner settings table, like the table layouts and the
 * side menu: TYPE 100, USERNAME = the login, TABLENAME = the key, SEARCH = the value.
 */
class UserSetting
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $user = auth()->user();
        if (! $user) {
            return $default;
        }

        $json = DB::table('MyAthensTransfersAPP_settings')
            ->where(['TYPE' => TableLayouts::TYPE, 'USERNAME' => $user->username, 'TABLENAME' => $key])
            ->value('SEARCH');

        return is_string($json) ? (json_decode($json, true) ?? $default) : $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $where = ['TYPE' => TableLayouts::TYPE, 'USERNAME' => $user->username, 'TABLENAME' => $key];
        $value === null || $value === []
            ? DB::table('MyAthensTransfersAPP_settings')->where($where)->delete()
            : DB::table('MyAthensTransfersAPP_settings')->updateOrInsert($where, ['SEARCH' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'NAME' => 'user setting']);
    }
}
