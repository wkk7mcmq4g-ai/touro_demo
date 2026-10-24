<?php

namespace App\Filament\Resources\VehicleKms\Pages;

use App\Filament\Resources\VehicleKms\VehicleKmResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListVehicleKms extends ListRecords
{
    protected static string $resource = VehicleKmResource::class;

    /** Wide like the other lists: tables use the whole width of the screen. */
    protected Width|string|null $maxContentWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
