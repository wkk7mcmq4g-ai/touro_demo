<?php

namespace App\Filament\Resources\VehicleKms\Pages;

use App\Filament\Resources\VehicleKms\VehicleKmResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVehicleKm extends EditRecord
{
    protected static string $resource = VehicleKmResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
