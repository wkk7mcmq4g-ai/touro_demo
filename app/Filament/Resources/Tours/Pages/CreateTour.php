<?php

namespace App\Filament\Resources\Tours\Pages;

use App\Filament\Concerns\HandlesLegacyFiles;
use App\Filament\Resources\Tours\TourResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTour extends CreateRecord
{
    use HandlesLegacyFiles;

    protected static string $resource = TourResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->mergeLegacyFiles($data, null);
    }
}
