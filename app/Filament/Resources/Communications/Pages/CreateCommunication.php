<?php

namespace App\Filament\Resources\Communications\Pages;

use App\Filament\Resources\Communications\CommunicationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCommunication extends CreateRecord
{
    protected static string $resource = CommunicationResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
