<?php

namespace App\Listeners;

use App\Support\ActivityLog;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\DB;

/**
 * Port of PHPRunner's AfterSuccessfulLogin / AfterLogout events:
 * one row in access_log per login and logout (and one in the activity log, App\Support\ActivityLog).
 */
class LogAccess
{
    public function login(Login $event): void
    {
        // This bookkeeping is the login itself in the activity log, not two separate writes.
        ActivityLog::quietly(function () use ($event): void {
            $this->write($event->user?->username, 'In');

            DB::table('MyAthensTransfersAPP_users')
                ->where('ID', $event->user?->getAuthIdentifier())
                ->update(['LastLoginDateTime' => now()]);
        });

        ActivityLog::login($event->user?->username);
    }

    public function logout(Logout $event): void
    {
        ActivityLog::quietly(fn () => $this->write($event->user?->username, 'Out'));

        ActivityLog::logout($event->user?->username);
    }

    private function write(?string $username, string $status): void
    {
        if (! $username) {
            return;
        }

        DB::table('access_log')->insert([
            'userid' => $username,
            'AccessStatus' => $status,
            'LogDate' => now(),
        ]);
    }
}
