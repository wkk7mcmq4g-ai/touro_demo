<?php

namespace App\Filament\Resources\FuelRegisters\Pages;

use App\Filament\Resources\FuelRegisters\FuelRegisterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFuelRegisters extends ListRecords
{
    protected static string $resource = FuelRegisterResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
