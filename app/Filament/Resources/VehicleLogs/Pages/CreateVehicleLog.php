<?php

namespace App\Filament\Resources\VehicleLogs\Pages;

use App\Filament\Concerns\HandlesLegacyFiles;
use App\Filament\Resources\VehicleLogs\VehicleLogResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVehicleLog extends CreateRecord
{
    use HandlesLegacyFiles;

    protected static string $resource = VehicleLogResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->mergeLegacyFiles($data, null);
    }
}
