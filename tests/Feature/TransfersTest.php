<?php

namespace Tests\Feature;

use App\Filament\Resources\Transfers\Pages\CreateTransfer;
use App\Filament\Resources\Transfers\Pages\EditTransfer;
use App\Filament\Resources\Transfers\Pages\ListTransfers;
use App\Filament\Resources\Transfers\RelationManagers\FilesRelationManager;
use App\Filament\Resources\Transfers\RelationManagers\NotesRelationManager;
use App\Filament\Resources\Transfers\RelationManagers\PriceLinesRelationManager;
use App\Models\MyatTransfersFile;
use App\Models\Transfer;
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
            'price_list', 'counterparties', 'vehicles', 'tours', 'optionvalues', 'status_values', 'touro_noti',
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
            'OpenExpensesFlag', 'OpenCharges', 'OperationalIssues', 'FinancialIssues', 'TransferID', 'customer.SupplierName',
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
        config(['transfers.lessee_is_lead_for_customers' => [$this->customerId]]);

        // Add page: the lead name is copied to the lessee for the configured customers.
        Livewire::test(CreateTransfer::class)
            ->fillForm(['cID_Customer' => $this->customerId, 'LesseeName' => ''])
            ->fillForm(['LeadName' => 'Maria Papadopoulou'])
            ->assertSchemaStateSet(['LesseeName' => 'Maria Papadopoulou']);

        config(['transfers.lessee_is_lead_for_customers' => []]);
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

    public function test_transfers_list_is_full_width_with_resizable_columns(): void
    {
        $this->actingAs($this->makeLegacyUser('Entry'));

        $this->get('/admin/transfers')
            ->assertOk()
            ->assertSee('fi-width-full', false)
            ->assertSee('myat-column-widths', false)
            ->assertSee('Reset column widths');

        // Other pages keep the normal width and no resize script.
        $this->get('/admin')->assertOk()->assertDontSee('myat-column-widths', false);
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

        // Type-ahead cells still only accept known values.
        $transfer = Transfer::query()->firstOrFail();
        $result = $page->instance()->updateTableColumnState('To', (string) $transfer->TransferID, 'Nowhere');
        $this->assertArrayHasKey('error', $result);

        $page->instance()->updateTableColumnState('To', (string) $transfer->TransferID, 'Place 7 with a fairly long descriptive name');
        $this->assertSame('Place 7 with a fairly long descriptive name', $transfer->fresh()->To);
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

    public function test_file_downloads_need_a_back_office_login(): void
    {
        $transfer = $this->transfer();
        $id = DB::table('myat_transfers_files')->insertGetId(['TransferID' => $transfer->TransferID, 'Flnm' => 'a.txt', 'Attachment' => 'secret'], 'ID');

        $this->get(route('transfer-files.download', $id))->assertRedirect();

        $this->actingAs($this->makeLegacyUser('Partner'))
            ->get(route('transfer-files.download', $id))
            ->assertForbidden();
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
}
