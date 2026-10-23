<?php

namespace App\Filament\Resources\Counterparties\Pages;

use App\Filament\Resources\Counterparties\CounterpartyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCounterparty extends CreateRecord
{
    protected static string $resource = CounterpartyResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
