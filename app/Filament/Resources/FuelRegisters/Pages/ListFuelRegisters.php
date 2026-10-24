<?php

namespace App\Filament\Resources\FuelRegisters\Pages;

use App\Filament\Resources\FuelRegisters\FuelRegisterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListFuelRegisters extends ListRecords
{
    protected static string $resource = FuelRegisterResource::class;

    /** Wide like the other lists: tables use the whole width of the screen. */
    protected Width|string|null $maxContentWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
