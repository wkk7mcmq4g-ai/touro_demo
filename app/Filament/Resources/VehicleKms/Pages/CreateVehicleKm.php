<?php

namespace App\Filament\Resources\VehicleKms\Pages;

use App\Filament\Resources\VehicleKms\VehicleKmResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVehicleKm extends CreateRecord
{
    protected static string $resource = VehicleKmResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
