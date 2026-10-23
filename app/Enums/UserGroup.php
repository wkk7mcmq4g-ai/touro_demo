<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The PHPRunner user groups, stored as text in MyAthensTransfersAPP_users.groupid.
 */
enum UserGroup: string implements HasColor, HasLabel
{
    case Admin = 'Admin';
    case Entry = 'Entry';
    case Coach = 'Coach';
    case Drivers = 'Drivers';
    case DriversHol = 'Drivers_Hol';
    case DriversLog = 'Drivers_Log';
    case Partner = 'Partner';
    case Supplier = 'Supplier';
    case SupplierHotel = 'Supplier_Hotel';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Entry => 'Entry (office)',
            self::Coach => 'Coach company',
            self::Drivers => 'Driver',
            self::DriversHol => 'Driver (holidays)',
            self::DriversLog => 'Driver (vehicle log)',
            self::Partner => 'Partner',
            self::Supplier => 'Supplier',
            self::SupplierHotel => 'Supplier (hotel)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Admin => 'danger',
            self::Entry => 'primary',
            self::Partner, self::Supplier, self::SupplierHotel, self::Coach => 'info',
            default => 'gray',
        };
    }

    /**
     * Groups that work in the back-office panel. The others (drivers, partners,
     * suppliers, coach companies) get their own portal or the Drivers PWA later.
     */
    public function usesBackOffice(): bool
    {
        return in_array($this, [self::Admin, self::Entry], true);
    }

    /**
     * Groups that use the portal (/portal): partners, suppliers, hotels and coach companies.
     * Drivers are not in it yet (they keep PHPRunner / the Drivers PWA for now).
     */
    public function usesPortal(): bool
    {
        return in_array($this, [self::Partner, self::Supplier, self::SupplierHotel, self::Coach], true);
    }

    public function isDriver(): bool
    {
        return in_array($this, [self::Drivers, self::DriversHol, self::DriversLog], true);
    }
}
