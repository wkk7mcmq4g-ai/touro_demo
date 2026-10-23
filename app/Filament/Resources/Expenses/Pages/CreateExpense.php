<?php

namespace App\Filament\Resources\Expenses\Pages;

use App\Filament\Concerns\HandlesLegacyFiles;
use App\Filament\Resources\Expenses\ExpenseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExpense extends CreateRecord
{
    use HandlesLegacyFiles;

    protected static string $resource = ExpenseResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->mergeLegacyFiles($data, null);
    }
}
