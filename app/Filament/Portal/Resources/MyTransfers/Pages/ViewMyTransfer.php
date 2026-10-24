<?php

namespace App\Filament\Portal\Resources\MyTransfers\Pages;

use App\Filament\Portal\Resources\MyTransfers\MyTransferResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMyTransfer extends ViewRecord
{
    protected static string $resource = MyTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()->visible(fn (): bool => MyTransferResource::canEdit($this->getRecord()))];
    }
}
