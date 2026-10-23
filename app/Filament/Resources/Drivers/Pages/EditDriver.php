<?php

namespace App\Filament\Resources\Drivers\Pages;

use App\Filament\Concerns\HandlesLegacyFiles;
use App\Filament\Resources\Drivers\DriverResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDriver extends EditRecord
{
    use HandlesLegacyFiles;

    protected static string $resource = DriverResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->mergeLegacyFiles($data, $this->getRecord());
    }
}
