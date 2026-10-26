<?php

namespace Tests\Feature;

use App\Filament\Pages\ElorusContacts;
use App\Filament\Pages\ElorusSettings;
use App\Filament\Resources\Charges\Pages\ListCharges;
use App\Filament\Resources\Counterparties\Pages\ListCounterparties;
use App\Filament\Resources\Transfers\Pages\ListTransfers;
use Livewire\Livewire;
use Tests\TestCase;

/** Demo showcase: no Elorus buttons or screens (config elorus.buttons = false). */
class DemoElorusHiddenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->makeLegacyUser('Admin'));
    }

    public function test_the_demo_ships_with_elorus_hidden(): void
    {
        $this->assertFalse(config('elorus.buttons'));
    }

    public function test_no_elorus_actions_on_transfers_charges_or_partners(): void
    {
        $transfers = Livewire::test(ListTransfers::class);
        foreach (['invoiceTotal', 'invoiceLines', 'invoicesPerLead', 'receipts', 'taxiReceipts', 'getInvoicePdf'] as $action) {
            $transfers->assertTableBulkActionHidden($action);
        }
        $transfers->assertTableBulkActionVisible('closeSales'); // not Elorus: stays

        Livewire::test(ListCharges::class)->assertTableBulkActionHidden('issueReceipt');
        Livewire::test(ListCounterparties::class)->assertTableBulkActionHidden('addToElorus');
    }

    public function test_no_elorus_screens(): void
    {
        $this->assertFalse(ElorusSettings::canAccess());
        $this->assertFalse(ElorusContacts::canAccess());
        $this->get(ElorusSettings::getUrl())->assertForbidden();
        $this->get(ElorusContacts::getUrl())->assertForbidden();
        $this->get('/admin')->assertOk()->assertDontSee('Elorus Settings')->assertDontSee('Elorus Contacts');
    }

    public function test_switching_it_back_on_shows_them(): void
    {
        config(['elorus.buttons' => true]);
        Livewire::test(ListTransfers::class)->assertTableBulkActionVisible('invoiceTotal');
        $this->assertTrue(ElorusSettings::canAccess());
    }
}
