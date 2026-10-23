<?php

namespace App\Support;

use App\Models\User;

/**
 * Checks the PHPRunner permission masks in config/permissions.php.
 *
 * Usage: Permissions::allows($user, 'transfers', 'edit')
 */
class Permissions
{
    /** Maps Laravel/Filament ability names to PHPRunner mask letters. */
    public const ABILITIES = [
        'viewAny' => 'S',
        'view' => 'S',
        'list' => 'S',
        'search' => 'S',
        'create' => 'A',
        'add' => 'A',
        'replicate' => 'A',
        'update' => 'E',
        'edit' => 'E',
        'delete' => 'D',
        'deleteAny' => 'D',
        'export' => 'P',
        'print' => 'P',
        'import' => 'I',
    ];

    public static function mask(?string $group, string $table): string
    {
        $matrix = config('permissions', []);
        $groupTable = $matrix[$group] ?? $matrix['<Default>'] ?? [];

        // PHPRunner table names are case-insensitive (e.g. PaymentsReceived).
        foreach ($groupTable as $name => $mask) {
            if (strcasecmp($name, $table) === 0) {
                return $mask;
            }
        }

        return '';
    }

    public static function allows(?User $user, string $table, string $ability): bool
    {
        if (! $user) {
            return false;
        }

        $letter = self::ABILITIES[$ability] ?? strtoupper($ability);

        return str_contains(self::mask($user->groupid, $table), $letter);
    }
}
