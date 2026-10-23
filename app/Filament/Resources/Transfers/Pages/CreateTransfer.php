<?php

namespace App\Filament\Resources\Transfers\Pages;

use App\Filament\Resources\Transfers\TransferResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTransfer extends CreateRecord
{
    /** Save stays in reach at the bottom of the screen, also on phones. */
    public static bool $formActionsAreSticky = true;

    protected static string $resource = TransferResource::class;

    protected function getRedirectUrl(): string
    {
        // Straight to the transfer, so notes, charges and files can be added.
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
