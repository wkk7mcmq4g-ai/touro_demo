<?php

namespace App\Filament\Resources\VehicleKms\Pages;

use App\Filament\Resources\VehicleKms\VehicleKmResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVehicleKms extends ListRecords
{
    protected static string $resource = VehicleKmResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
