<?php

namespace App\Filament\Resources\FuelRegisters\Pages;

use App\Filament\Concerns\HandlesLegacyFiles;
use App\Filament\Resources\FuelRegisters\FuelRegisterResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFuelRegister extends EditRecord
{
    use HandlesLegacyFiles;

    protected static string $resource = FuelRegisterResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->mergeLegacyFiles($data, $this->getRecord());
    }
}
