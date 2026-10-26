<?php

namespace Tests\Feature;

use App\Filament\Pages\ElorusContacts;
use App\Filament\Pages\ElorusSettings;
use App\Filament\Pages\Reports\TaxisReport;
use App\Support\Help;
use App\Filament\Resources\Charges\Pages\ListCharges;
use App\Filament\Resources\Counterparties\Pages\ListCounterparties;
use App\Filament\Resources\Transfers\Pages\ListTransfers;
use Livewire\Livewire;
use Tests\TestCase;

/** Demo showcase: nothing about Elorus on screen (elorus.ui), no incoming payment button (transfers.incoming_payment). */
class DemoElorusHiddenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs($this->makeLegacyUser('Admin'));
    }

    public function test_the_demo_ships_with_elorus_hidden(): void
    {
        $this->assertFalse(config('elorus.ui'));
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
        config(['elorus.ui' => true]);
        Livewire::test(ListTransfers::class)->assertTableBulkActionVisible('invoiceTotal');
        $this->assertTrue(ElorusSettings::canAccess());
    }

    public function test_no_incoming_payment_button(): void
    {
        $this->assertFalse(config('transfers.incoming_payment'));
        Livewire::test(ListTransfers::class)->assertActionHidden('incomingPayment');
        $this->get('/admin/transfers')->assertOk()->assertDontSee('Εισερχόμενο έμβασμα');

        config(['transfers.incoming_payment' => true]);
        Livewire::test(ListTransfers::class)->assertActionVisible('incomingPayment');
    }

    public function test_no_elorus_fields_columns_or_filters(): void
    {
        // Visible text only: Livewire's page state still names the (hidden) fields.
        $this->get('/admin/counterparties/create')->assertOk()->assertDontSeeText('Elorus')->assertDontSeeText('myDATA');
        $this->get('/admin/counterparties')->assertOk()->assertDontSeeText('Elorus');
        $this->get('/admin/transfers/create')->assertOk()->assertDontSeeText('Elorus');
        $this->get(TaxisReport::getUrl())->assertOk()->assertDontSeeText('Elorus');
    }

    public function test_the_manual_has_no_elorus_or_phprunner(): void
    {
        foreach (Help::PANELS as $panel) {
            foreach (Help::articles($panel) as $article) {
                $this->assertStringNotContainsStringIgnoringCase('elorus', $article['body'], "{$panel}/{$article['slug']}");
                $this->assertStringNotContainsStringIgnoringCase('phprunner', $article['body'], "{$panel}/{$article['slug']}");
                $this->assertStringNotContainsString('Εισερχόμενο έμβασμα', $article['body'], "{$panel}/{$article['slug']}");
            }
        }
        $this->assertNull(Help::find('admin', 'invoicing'));
    }
}
