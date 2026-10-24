<?php

namespace App\Filament\Resources\Transfers\Pages;

use App\Filament\Resources\Transfers\TransferResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
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

    /**
     * The lists (notes, products, charges, files, driver comments) are inside the form's tabs, as on
     * PHPRunner's page (TransferForm), so nothing is drawn under the form.
     */
    public function getRelationManagersContentComponent(): Component
    {
        return Group::make()->hidden();
    }

    /**
     * A price line, charge, expense or note was added, changed or deleted: the database triggers
     * recalculated the transfer (sale price, extra income, expenses, open charges, issue flags).
     * Show the new values at once, without touching anything else typed on the form; saving the form
     * afterwards then keeps them instead of writing the old values back.
     */
    #[On('transfer-prices-changed')]
    public function refreshCalculatedFields(): void
    {
        $fields = ['SalePrice', 'ExtraIncome', 'Expense', 'OperationalIssuesFlag', 'FinancialIssuesFlag'];
        $fresh = $this->getRecord()->newQuery()->whereKey($this->getRecord()->getKey())->first($fields);

        foreach ($fields as $field) {
            $this->data[$field] = $fresh?->getAttribute($field);
        }
    }
}
