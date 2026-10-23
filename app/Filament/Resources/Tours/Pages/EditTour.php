<?php

namespace App\Filament\Resources\Tours\Pages;

use App\Filament\Concerns\HandlesLegacyFiles;
use App\Filament\Resources\Tours\TourResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTour extends EditRecord
{
    use HandlesLegacyFiles;

    protected static string $resource = TourResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->mergeLegacyFiles($data, $this->getRecord());
    }
}
