<?php

namespace App\Filament\Resources\Cash\Pages;

use App\Filament\Resources\Cash\CashResource;
use App\Models\Cash;
use Filament\Resources\Pages\CreateRecord;

class CreateCash extends CreateRecord
{
    protected static string $resource = CashResource::class;

    /** Start from the last count, so only what changed needs typing. */
    protected function fillForm(): void
    {
        $last = Cash::query()->latest('ID')->first();

        $this->form->fill($last ? ['DT' => now()->toDateString()] + $last->only(array_keys(Cash::BOXES)) : []);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
