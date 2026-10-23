<?php

namespace App\Filament\Pages;

use App\Support\Permissions;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * PHPRunner "Calendar": the drivers' schedule calendar, a separate page of the PHPRunner
 * site (driver_cal) shown inside the app, as PHPRunner did.
 */
class DriverCalendar extends Page
{
    protected string $view = 'filament.pages.driver-calendar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static string|UnitEnum|null $navigationGroup = 'Fleet & drivers';

    protected static ?int $navigationSort = 12;

    protected static ?string $navigationLabel = 'Calendar';

    protected static ?string $title = 'Πρόγραμμα οδηγών';

    protected Width|string|null $maxContentWidth = Width::Full;

    public static function canAccess(): bool
    {
        return Permissions::allows(auth()->user(), 'drivers', 'view');
    }

    public function calendarUrl(): string
    {
        return rtrim((string) config('services.phprunner.url'), '/').'/driver_cal';
    }
}
