<?php

namespace App\Filament\Resources\Drivers\Pages;

use App\Filament\Concerns\HandlesLegacyFiles;
use App\Filament\Resources\Drivers\DriverResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDriver extends CreateRecord
{
    use HandlesLegacyFiles;

    protected static string $resource = DriverResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->mergeLegacyFiles($data, null);
    }
}
