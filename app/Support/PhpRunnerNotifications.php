<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Writes PHPRunner-style notifications (the bell in the old app) to `touro_noti`,
 * in the same shape as PHPRunner's addNotification() and the Daily_Notifications
 * procedure, so they keep showing up there.
 */
class PhpRunnerNotifications
{
    public static function toGroup(string $group, string $title, string $message, string $url, string $icon = 'glyphicon-info-sign', int $expireMinutes = 1440): void
    {
        $usernames = DB::table('MyAthensTransfersAPP_users')
            ->where('groupid', $group)
            ->pluck('username');

        foreach ($usernames as $username) {
            self::toUser($username, $title, $message, $url, $icon, $expireMinutes);
        }
    }

    public static function toUser(string $username, string $title, string $message, string $url, string $icon = 'glyphicon-info-sign', int $expireMinutes = 1440): void
    {
        DB::table('touro_noti')->insert([
            'message' => json_encode(['message' => $message, 'newWindow' => false], JSON_UNESCAPED_UNICODE),
            'user' => $username,
            'provider' => null,
            'title' => $title,
            'url' => $url,
            'icon' => $icon,
            'created' => now(),
            'expire' => now()->addMinutes($expireMinutes),
            'type' => 0,
            'group' => null,
        ]);
    }

    /** Links still point at the PHPRunner app until that page is ported. */
    public static function legacyUrl(string $path): string
    {
        return rtrim((string) config('services.phprunner.url'), '/').'/'.ltrim($path, '/');
    }
}
