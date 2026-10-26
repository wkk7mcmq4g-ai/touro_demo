<?php

namespace App\Filament\Resources\Transfers\Pages;

use App\Filament\Resources\Transfers\TransferResource;
use Filament\Actions\Action;
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

    /** Set by afterSave, so "Αποθήκευση & πίσω" only leaves when the save went through. */
    private bool $justSaved = false;

    /** Αποθήκευση (stay on the form), Αποθήκευση & πίσω (back to the list), Ακύρωση. */
    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()->label('Αποθήκευση'),
            Action::make('saveAndBack')
                ->label('Αποθήκευση & πίσω')
                ->icon('heroicon-m-arrow-uturn-left')
                ->color('gray')
                ->action('saveAndBack'),
            $this->getCancelFormAction(),
        ];
    }

    /** Save, then back to the list the user came from (same tab, filters, search), else the plain list. */
    public function saveAndBack(): void
    {
        $this->save(shouldRedirect: false);

        if (! $this->justSaved) {
            return;
        }

        $list = $this->getResourceUrl();
        $back = $this->previousUrl && parse_url($this->previousUrl, PHP_URL_PATH) === parse_url($list, PHP_URL_PATH)
            ? $this->previousUrl
            : $list;

        $this->redirect($back);
    }

    /**
     * Triggers and Update_Transfers_Price change the row after saving (prices, lessee,
     * invoice type...), so show what the database now holds.
     */
    protected function afterSave(): void
    {
        $this->justSaved = true;
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
