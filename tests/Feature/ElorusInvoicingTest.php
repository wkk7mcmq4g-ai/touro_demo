<?php

namespace Tests\Feature;

use App\Filament\Resources\Transfers\Pages\EditTransfer;
use App\Filament\Resources\Transfers\Pages\ListTransfers;
use App\Filament\Resources\Transfers\RelationManagers\ChargesRelationManager;
use App\Models\Transfer;
use App\Models\TransferCharge;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ElorusInvoicingTest extends TestCase
{
    private int $customerId;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        config(['elorus.buttons' => true]); // hidden in the Demo; the Elorus code itself stays tested

        $this->emptyTables(['transfers', 'transfer_charges', 'myat_transfers_files', 'counterparties', 'elorus_Settings', 'optionvalues', 'status_values']);

        config(['elorus.enabled' => true, 'elorus.token' => null, 'elorus.organization' => null]);
        DB::table('elorus_Settings')->insert(['OrgID' => 'ORG1', 'apikey' => 'KEY1', 'DefaultProductID' => 'PRD1']);

        $billing = ['MydataDocumentType' => '2.1', 'MydataClassCategory' => 'category1_3', 'MydataClassType' => 'E3_561_001', 'DocumentType' => 'DT-INV'];
        $this->customerId = DB::table('counterparties')->insertGetId(['CounterShortName' => 'GYG', 'SupplierName' => 'GetYourGuide', 'IsSupplier' => 1, 'ElorusID' => 'CUST-GYG'] + $billing, 'CID');
        DB::table('counterparties')->insert(['CounterShortName' => 'Private Individual', 'SupplierName' => 'Private', 'IsSupplier' => 0, 'ElorusID' => 'CUST-PRIVATE'] + array_merge($billing, ['DocumentType' => 'DT-RCPT', 'MydataDocumentType' => '11.2']));

        $this->actingAs($this->makeLegacyUser('Admin'));

        $pdfWorks = $this->name() !== 'test_taxi_credit_note_is_issued_even_if_the_pdf_fails';
        Http::fake([
            'api.elorus.com/v1.2/invoices/*/pdf/' => $pdfWorks
                ? Http::response('%PDF-1.4 fake', 200, ['Content-Type' => 'application/pdf'])
                : Http::response(['detail' => 'Not found'], 404),
            'api.elorus.com/v1.2/creditnotes/*/pdf/' => Http::response('%PDF-1.4 credit', 200, ['Content-Type' => 'application/pdf']),
            'api.elorus.com/v1.2/invoices/' => fn () => Http::response(['id' => (string) (2208819500000000000 + ++$this->sequence), 'representation' => "ΤΠΥ Α {$this->sequence}", 'status' => 'draft']),
            'api.elorus.com/v1.2/creditnotes/' => fn () => Http::response(['id' => (string) (2208819600000000000 + ++$this->sequence), 'representation' => "ΠΙΣ Α {$this->sequence}", 'status' => 'draft']),
        ]);
    }

    private function transfer(array $attributes = []): Transfer
    {
        $id = DB::table('transfers')->insertGetId(array_merge([
            'ImportDate' => Carbon::today()->subDays(3), 'cID_Customer' => $this->customerId, 'TransferType' => 'Arrival',
            'TransferDate' => Carbon::today(), 'TransferTime' => '10:00', 'From' => 'Athens Airport', 'To' => 'Hotel Plaka',
            'CarTypeID' => 'VAN', 'Status' => 'Pending', 'LesseeName' => 'X', 'TobeInvoiced' => 'Τιμολόγιο', 'PaymentType' => 'Cash',
            'Pax' => '2', 'LeadName' => 'Maria', 'SalePrice' => 80,
        ], $attributes), 'TransferID');

        return Transfer::findOrFail($id);
    }

    public function test_one_invoice_with_a_line_per_transfer(): void
    {
        $a = $this->transfer(['SalesInvoiceNum' => 'OLD 1']);
        $b = $this->transfer(['SalePrice' => 45.5, 'LeadName' => 'Nikos']);

        Livewire::test(ListTransfers::class)
            ->callTableBulkAction('invoiceLines', [$a, $b], data: ['date' => '2026-10-23', 'draft' => true]);

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://api.elorus.com/v1.2/invoices/') {
                return false;
            }
            $body = $request->data();

            return $request->hasHeader('Authorization', 'Token KEY1') && $request->hasHeader('X-Elorus-Organization', 'ORG1')
                && $body['client'] === 'CUST-GYG' && $body['draft'] === true && $body['date'] === '2026-10-23'
                && $body['documenttype'] === 'DT-INV' && $body['mydata_document_type'] === '2.1'
                && count($body['items']) === 2 && $body['items'][1]['unit_value'] === '45.5'
                && $body['items'][0]['product'] === 'PRD1'
                && str_starts_with($body['items'][0]['description'], 'Athens Airport - Hotel Plaka, '.Carbon::today()->format('Y-m-d').', 10:00, 2 pax, Name: Maria');
        });
        Http::assertSentCount(1);

        $a->refresh();
        $this->assertSame(['Ναι', '2208819500000000001', 'ΤΠΥ Α 1 OLD 1'], [$a->InvoicedIssued, $a->ElorusID, $a->SalesInvoiceNum]);
        $this->assertSame('ΤΠΥ Α 1', $b->fresh()->SalesInvoiceNum);
    }

    public function test_one_invoice_total_and_one_per_lead_name(): void
    {
        $a = $this->transfer(['SalePrice' => 50]);
        $b = $this->transfer(['SalePrice' => 30, 'LeadName' => 'Nikos']);
        $c = $this->transfer(['SalePrice' => 20, 'LeadName' => 'Nikos']);

        Livewire::test(ListTransfers::class)
            ->callTableBulkAction('invoiceTotal', [$a, $b, $c], data: ['date' => '2026-10-23', 'description' => 'Transfers October', 'draft' => false]);
        Http::assertSent(fn (Request $r): bool => $r->url() === 'https://api.elorus.com/v1.2/invoices/'
            && $r['draft'] === false && count($r['items']) === 1 && $r['items'][0]['unit_value'] === '100' && $r['items'][0]['description'] === 'Transfers October');

        Livewire::test(ListTransfers::class)
            ->callTableBulkAction('invoicesPerLead', [$a, $b, $c], data: ['date' => '2026-10-23', 'draft' => true, 'again' => true]);
        Http::assertSentCount(3);
        $this->assertSame($b->fresh()->ElorusID, $c->fresh()->ElorusID);
        $this->assertNotSame($a->fresh()->ElorusID, $b->fresh()->ElorusID);
    }

    public function test_one_invoice_refuses_transfers_of_different_customers(): void
    {
        $other = DB::table('counterparties')->insertGetId(['CounterShortName' => 'VIA', 'SupplierName' => 'Viator', 'IsSupplier' => 1, 'ElorusID' => 'CUST-VIA'], 'CID');
        $a = $this->transfer();
        $b = $this->transfer(['cID_Customer' => $other]);

        Livewire::test(ListTransfers::class)
            ->callTableBulkAction('invoiceLines', [$a, $b], data: ['date' => '2026-10-23', 'draft' => true])
            ->assertNotified('Nothing was created in Elorus');

        Http::assertNothingSent();
        $this->assertNull($a->fresh()->ElorusID);
    }

    public function test_receipts_per_transfer_with_pdf(): void
    {
        config(['elorus.supplier_labels' => [$this->customerId => 'Get Your Guide']]);
        $a = $this->transfer(['SupplierCode' => 'GYG123', 'Platform' => 'Ναι', 'PlatformPrice' => 70, 'TransferType' => 'Tour']);
        $b = $this->transfer(['SupplierCode' => 'GYG124']);

        Livewire::test(ListTransfers::class)
            ->callTableBulkAction('receipts', [$a, $b], data: ['draft' => true, 'price' => 'platform', 'pdf' => 'auto']);

        Http::assertSent(fn (Request $r): bool => $r->url() === 'https://api.elorus.com/v1.2/invoices/' && $r['client'] === 'CUST-PRIVATE'
            && $r['items'][0]['description'] === 'Lead Name: Maria - Booking Reference :GYG123 / Get Your Guide ' && $r['items'][0]['unit_value'] === '70');
        Http::assertSent(fn (Request $r): bool => $r->url() === 'https://api.elorus.com/v1.2/invoices/' && $r['items'][0]['unit_value'] === '80');

        // Auto: the PDF only for the platform tour.
        $files = DB::table('myat_transfers_files')->get();
        $this->assertCount(1, $files);
        $this->assertSame([$a->TransferID, 1357, '%PDF-1.4 fake'], [$files[0]->TransferID, $files[0]->Type, $files[0]->Attachment]);
        $this->assertSame('ΑΠΥ', $a->fresh()->FLNM);
        $this->assertSame('Ναι', $b->fresh()->InvoicedIssued);
    }

    public function test_taxi_receipt_with_credit_note(): void
    {
        $a = $this->transfer(['TaxiReceipt' => '4471', 'TaxiReceiptAmount' => 35]);

        Livewire::test(ListTransfers::class)
            ->callTableBulkAction('taxiReceipts', [$a], data: ['draft' => true, 'price' => 'sale', 'pdf' => 'all', 'credit' => true]);

        Http::assertSent(fn (Request $r): bool => $r->url() === 'https://api.elorus.com/v1.2/invoices/'
            && str_ends_with($r['items'][0]['description'], 'και σε αντικατάσταση της απόδειξης με AA  4471'));
        Http::assertSent(fn (Request $r): bool => $r->url() === 'https://api.elorus.com/v1.2/creditnotes/'
            && $r['documenttype'] === '2208819517323216748' && $r['mydata_document_type'] === '11.4'
            && $r['items'][0]['description'] === 'ΑΑ4471' && $r['items'][0]['unit_value'] === '35');

        $a->refresh();
        $this->assertSame(['Ναι', 'ΠΙΣ Α 2'], [$a->TaxiReceipt_Issued, $a->ElorusTaxiNumber]);
        $this->assertSame(2, DB::table('myat_transfers_files')->where('TransferID', $a->TransferID)->count());
    }

    public function test_taxi_credit_note_is_issued_even_if_the_pdf_fails(): void
    {
        $a = $this->transfer(['TaxiReceipt' => '4471', 'TaxiReceiptAmount' => 35]);

        Livewire::test(ListTransfers::class)
            ->callTableBulkAction('taxiReceipts', [$a], data: ['draft' => true, 'price' => 'sale', 'pdf' => 'all', 'credit' => true])
            ->assertNotified('Partly done');

        $this->assertSame(['Ναι', 'ΠΙΣ Α 2'], [$a->fresh()->TaxiReceipt_Issued, $a->fresh()->ElorusTaxiNumber]);
    }

    public function test_one_invoice_per_lead_name_ignores_case_and_accents(): void
    {
        $a = $this->transfer(['LeadName' => 'Μαρία Παπά']);
        $b = $this->transfer(['LeadName' => 'ΜΑΡΙΑ ΠΑΠΑ ']);

        Livewire::test(ListTransfers::class)
            ->callTableBulkAction('invoicesPerLead', [$a, $b], data: ['date' => '2026-10-23', 'draft' => true]);

        Http::assertSentCount(1);
        $this->assertSame($a->fresh()->ElorusID, $b->fresh()->ElorusID);
    }

    public function test_get_invoice_pdf_and_charge_receipts(): void
    {
        $a = $this->transfer(['ElorusID' => '2208819500000000099', 'SalesInvoiceNum' => 'ΤΠΥ Α 99']);
        $none = $this->transfer();

        Livewire::test(ListTransfers::class)
            ->callTableBulkAction('getInvoicePdf', [$a, $none], data: ['pdf' => 'all'])
            ->assertNotified('Partly done');
        Http::assertSent(fn (Request $r): bool => $r->url() === 'https://api.elorus.com/v1.2/invoices/2208819500000000099/pdf/');
        $this->assertSame('ΤΠΥ Α 99.pdf', DB::table('myat_transfers_files')->value('Flnm'));

        $charge = TransferCharge::query()->create(['TransferID' => $a->TransferID, 'TransferCharge' => 'Extra stop', 'ChargeValue' => 15, 'PaymentVia' => 'Cash']);
        Livewire::test(ChargesRelationManager::class, ['ownerRecord' => $a, 'pageClass' => EditTransfer::class])
            ->callTableBulkAction('issueReceipt', [$charge], data: ['draft' => true]);
        $charge->refresh();
        $this->assertSame([1, 'ΤΠΥ Α 1'], [(int) $charge->Invoiced, $charge->InvoiceNum]);
    }

    public function test_already_invoiced_transfers_are_skipped_unless_ticked(): void
    {
        $done = $this->transfer(['InvoicedIssued' => 'Ναι', 'ElorusID' => '1', 'SalesInvoiceNum' => 'ΤΠΥ Α 7']);
        $open = $this->transfer();

        Livewire::test(ListTransfers::class)
            ->callTableBulkAction('receipts', [$done, $open], data: ['draft' => true, 'price' => 'sale', 'pdf' => 'no'])
            ->assertNotified('Partly done');
        Http::assertSentCount(1);
        $this->assertSame('ΤΠΥ Α 7', $done->fresh()->SalesInvoiceNum);

        Livewire::test(ListTransfers::class)
            ->callTableBulkAction('receipts', [$done], data: ['draft' => true, 'price' => 'sale', 'pdf' => 'no', 'again' => true]);
        Http::assertSentCount(2);
        $this->assertSame('ΤΠΥ Α 2 ΤΠΥ Α 7', $done->fresh()->SalesInvoiceNum);
    }

    public function test_nothing_is_sent_while_elorus_is_switched_off(): void
    {
        config(['elorus.enabled' => false]);
        $a = $this->transfer();

        Livewire::test(ListTransfers::class)
            ->callTableBulkAction('invoiceLines', [$a], data: ['date' => '2026-10-23', 'draft' => true])
            ->assertNotified('Nothing was created in Elorus');

        Http::assertNothingSent();
        $this->assertNull($a->fresh()->InvoicedIssued === 'Ναι' ? 'issued' : null);
    }
}
