<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\DB;

/**
 * Port of PHPRunner's AfterSuccessfulLogin / AfterLogout events:
 * one row in access_log per login and logout.
 */
class LogAccess
{
    public function login(Login $event): void
    {
        $this->write($event->user?->username, 'In');

        DB::table('MyAthensTransfersAPP_users')
            ->where('ID', $event->user?->getAuthIdentifier())
            ->update(['LastLoginDateTime' => now()]);
    }

    public function logout(Logout $event): void
    {
        $this->write($event->user?->username, 'Out');
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
