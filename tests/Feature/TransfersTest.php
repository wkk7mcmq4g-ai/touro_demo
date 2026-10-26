<?php

namespace Tests\Feature;

use App\Filament\Resources\Transfers\Pages\CreateTransfer;
use App\Filament\Resources\Transfers\Pages\EditTransfer;
use App\Filament\Resources\Transfers\TransferResource;
use App\Filament\Resources\Transfers\Pages\ListTransfers;
use App\Filament\Resources\Transfers\RelationManagers\ChargesRelationManager;
use App\Filament\Resources\Transfers\RelationManagers\CommsRelationManager;
use App\Filament\Resources\Transfers\RelationManagers\ExpensesRelationManager;
use App\Filament\Resources\Transfers\RelationManagers\FilesRelationManager;
use App\Filament\Resources\Transfers\RelationManagers\NotesRelationManager;
use App\Filament\Resources\Transfers\RelationManagers\PriceLinesRelationManager;
use App\Filament\Resources\Transfers\RelationManagers\TaxiFilesRelationManager;
use App\Filament\Resources\Transfers\Tables\TransfersTable;
use App\Filament\Resources\Transfers\Widgets\TransferTotals;
use App\Models\MyatTransfersFile;
use App\Models\Transfer;
use App\Models\TransferCharge;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class TransfersTest extends TestCase
{
    private int $customerId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->emptyTables([
            'transfers', 'transfer_notes', 'transfer_charges', 'myat_transfers_files', 'price_list_transfers',
            'price_list', 'counterparties', 'vehicles', 'tours', 'optionvalues', 'status_values', 'touro_noti', 'transfers_comms',
        ]);

        $this->customerId = DB::table('counterparties')->insertGetId([
            'CounterShortName' => 'GYG', 'SupplierName' => 'GetYourGuide', 'IsSupplier' => 1,
            'LesseName' => 'GetYourGuide Deutschland GmbH', 'InvoiceType' => 'Τιμολόγιο', 'PaymentType' => 'GYG',
        ], 'CID');

        DB::table('tours')->insert([
            ['TourName' => 'Athens Airport', 'TourDescription' => '', 'DestinationType' => 'Airport'],
            ['TourName' => 'Hotel Grande Bretagne', 'TourDescription' => '', 'DestinationType' => 'Hotel'],
        ]);
        DB::table('optionvalues')->insert([
            ['Option' => 'TransferType', 'Value' => 'Arrival', 'Color' => '#b3e5fc'],
            ['Option' => 'CarType', 'Value' => 'VAN', 'Color' => null],
            ['Option' => 'Status', 'Value' => 'Pending', 'Color' => 'Orange'],
            ['Option' => 'TransferFile', 'Value' => 'Voucher', 'Color' => null],
        ]);
        DB::table('vehicles')->insert(['VehicleName' => 'VITO 2022', 'VehicleLicence' => 'IKE']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function transfer(array $attributes = []): Transfer
    {
        $id = DB::table('transfers')->insertGetId(array_merge([
            'ImportDate' => Carbon::today()->subDays(3),
            'cID_Customer' => $this->customerId,
            'TransferType' => 'Arrival',
            'TransferDate' => Carbon::today(),
            'TransferTime' => '10:00',
            'From' => 'Athens Airport',
            'To' => 'Hotel Grande Bretagne',
            'CarTypeID' => 'VAN',
            'Status' => 'Pending',
            'LesseeName' => 'Someone',
            'TobeInvoiced' => 'Όχι',
            'PaymentType' => 'Cash',
        ], $attributes), 'TransferID');

        return Transfer::findOrFail($id);
    }

    public function test_list_tabs_split_today_tomorrow_and_later(): void
    {
        $this->actingAs($this->makeLegacyUser('Entry'));

        $today = $this->transfer();
        $tomorrow = $this->transfer(['TransferDate' => Carbon::tomorrow()]);
        $nextWeek = $this->transfer(['TransferDate' => Carbon::today()->addDays(7)]);

        Livewire::test(ListTransfers::class)
            ->assertCanSeeTableRecords([$today])
            ->assertCanNotSeeTableRecords([$tomorrow, $nextWeek])
            ->set('activeTab', 'tomorrow')
            ->assertCanSeeTableRecords([$tomorrow])
            ->assertCanNotSeeTableRecords([$today])
            ->set('activeTab', 'next5')
            ->assertCanSeeTableRecords([$today, $tomorrow])
            ->assertCanNotSeeTableRecords([$nextWeek])
            ->set('activeTab', 'all')
            ->assertCanSeeTableRecords([$today, $tomorrow, $nextWeek]);
    }

    public function test_list_has_every_column_of_the_phprunner_list(): void
    {
        $this->actingAs($this->makeLegacyUser('Entry'));

        $phprunnerColumns = [
            'flags', /* Exp. / Chg. / Ops / Fin. as badges */ 'TransferID', 'customer.SupplierName',
            'SupplierCode', 'Pax', 'TransferType', 'TransferDate', 'TransferTime', 'From', 'To', 'Flight_Ship_Num_From', 'OLP',
            'TicketsRequested', 'AssistantStatus', 'Assistant_Price', 'CarTypeID', 'CoachCompanyID', 'Notes', 'LesseeName',
            'LeadName', 'LeadPhone', 'PassengerName', 'Status', 'PlatformPrice', 'Assets', 'Liabilities', 'CashRegister',
            'Income', 'PurchasePrice', 'SalePrice', 'PricePaid', 'PriceRcvd', 'Expense', 'TobeInvoiced', 'InvoicedIssued',
            'InvoicedRcvd', 'VPOS', 'SalesInvoiceNum', 'PurchaseInvoiceNum', 'DriverNotes', 'PaymentOnHoldFlag', 'Assistant',
            'Platform', 'PaymentType', 'Voucher_StartDt', 'Voucher_StartTime', 'Voucher_EndDt', 'Voucher_EndTime',
            'ImportDate', 'ExtraIncome', 'DriverReceiptType', 'TicketsValue', 'FLNM', 'TaxiReceipt_Issued',
        ];

        $page = Livewire::test(ListTransfers::class);

        foreach ($phprunnerColumns as $column) {
            $page->assertTableColumnVisible($column);
        }
    }

    public function test_invoice_received_filter_treats_empty_as_no_like_phprunner(): void
    {
        $this->actingAs($this->makeLegacyUser('Entry'));
        $empty = $this->transfer(['InvoicedRcvd' => '']);
        $received = $this->transfer(['InvoicedRcvd' => 'Έχω λάβει']);

        Livewire::test(ListTransfers::class)
            ->filterTable('InvoicedRcvd_Flag', ['Όχι'])
            ->assertCanSeeTableRecords([$empty])
            ->assertCanNotSeeTableRecords([$received]);
    }

    public function test_all_tab_starts_at_2025_like_phprunner(): void
    {
        $this->actingAs($this->makeLegacyUser('Entry'));
        $old = $this->transfer(['TransferDate' => '2024-12-30', 'ImportDate' => '2024-12-01']);
        $recent = $this->transfer(['TransferDate' => '2025-01-02', 'ImportDate' => '2024-12-01']);

        Livewire::test(ListTransfers::class)
            ->set('activeTab', 'all')
            ->assertCanSeeTableRecords([$recent])
            ->assertCanNotSeeTableRecords([$old]);
    }

    public function test_edit_in_list_saves_through_the_same_rules(): void
    {
        $user = $this->makeLegacyUser('Entry');
        $this->actingAs($user);
        $transfer = $this->transfer(['PurchasePrice' => 40]);

        $page = Livewire::test(ListTransfers::class)
            ->callAction('toggleInlineEditing')
            ->assertSet('inlineEditing', true);

        // A cell change is saved at once, stamped with the user, like the edit page.
        $page->call('updateTableColumnState', 'SalePrice', (string) $transfer->TransferID, '99.5');
        $this->assertSame(99.5, $transfer->fresh()->SalePrice);
        $this->assertSame($user->username, $transfer->fresh()->ModifiedBy);

        // Own vehicle in the Coach cell: purchase price reset after saving, as in PHPRunner.
        $page->call('updateTableColumnState', 'CoachCompanyID', (string) $transfer->TransferID, 'VITO 2022');
        $this->assertSame(0.0, $transfer->fresh()->PurchasePrice);

        // Moving it into the past is refused with PHPRunner's message.
        $result = $page->instance()->updateTableColumnState('TransferDate', (string) $transfer->TransferID, Carbon::today()->subDays(3)->format('Y-m-d'));
        $this->assertStringContainsString('παλαιότερη της σημερινής', $result['error'] ?? '');
        $this->assertTrue($transfer->fresh()->TransferDate->isToday());
    }

    public function test_form_events_from_the_phprunner_add_and_edit_pages(): void
    {
        $this->actingAs($this->makeLegacyUser('Entry'));
        DB::table('counterparties')->where('CID', $this->customerId)->update(['LeadName' => 1]);

        // Add page: picking the customer fills in its lessee (PHPRunner autofill); not on edit.
        DB::table('counterparties')->where('CID', $this->customerId)->update(['LesseName' => 'ACME Leasing']);
        Livewire::test(CreateTransfer::class)
            ->fillForm(['cID_Customer' => $this->customerId])
            ->assertSchemaStateSet(['LesseeName' => 'ACME Leasing']);
        $kept = $this->transfer(['LesseeName' => 'Kept']);
        Livewire::test(EditTransfer::class, ['record' => $kept->getKey()])
            ->fillForm(['cID_Customer' => $this->customerId])
            ->assertSchemaStateSet(['LesseeName' => 'Kept']);
        DB::table('counterparties')->where('CID', $this->customerId)->update(['LesseName' => '']);

        // Add page: the lead name is copied to the lessee for the configured customers.
        Livewire::test(CreateTransfer::class)
            ->fillForm(['cID_Customer' => $this->customerId, 'LesseeName' => ''])
            ->fillForm(['LeadName' => 'Maria Papadopoulou'])
            ->assertSchemaStateSet(['LesseeName' => 'Maria Papadopoulou']);

        DB::table('counterparties')->where('CID', $this->customerId)->update(['LeadName' => 0]);
        Livewire::test(CreateTransfer::class)
            ->fillForm(['cID_Customer' => $this->customerId, 'LesseeName' => ''])
            ->fillForm(['LeadName' => 'Maria Papadopoulou'])
            ->assertSchemaStateSet(['LesseeName' => '']);

        // Platform price only with a platform booking; expenses are never typed on the form.
        $transfer = $this->transfer(['Platform' => 'Όχι', 'PlatformPrice' => 12, 'Expense' => 7]);
        Livewire::test(EditTransfer::class, ['record' => $transfer->getKey()])
            ->assertFormFieldDisabled('PlatformPrice')
            ->assertFormFieldDisabled('Expense')
            ->fillForm(['Platform' => 'Ναι'])
            ->assertFormFieldEnabled('PlatformPrice')
            ->fillForm(['PlatformPrice' => 20, 'Notes' => 'x'])
            ->call('save')
            ->assertHasNoFormErrors();

        $transfer->refresh();
        $this->assertSame('Ναι', $transfer->Platform);
        $this->assertEquals(20, $transfer->PlatformPrice);
        $this->assertEquals(7, $transfer->Expense);
    }

    public function test_save_stays_and_save_and_back_returns_to_the_list(): void
    {
        $this->actingAs($this->makeLegacyUser('Entry'));
        $transfer = $this->transfer(['Notes' => 'a']);
        $list = TransferResource::getUrl('index');

        Livewire::test(EditTransfer::class, ['record' => $transfer->getKey()])
            ->assertSee('Αποθήκευση &amp; πίσω', false)
            ->fillForm(['Notes' => 'b'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNoRedirect();
        $this->assertSame('b', $transfer->fresh()->Notes);

        // Back to the list the user came from, with its tab and search.
        Livewire::test(EditTransfer::class, ['record' => $transfer->getKey()])
            ->set('previousUrl', $list.'?tab=today&search=x')
            ->fillForm(['Notes' => 'c'])
            ->call('saveAndBack')
            ->assertRedirect($list.'?tab=today&search=x');
        $this->assertSame('c', $transfer->fresh()->Notes);

        // Came from elsewhere (e.g. the create page): the plain list.
        Livewire::test(EditTransfer::class, ['record' => $transfer->getKey()])
            ->set('previousUrl', TransferResource::getUrl('create'))
            ->call('saveAndBack')
            ->assertRedirect($list);

        // A form error keeps the user on the form.
        Livewire::test(EditTransfer::class, ['record' => $transfer->getKey()])
            ->fillForm(['TransferDate' => null])
            ->call('saveAndBack')
            ->assertHasFormErrors(['TransferDate'])
            ->assertNoRedirect();
    }

    public function test_copy_transfer_button(): void
    {
        $this->actingAs($this->makeLegacyUser('Entry'));
        $transfer = $this->transfer(['LeadName' => 'Maria', 'Notes' => 'Child seat']);

        Livewire::test(ListTransfers::class)
            ->callTableAction('copy', $transfer)
            ->assertRedirect();

        $copy = Transfer::query()->whereKeyNot($transfer->getKey())->latest('TransferID')->firstOrFail();
        $this->assertSame(['Maria', 'Child seat'], [$copy->LeadName, $copy->Notes]);
    }

    public function test_closing_sales_and_purchases_on_selected_transfers(): void
    {
        $user = $this->makeLegacyUser('Entry');
        $this->actingAs($user);
        $a = $this->transfer(['SalePrice' => 80, 'PriceRcvd' => 0, 'PurchasePrice' => 50, 'PricePaid' => 0]);
        $b = $this->transfer(['SalePrice' => 60, 'PriceRcvd' => 0, 'PurchasePrice' => 40, 'PricePaid' => 0]);
        $untouched = $this->transfer(['SalePrice' => 99, 'PriceRcvd' => 0]);

        // The user has to type their own username, as in PHPRunner.
        Livewire::test(ListTransfers::class)
            ->callTableBulkAction('closeSales', [$a, $b], data: ['usr' => 'someone-else'])
            ->assertHasTableBulkActionErrors(['usr']);
        $this->assertEquals(0, $a->fresh()->PriceRcvd);

        Livewire::test(ListTransfers::class)
            ->callTableBulkAction('closeSales', [$a, $b], data: ['usr' => $user->username])
            ->assertHasNoTableBulkActionErrors();
        $this->assertEquals([80, 60, 0], [$a->fresh()->PriceRcvd, $b->fresh()->PriceRcvd, $untouched->fresh()->PriceRcvd]);

        Livewire::test(ListTransfers::class)
            ->callTableBulkAction('closePurchases', [$a], data: ['usr' => $user->username, 'invnum' => 'ΤΔΑ 123'])
            ->assertHasNoTableBulkActionErrors();
        $a->refresh();
        $this->assertEquals(50, $a->PricePaid);
        $this->assertSame(['ΤΔΑ 123', 'Έχω λάβει'], [$a->PurchaseInvoiceNum, $a->InvoicedRcvd]);
    }

    public function test_incoming_payment_button(): void
    {
        config(['transfers.incoming_payment' => true]); // hidden in the Demo; the action itself stays tested
        $user = $this->makeLegacyUser('Admin');
        $this->actingAs($user);

        Livewire::test(ListTransfers::class)
            ->callAction('incomingPayment', ['customer' => $this->customerId, 'amount' => 150.5, 'date' => '2026-10-20'])
            ->assertHasNoActionErrors();

        $payment = DB::table('PaymentsReceived')->where('CID_Customer', $this->customerId)->first();
        $this->assertEquals(150.5, $payment->PaymentAmount);
        $this->assertSame(['2026-10-20', $user->username], [$payment->PaymentDate, $payment->CreatedBy]);
    }

    public function test_issues_tab_and_period_filter(): void
    {
        $this->actingAs($this->makeLegacyUser('Entry'));
        $tbd = $this->transfer(['TransferDate' => Carbon::today()->addDays(2), 'LeadPhone' => 'TBD']);
        $flagged = $this->transfer(['TransferDate' => Carbon::today()->addDays(3), 'OperationalIssues' => 1]);
        $fine = $this->transfer(['TransferDate' => Carbon::today()->addDays(2)]);
        $cancelled = $this->transfer(['TransferDate' => Carbon::today()->addDays(2), 'LeadPhone' => 'TBD', 'Status' => 'Cancelled']);
        $later = $this->transfer(['TransferDate' => Carbon::today()->addDays(20), 'LeadPhone' => 'TBD']);

        $page = Livewire::test(ListTransfers::class)->set('activeTab', 'issues');
        $page->assertCanSeeTableRecords([$tbd, $flagged])->assertCanNotSeeTableRecords([$fine, $cancelled, $later]);

        $lastMonth = $this->transfer(['TransferDate' => Carbon::today()->subMonthNoOverflow()->startOfMonth()->addDays(3), 'ImportDate' => Carbon::today()->subYear()]);
        Livewire::test(ListTransfers::class)->set('activeTab', 'all')
            ->filterTable('period', 'last_month')
            ->assertCanSeeTableRecords([$lastMonth])
            ->assertCanNotSeeTableRecords([$fine]);

        // Every select filter is multi-select: several periods match any of them.
        $thisMonth = $this->transfer(['TransferDate' => Carbon::today()->startOfMonth(), 'ImportDate' => Carbon::today()->subYear()]);
        Livewire::test(ListTransfers::class)->set('activeTab', 'all')
            ->filterTable('period', ['last_month', 'this_month'])
            ->assertCanSeeTableRecords([$lastMonth, $thisMonth])
            ->assertCanNotSeeTableRecords([$this->transfer(['TransferDate' => Carbon::today()->subYears(2)])]);
    }

    public function test_sms_row_button_for_admins(): void
    {
        $transfer = $this->transfer(['LeadName' => 'Ms Sms', 'DriverNotes' => 'Gate B']);

        $this->actingAs($this->makeLegacyUser('Entry'));
        Livewire::test(ListTransfers::class)->set('activeTab', 'all')->assertTableActionHidden('sms', $transfer);

        $this->actingAs($this->makeLegacyUser('Admin'));
        Livewire::test(ListTransfers::class)->set('activeTab', 'all')
            ->assertTableActionVisible('sms', $transfer)
            ->mountTableAction('sms', $transfer)
            ->assertOk();

        $text = $transfer->smsForDriver()['text'];
        $this->assertStringContainsString('*Όνομα επιβάτη:* Ms Sms', $text);
        $this->assertStringContainsString('*Ημερομηνία:* '.$transfer->TransferDate->format('d/m/Y'), $text);
    }

    public function test_copy_notes_to_driver_notes(): void
    {
        $this->actingAs($this->makeLegacyUser('Entry'));
        $transfer = $this->transfer(['Notes' => 'Meet at gate B', 'DriverNotes' => '']);

        Livewire::test(EditTransfer::class, ['record' => $transfer->getKey()])
            ->callAction(TestAction::make('copyNotes')->schemaComponent('DriverNotes'))
            ->assertSchemaStateSet(['DriverNotes' => 'Meet at gate B']);
    }

    public function test_transfers_list_is_full_width_with_resizable_columns(): void
    {
        $this->actingAs($this->makeLegacyUser('Entry'));

        $this->get('/admin/transfers')
            ->assertOk()
            ->assertSee('fi-width-full', false)
            ->assertSee('myat-column-widths', false)
            ->assertSee('Reset column widths');

        // Every table can be resized and filtered from its header now.
        $this->get('/admin')->assertOk()->assertSee('myat-column-widths', false);
    }

    public function test_edit_in_list_stays_light_with_long_lookups(): void
    {
        $this->actingAs($this->makeLegacyUser('Entry'));

        // Real-life sized lookups: many places, many transfers on screen.
        DB::table('tours')->insert(array_map(fn (int $i): array => [
            'TourName' => "Place {$i} with a fairly long descriptive name", 'TourDescription' => '', 'DestinationType' => 'Hotel',
        ], range(1, 1500)));
        foreach (range(1, 60) as $i) {
            $this->transfer();
        }

        $page = Livewire::test(ListTransfers::class)
            ->set('tableRecordsPerPage', 200)
            ->callAction('toggleInlineEditing');

        // Editing pages are capped at 50 rows.
        $this->assertLessThanOrEqual(50, $page->instance()->getTableRecords()->count());

        // Each place is sent once (shared suggestion list), not once per row and column.
        $html = $page->html();
        $this->assertLessThan(12 * 1024 * 1024, strlen($html), 'Edit-in-list page is too large');
        $this->assertSame(2, substr_count($html, 'Place 1499 with a fairly long descriptive name'));

        // Places take free text (PHPRunner "allow free input"); the coach must be in the list.
        $transfer = Transfer::query()->firstOrFail();
        $page->instance()->updateTableColumnState('To', (string) $transfer->TransferID, 'Villa Kalimera, Sounio');
        $this->assertSame('Villa Kalimera, Sounio', $transfer->fresh()->To);

        $result = $page->instance()->updateTableColumnState('CoachCompanyID', (string) $transfer->TransferID, 'Nobody');
        $this->assertArrayHasKey('error', $result);
        $this->assertNotSame('Nobody', $transfer->fresh()->CoachCompanyID);
    }

    public function test_only_the_configured_columns_are_editable_in_the_list(): void
    {
        $this->actingAs($this->makeLegacyUser('Entry'));
        $transfer = $this->transfer();

        $page = Livewire::test(ListTransfers::class)->callAction('toggleInlineEditing');

        // TicketsRequested is shown but not in PHPRunner's inline-edit list.
        $result = $page->instance()->updateTableColumnState('TicketsRequested', (string) $transfer->TransferID, 'Contacted');
        $this->assertArrayHasKey('error', $result);
    }

    public function test_list_flags_fields_still_marked_tbd(): void
    {
        $this->actingAs($this->makeLegacyUser('Entry'));
        $transfer = $this->transfer(['Flight_Ship_Num_From' => 'TBD', 'Pax' => 'TBD 4']);

        $this->assertSame(['Pax', 'Flight/ship'], $transfer->placeholderFields());

        // Shown as a tooltip on the warning icon next to the transfer number.
        Livewire::test(ListTransfers::class)
            ->assertSee('Still to be confirmed: Pax, Flight');
    }

    public function test_creating_a_transfer_works_like_phprunner(): void
    {
        $user = $this->makeLegacyUser('Entry');
        $this->actingAs($user);

        Livewire::test(CreateTransfer::class)
            ->fillForm([
                'cID_Customer' => $this->customerId,
                'TransferType' => 'Arrival',
                'ImportDate' => Carbon::today()->format('Y-m-d'),
                'TransferDate' => Carbon::tomorrow()->format('Y-m-d'),
                'TransferTime' => '07:40',
                'From' => 'Athens Airport',
                'To' => 'Hotel Grande Bretagne',
                'CarTypeID' => 'VAN',
                'Status' => 'Pending',
                'LeadName' => 'Ms Example',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $transfer = Transfer::query()->latest('TransferID')->firstOrFail();

        $this->assertSame($user->username, $transfer->CreatedBy);
        $this->assertNotNull($transfer->CreatedTimestamp);
        // Left empty in the form, filled by the database trigger from the customer.
        $this->assertSame('GetYourGuide Deutschland GmbH', $transfer->LesseeName);
        $this->assertSame('Τιμολόγιο', $transfer->TobeInvoiced);
        $this->assertSame('GYG', $transfer->PaymentType);
    }

    public function test_saving_never_writes_columns_the_database_calculates(): void
    {
        // In strict mode MariaDB rejects writes to GENERATED columns instead of ignoring them.
        config(['database.connections.mariadb.strict' => true]);
        DB::purge('mariadb');

        $this->actingAs($this->makeLegacyUser('Entry'));
        $transfer = $this->transfer();

        Livewire::test(CreateTransfer::class)
            ->fillForm([
                'cID_Customer' => $this->customerId,
                'TransferType' => 'Arrival',
                'ImportDate' => Carbon::today()->format('Y-m-d'),
                'TransferDate' => Carbon::tomorrow()->format('Y-m-d'),
                'TransferTime' => '07:40',
                'From' => 'Athens Airport',
                'CarTypeID' => 'VAN',
                'Status' => 'Pending',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        Livewire::test(EditTransfer::class, ['record' => $transfer->getKey()])
            ->fillForm(['Status' => 'Completed', 'SalePrice' => 80, 'PriceRcvd' => 50])
            ->call('save')
            ->assertHasNoFormErrors();

        // Calculated by MariaDB: still correct after saving through the app.
        $this->assertSame(30.0, (float) $transfer->fresh()->Assets);

        // Even if code sets a calculated column, the model leaves it to the database.
        $loaded = Transfer::query()->withoutBlobs()->findOrFail($transfer->TransferID);
        $loaded->fill(['OperationalIssuesFlag' => 'Yes', 'Assets' => 999, 'Notes' => 'checked']);
        $loaded->save();
        $this->assertSame('No', $transfer->fresh()->OperationalIssuesFlag);
        $this->assertSame(30.0, (float) $transfer->fresh()->Assets);
        $this->assertSame('10:00:00', (string) DB::table('transfers')->where('TransferID', $transfer->TransferID)->value('TransferTime_sort'));
    }

    public function test_new_transfers_cannot_be_older_than_seven_days(): void
    {
        $this->actingAs($this->makeLegacyUser('Entry'));

        Livewire::test(CreateTransfer::class)
            ->fillForm([
                'cID_Customer' => $this->customerId,
                'TransferType' => 'Arrival',
                'ImportDate' => Carbon::today()->subDays(20)->format('Y-m-d'),
                'TransferDate' => Carbon::today()->subDays(10)->format('Y-m-d'),
                'TransferTime' => '07:40',
                'From' => 'Athens Airport',
                'CarTypeID' => 'VAN',
                'Status' => 'Pending',
            ])
            ->call('create')
            ->assertHasFormErrors(['TransferDate']);

        $this->assertSame(0, Transfer::query()->count());
    }

    public function test_totals_above_the_list_follow_what_the_list_shows(): void
    {
        $this->actingAs($this->makeLegacyUser('Admin'));
        $this->transfer(['SalePrice' => 100, 'PurchasePrice' => 60, 'Status' => 'Completed']);
        $this->transfer(['SalePrice' => 40, 'PurchasePrice' => 10, 'TransferDate' => Carbon::today()->addDay()]);

        Livewire::test(TransferTotals::class, ['activeTab' => 'all'])
            ->assertSeeInOrder(['Transfers', '2', 'Πωλήσεις', '140,00', 'Αγορές', '70,00', 'Απαιτήσεις', '100,00', 'Υποχρεώσεις', '60,00']);
        Livewire::test(TransferTotals::class, ['activeTab' => 'tomorrow'])
            ->assertSeeInOrder(['Transfers', '1', 'Πωλήσεις', '40,00']);

        // Office users without the finance permission see the count only.
        $this->actingAs($this->makeLegacyUser('Entry'));
        Livewire::test(TransferTotals::class, ['activeTab' => 'all'])
            ->assertSee('Transfers')->assertDontSee('Πωλήσεις');
    }

    public function test_the_form_marks_the_tab_with_an_error_and_accepts_both_platform_spellings(): void
    {
        $this->actingAs($this->makeLegacyUser('Entry'));
        // The insert trigger writes "Ναί", the update trigger "Ναι": both must open and save.
        $transfer = $this->transfer(['Platform' => 'Ναί', 'PlatformPrice' => 12]);

        Livewire::test(EditTransfer::class, ['record' => $transfer->getKey()])
            ->assertFormSet(['Platform' => 'Ναι'])
            ->call('save')
            ->assertHasNoFormErrors();

        $page = Livewire::test(EditTransfer::class, ['record' => $transfer->getKey()])
            ->fillForm(['ImportDate' => null])
            ->call('save')
            ->assertHasFormErrors(['ImportDate' => 'required']);
        preg_match_all('/<button[^>]*fi-tabs-item.*?<\/button>/s', $page->html(), $tabs);
        $labels = array_map(fn (string $tab): string => trim(preg_replace('/\s+/', ' ', strip_tags($tab))), $tabs[0]);
        $this->assertContains('Transfer Details !', $labels);
        $this->assertContains('Financials', $labels);
    }

    public function test_editing_keeps_old_dates_but_cannot_move_a_transfer_into_the_past(): void
    {
        $user = $this->makeLegacyUser('Entry');
        $this->actingAs($user);
        $transfer = $this->transfer(['ImportDate' => Carbon::today()->subDays(30), 'TransferDate' => Carbon::today()->subDays(10)]);

        // Unchanged past date: saving other fields is fine.
        Livewire::test(EditTransfer::class, ['record' => $transfer->getKey()])
            ->fillForm(['Notes' => 'Corrected after the trip'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Corrected after the trip', $transfer->fresh()->Notes);
        $this->assertSame($user->username, $transfer->fresh()->ModifiedBy);

        // Moving it to another past date is refused.
        Livewire::test(EditTransfer::class, ['record' => $transfer->getKey()])
            ->fillForm(['TransferDate' => Carbon::today()->subDays(2)->format('Y-m-d')])
            ->call('save')
            ->assertHasFormErrors(['TransferDate']);
    }

    public function test_own_vehicle_resets_purchase_price_after_saving(): void
    {
        $this->actingAs($this->makeLegacyUser('Entry'));
        $transfer = $this->transfer(['PurchasePrice' => 45, 'PricePaid' => 45]);

        Livewire::test(EditTransfer::class, ['record' => $transfer->getKey()])
            ->fillForm(['CoachCompanyID' => 'VITO 2022'])
            ->call('save')
            ->assertHasNoFormErrors();

        $transfer->refresh();
        $this->assertSame(0.0, $transfer->PurchasePrice);
        $this->assertSame(0.0, $transfer->PricePaid);
        $this->assertSame('Δε θα λάβω', $transfer->InvoicedRcvd);
    }

    public function test_a_new_note_notifies_every_admin_in_the_phprunner_bell(): void
    {
        $admin = $this->makeLegacyUser('Admin');
        $this->actingAs($this->makeLegacyUser('Entry'));
        $transfer = $this->transfer();

        Livewire::test(NotesRelationManager::class, ['ownerRecord' => $transfer, 'pageClass' => EditTransfer::class])
            ->callAction(TestAction::make('create')->table(), data: [
                'Note' => 'Flight delayed 2h',
                'Category' => 'Operational',
            ])
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('transfer_notes', ['TransferID' => $transfer->TransferID, 'Note' => 'Flight delayed 2h']);
        $this->assertDatabaseHas('touro_noti', [
            'user' => $admin->username,
            'title' => 'New issue raised',
        ]);
        $this->assertStringContainsString(
            "New Operational issue on case {$transfer->TransferID}",
            DB::table('touro_noti')->where('user', $admin->username)->value('message'),
        );
    }

    public function test_price_lines_set_the_sale_price(): void
    {
        $this->actingAs($this->makeLegacyUser('Entry'));
        $transfer = $this->transfer(['SalePrice' => 10]);
        $productId = DB::table('price_list')->insertGetId([
            'cid_Customer' => $this->customerId, 'Product' => 'ATH-CENTER', 'Product2' => 'Airport → Centre',
            'CarType' => 'VAN', 'PriceValue' => 65, 'Category' => 'Transfer',
        ]);

        Livewire::test(PriceLinesRelationManager::class, ['ownerRecord' => $transfer, 'pageClass' => EditTransfer::class])
            ->callAction(TestAction::make('create')->table(), data: [
                'Category' => 'Transfer',
                'ProductCarType' => 'VAN',
                'ProductID' => (string) $productId,
                'PriceValue' => 65,
            ])
            ->assertHasNoFormErrors();

        $this->assertSame(65.0, $transfer->fresh()->SalePrice);
    }

    public function test_files_are_stored_in_the_database_and_downloadable(): void
    {
        $this->actingAs($this->makeLegacyUser('Entry'));
        $transfer = $this->transfer();
        $pdf = UploadedFile::fake()->createWithContent('voucher-Αθήνα.pdf', "%PDF-1.4\n% test voucher\n");

        Livewire::test(FilesRelationManager::class, ['ownerRecord' => $transfer, 'pageClass' => EditTransfer::class])
            ->callAction(TestAction::make('create')->table(), data: [
                'upload' => $pdf,
                'Type' => DB::table('optionvalues')->where('Option', 'TransferFile')->value('OptionID'),
            ])
            ->assertHasNoFormErrors();

        $file = MyatTransfersFile::query()->where('TransferID', $transfer->TransferID)->firstOrFail();
        $this->assertSame('voucher-Αθήνα.pdf', $file->Flnm);
        $this->assertStringStartsWith('%PDF-1.4', $file->getRawOriginal('Attachment'));

        $this->get(route('transfer-files.download', $file->ID))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertSee('test voucher', false);
    }

    public function test_expenses_taxi_receipts_and_communications_tabs(): void
    {
        $this->actingAs($this->makeLegacyUser('Entry'));
        $transfer = $this->transfer();
        $rm = fn (string $class) => Livewire::test($class, ['ownerRecord' => $transfer, 'pageClass' => EditTransfer::class]);

        // Income and expense lines live in one table; each tab shows its own.
        DB::table('transfer_charges')->insert([
            ['TransferID' => $transfer->TransferID, 'TransferCharge' => 'Extra stop', 'ChargeValue' => 15, 'Expense' => 0],
        ]);
        $rm(ExpensesRelationManager::class)
            ->callAction(TestAction::make('create')->table(), data: ['Expense' => 12.5, 'ExpenseReason' => 'Parking', 'ExpensePaid' => 'Ναι'])
            ->assertHasNoFormErrors();
        $expense = TransferCharge::query()->where('Expense', 12.5)->firstOrFail();
        $income = TransferCharge::query()->where('ChargeValue', 15)->firstOrFail();
        $this->assertSame([$transfer->TransferID, 'Parking'], [$expense->TransferID, $expense->ExpenseReason]);
        $this->assertNotNull($expense->CreatedBy);
        // The open form shows the totals the triggers recalculated, without leaving the page.
        $page = Livewire::test(EditTransfer::class, ['record' => $transfer->getKey()])->assertFormSet(['Expense' => 12.5]);
        DB::table('transfer_charges')->insert(['TransferID' => $transfer->TransferID, 'TransferCharge' => 'Waiting', 'ChargeValue' => 5, 'Expense' => 0, 'PaymentStatus' => 'Paid']);
        $page->dispatch('transfer-prices-changed')->assertFormSet(['ExtraIncome' => (float) $transfer->fresh()->ExtraIncome]);
        $this->assertSame(5.0, (float) $transfer->fresh()->ExtraIncome);
        // Saving the form afterwards keeps the new total (it used to write the old one back).
        $page->call('save')->assertHasNoFormErrors();
        $this->assertSame(5.0, (float) $transfer->fresh()->ExtraIncome);
        $rm(ChargesRelationManager::class)
            ->callAction(TestAction::make('delete')->table(TransferCharge::query()->where('ChargeValue', 5)->firstOrFail()))
            ->assertDispatched('transfer-prices-changed');

        $rm(ExpensesRelationManager::class)->assertCanSeeTableRecords([$expense])->assertCanNotSeeTableRecords([$income]);
        $rm(ChargesRelationManager::class)->assertCanSeeTableRecords([$income])->assertCanNotSeeTableRecords([$expense]);

        // Taxi receipts are files of type 1363; like in PHPRunner, Entry users don't have that tab.
        $this->assertFalse(TaxiFilesRelationManager::canViewForRecord($transfer, EditTransfer::class));
        $this->actingAs($this->makeLegacyUser('Admin'));
        $rm(TaxiFilesRelationManager::class)
            ->callAction(TestAction::make('create')->table(), data: ['upload' => UploadedFile::fake()->createWithContent('receipt.jpg', 'jpeg')])
            ->assertHasNoFormErrors();
        $taxi = MyatTransfersFile::query()->withoutBlob()->where('Flnm', 'receipt.jpg')->firstOrFail();
        $this->assertSame(1363, (int) $taxi->Type);
        $other = MyatTransfersFile::query()->create(['TransferID' => $transfer->TransferID, 'Flnm' => 'contract.pdf', 'Attachment' => 'x', 'Type' => null]);
        $rm(TaxiFilesRelationManager::class)->assertCanSeeTableRecords([$taxi])->assertCanNotSeeTableRecords([$other]);

        // Communications log: stamped with who and when.
        $rm(CommsRelationManager::class)
            ->callAction(TestAction::make('create')->table(), data: ['Notes' => 'Called the hotel, pickup moved to 10:15'])
            ->assertHasNoFormErrors();
        $comm = $transfer->comms()->firstOrFail();
        $this->assertSame(auth()->user()->username, $comm->CreatedBy);
        $this->assertNotNull($comm->CreatedDate);
    }

    public function test_file_downloads_need_a_login_and_portal_users_only_get_their_own(): void
    {
        $transfer = $this->transfer();
        $id = DB::table('myat_transfers_files')->insertGetId(['TransferID' => $transfer->TransferID, 'Flnm' => 'a.txt', 'Attachment' => 'secret'], 'ID');

        $this->get(route('transfer-files.download', $id))->assertRedirect();

        // A partner whose customer is someone else.
        $this->actingAs($this->makeLegacyUser('Partner'))
            ->get(route('transfer-files.download', $id))
            ->assertNotFound();
    }

    public function test_svg_files_download_instead_of_opening(): void
    {
        $this->actingAs($this->makeLegacyUser('Admin'));
        $transfer = $this->transfer();
        $id = DB::table('myat_transfers_files')->insertGetId([
            'TransferID' => $transfer->TransferID,
            'Flnm' => 'x.svg',
            'Attachment' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
        ], 'ID');

        $response = $this->get(route('transfer-files.download', $id))->assertOk();

        $this->assertSame('application/octet-stream', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('attachment', $response->headers->get('Content-Disposition'));
    }

    public function test_quick_toggle_hides_and_shows_cancelled_transfers(): void
    {
        $this->actingAs($this->makeLegacyUser('Entry'));
        $live = $this->transfer(['TransferDate' => Carbon::today(), 'Status' => 'Pending', 'SalePrice' => 100]);
        $gone = $this->transfer(['TransferDate' => Carbon::today(), 'Status' => 'Cancelled', 'SalePrice' => 40]);

        $page = Livewire::test(ListTransfers::class)
            ->assertSeeHtml('myat-quick-toggle is-on')       // shown by default
            ->assertCanSeeTableRecords([$live, $gone]);
        $this->assertSame(1, $page->instance()->cancelledCount());

        $page->call('toggleCancelled')
            ->assertCanSeeTableRecords([$live])->assertCanNotSeeTableRecords([$gone])
            ->assertDontSeeHtml('myat-quick-toggle is-on');
        $this->assertTrue(session(ListTransfers::HIDE_CANCELLED));

        // the choice stays for the next visit, then switches back
        Livewire::test(ListTransfers::class)->assertCanNotSeeTableRecords([$gone])
            ->call('toggleCancelled')->assertCanSeeTableRecords([$live, $gone]);
    }

    public function test_open_items_are_coloured_badges_first_with_an_explanation_on_hover(): void
    {
        $this->actingAs($this->makeLegacyUser('Entry'));
        $busy = $this->transfer();
        DB::table('transfers')->where('TransferID', $busy->TransferID)->update(['OpenExpensesFlag' => 1, 'OpenCharges' => 2, 'OperationalIssues' => 1]);
        $quiet = $this->transfer();

        $flags = TransfersTable::flags($busy->fresh());
        $this->assertSame(['exp', 'chg', 'ops'], array_column($flags, 'key'));
        $this->assertStringContainsString('2 ανοιχτές πρόσθετες χρεώσεις', $flags[1]['tip']);
        $this->assertSame([], TransfersTable::flags($quiet));

        $page = Livewire::test(ListTransfers::class)
            ->assertSeeHtml('myat-flag myat-flag-chg')
            ->assertSeeHtml('x-tooltip.raw="Ops: 1 ανοιχτές λειτουργικές σημειώσεις')
            ->assertDontSeeHtml('myat-flag-fin');
        $this->assertSame('flags', $page->instance()->tableColumns[0]['name']);

        // a saved layout with the badges elsewhere still gets them first
        $columns = $page->instance()->tableColumns;
        $page->call('applyTableColumnManager', [...array_slice($columns, 1), $columns[0]], true);
        $this->assertSame('flags', Livewire::test(ListTransfers::class)->instance()->tableColumns[0]['name']);
    }

    public function test_date_shortcuts_menu_like_phprunner_filters(): void
    {
        $this->actingAs($this->makeLegacyUser('Entry'));
        $today = $this->transfer(['TransferDate' => Carbon::today()]);
        $tomorrow = $this->transfer(['TransferDate' => Carbon::tomorrow()]);
        $dayAfter = $this->transfer(['TransferDate' => Carbon::today()->addDays(2)]);
        $yesterday = $this->transfer(['TransferDate' => Carbon::yesterday()]);
        $issue = $this->transfer(['TransferDate' => Carbon::today()->addDays(3), 'TransferTime' => 'TBD']);

        $page = Livewire::test(ListTransfers::class)
            ->assertSee('Ημερομηνίες')->assertSee('Day After Tomorrow')->assertSee('Εκκρεμότητες 7 ημερών')
            ->call('applyPeriod', 'day_after')
            ->assertSet('activeTab', 'all')
            ->assertCanSeeTableRecords([$dayAfter])->assertCanNotSeeTableRecords([$today, $tomorrow, $yesterday]);
        $this->assertSame('day_after', $page->instance()->activePeriod());

        $page->call('applyPeriod', 'yesterday')->assertCanSeeTableRecords([$yesterday])->assertCanNotSeeTableRecords([$today]);
        $page->call('applyPeriod', 'next5')->assertCanSeeTableRecords([$today, $tomorrow, $dayAfter, $issue])->assertCanNotSeeTableRecords([$yesterday]);
        $page->call('applyPeriod', 'issues7')->assertCanSeeTableRecords([$issue])->assertCanNotSeeTableRecords([$dayAfter]);
        $page->call('applyPeriod', '')->assertCanSeeTableRecords([$today, $yesterday, $issue]);
        $this->assertNull($page->instance()->activePeriod());
    }

    public function test_own_vehicle_filter_next_to_coach(): void
    {
        $this->actingAs($this->makeLegacyUser('Entry'));
        DB::table('vehicles')->insert(['VehicleName' => 'OUR VITO', 'VehicleLicence' => 'IKE']);
        $own = $this->transfer(['CoachCompanyID' => 'OUR VITO']);
        $partner = $this->transfer(['CoachCompanyID' => 'PARTNER BUS']);
        $none = $this->transfer(['CoachCompanyID' => '']);

        $filters = array_keys(Livewire::test(ListTransfers::class)->instance()->getTable()->getFilters());
        $this->assertSame(array_search('CoachCompanyID', $filters) + 1, array_search('CoachKind', $filters));

        Livewire::test(ListTransfers::class)->set('activeTab', 'all')
            ->filterTable('CoachKind', ['own'])->assertCanSeeTableRecords([$own])->assertCanNotSeeTableRecords([$partner, $none])
            ->filterTable('CoachKind', ['partner'])->assertCanSeeTableRecords([$partner])->assertCanNotSeeTableRecords([$own, $none])
            ->filterTable('CoachKind', ['none'])->assertCanSeeTableRecords([$none])->assertCanNotSeeTableRecords([$own, $partner]);
    }
}
