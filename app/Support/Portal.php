<?php

namespace App\Support;

use App\Enums\UserGroup;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Whose data a portal user sees (PHPRunner AfterSuccessfulLogin + the tables' Init events):
 * partners are the counterparty whose UserID is them; suppliers and hotels the counterparty
 * in their user's SupplierID. Everything they see is filtered to that customer ID.
 */
class Portal
{
    /** @var array<int, int|null> */
    private static array $customers = [];

    public static function customerId(?User $user = null): ?int
    {
        $user ??= auth()->user();
        if (! $user instanceof User) {
            return null;
        }

        return self::$customers[$user->ID] ??= match ($user->group()) {
            UserGroup::Supplier, UserGroup::SupplierHotel => $user->SupplierID ?: null,
            default => DB::table('counterparties')->where('UserID', $user->ID)->value('CID'),
        };
    }

    /** The customer ID, or an ID nothing has, so a user without one sees nothing. */
    public static function scopeId(): int
    {
        return self::customerId() ?? -1;
    }

    public static function group(): ?UserGroup
    {
        $user = auth()->user();

        return $user instanceof User ? $user->group() : null;
    }

    public static function flush(): void
    {
        self::$customers = [];
    }
}
