<?php

namespace App\Filament\Resources\Expenses\Pages;

use App\Filament\Concerns\HandlesLegacyFiles;
use App\Filament\Resources\Expenses\ExpenseResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditExpense extends EditRecord
{
    use HandlesLegacyFiles;

    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->mergeLegacyFiles($data, $this->getRecord());
    }
}
