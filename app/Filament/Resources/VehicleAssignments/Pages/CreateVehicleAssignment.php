<?php

namespace App\Filament\Resources\VehicleAssignments\Pages;

use App\Filament\Resources\VehicleAssignments\VehicleAssignmentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVehicleAssignment extends CreateRecord
{
    protected static string $resource = VehicleAssignmentResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
