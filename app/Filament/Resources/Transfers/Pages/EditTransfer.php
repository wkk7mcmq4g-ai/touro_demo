<?php

namespace App\Filament\Resources\Transfers\Pages;

use App\Filament\Resources\Transfers\TransferResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Livewire\Attributes\On;

class EditTransfer extends EditRecord
{
    /** Save stays in reach at the bottom of the screen, also on phones. */
    public static bool $formActionsAreSticky = true;

    protected static string $resource = TransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Triggers and Update_Transfers_Price change the row after saving (prices, lessee,
     * invoice type...), so show what the database now holds.
     */
    protected function afterSave(): void
    {
        $this->getRecord()->refresh();
        $this->fillForm();
    }

    /** A price line changed: the trigger recalculated the Sale price, show it. */
    #[On('transfer-prices-changed')]
    public function refreshSalePrice(): void
    {
        $this->data['SalePrice'] = $this->getRecord()->newQuery()
            ->whereKey($this->getRecord()->getKey())
            ->value('SalePrice');
    }
}
