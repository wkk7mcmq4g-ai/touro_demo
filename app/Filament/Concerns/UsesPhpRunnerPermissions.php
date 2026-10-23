<?php

namespace App\Filament\Concerns;

use App\Support\Permissions;
use Illuminate\Database\Eloquent\Model;

/**
 * Applies the PHPRunner permission mask of `$permissionTable` to a Filament resource.
 * Set `protected static string $permissionTable = 'transfers';` on the resource.
 */
trait UsesPhpRunnerPermissions
{
    protected static function allows(string $ability): bool
    {
        return Permissions::allows(auth()->user(), static::$permissionTable, $ability);
    }

    public static function canViewAny(): bool
    {
        return static::allows('viewAny');
    }

    public static function canView(Model $record): bool
    {
        return static::allows('view');
    }

    public static function canCreate(): bool
    {
        return static::allows('create');
    }

    public static function canEdit(Model $record): bool
    {
        return static::allows('update');
    }

    public static function canDelete(Model $record): bool
    {
        return static::allows('delete');
    }

    public static function canDeleteAny(): bool
    {
        return static::allows('delete');
    }
}
