<?php

namespace App\Filament\Portal\Resources\MyTransfers\Pages;

use App\Filament\Portal\Resources\MyTransfers\MyTransferResource;
use Filament\Resources\Pages\EditRecord;

class EditMyTransfer extends EditRecord
{
    protected static string $resource = MyTransferResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
