<?php

namespace App\Filament\Resources\VehicleLogs\Pages;

use App\Filament\Resources\VehicleLogs\VehicleLogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListVehicleLogs extends ListRecords
{
    protected static string $resource = VehicleLogResource::class;

    /** Wide like the other lists: tables use the whole width of the screen. */
    protected Width|string|null $maxContentWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
