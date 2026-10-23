<?php

namespace App\Filament\Resources\FuelRegisters\Pages;

use App\Filament\Concerns\HandlesLegacyFiles;
use App\Filament\Resources\FuelRegisters\FuelRegisterResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFuelRegister extends CreateRecord
{
    use HandlesLegacyFiles;

    protected static string $resource = FuelRegisterResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->mergeLegacyFiles($data, null);
    }
}
