<?php

namespace App\Filament\Portal\Resources\MyFuel\Pages;

use App\Filament\Concerns\HandlesLegacyFiles;
use App\Filament\Portal\Resources\MyFuel\MyFuelResource;
use Filament\Resources\Pages\EditRecord;

class EditMyFuel extends EditRecord
{
    use HandlesLegacyFiles;

    protected static string $resource = MyFuelResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->mergeLegacyFiles($data, $this->getRecord());
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
