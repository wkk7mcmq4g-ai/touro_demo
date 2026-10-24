<?php

namespace App\Filament\Resources\TransfersToSort\Pages;

use App\Filament\Resources\TransfersToSort\TransferToSortResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;

class EditTransferToSort extends EditRecord
{
    protected static string $resource = TransferToSortResource::class;

    /** The products, notes and files are inside the form's tabs (LoadFormTabs), as on PHPRunner's page. */
    public function getRelationManagersContentComponent(): Component
    {
        return Group::make()->hidden();
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
