<?php

namespace App\Filament\Resources\VehicleLogs\Pages;

use App\Filament\Concerns\HandlesLegacyFiles;
use App\Filament\Resources\VehicleLogs\VehicleLogResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVehicleLog extends EditRecord
{
    use HandlesLegacyFiles;

    protected static string $resource = VehicleLogResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->mergeLegacyFiles($data, $this->getRecord());
    }
}
