<?php

namespace App\Filament\Resources\Transfers\Pages;

use App\Filament\Resources\Transfers\TransferResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs;
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
     * The tabs under the form (Notes, Price lines, Charges...) switch in the browser: Filament's own
     * tabs asked the server to redraw the whole page, form included, on every click. Each list loads
     * on its own (lazily) the first time its tab is opened; the open tab is kept in the URL.
     */
    public function getRelationManagersContentComponent(): Component
    {
        $managers = $this->getCachedRelationManagers();
        $data = $this->getRelationManagerLivewireData();

        return Tabs::make()
            ->key('relationManagerTabs')
            ->contained(false)
            ->persistTabInQueryString('relation')
            ->tabs(collect($managers)->map(function (string $manager, string|int $key) use ($data) {
                $class = $this->normalizeRelationManagerClass($manager);

                return $class::getTabComponent($this->getRecord(), static::class)
                    ->schema([
                        Livewire::make($class, [...$data, ...$class::getDefaultProperties()])
                            ->key($class)
                            ->lazy(),
                    ]);
            })->all());
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
