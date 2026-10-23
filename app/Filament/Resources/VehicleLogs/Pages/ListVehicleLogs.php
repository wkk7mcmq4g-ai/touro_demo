<?php

namespace App\Filament\Resources\VehicleLogs\Pages;

use App\Filament\Resources\VehicleLogs\VehicleLogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVehicleLogs extends ListRecords
{
    protected static string $resource = VehicleLogResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
