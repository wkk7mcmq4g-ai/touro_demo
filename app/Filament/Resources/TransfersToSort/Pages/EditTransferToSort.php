<?php

namespace App\Filament\Resources\TransfersToSort\Pages;

use App\Filament\Resources\TransfersToSort\TransferToSortResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTransferToSort extends EditRecord
{
    protected static string $resource = TransferToSortResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
