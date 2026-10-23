<?php

namespace App\Filament\Resources\VehicleAssignments\Pages;

use App\Filament\Resources\VehicleAssignments\VehicleAssignmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVehicleAssignments extends ListRecords
{
    protected static string $resource = VehicleAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
