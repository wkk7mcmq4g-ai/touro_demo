<?php

namespace Tests\Feature;

use App\Filament\Pages\PartnerJobs;
use App\Filament\Resources\Archive\Pages\ListArchivedTransfers;
use App\Filament\Resources\Charges\Pages\ListCharges;
use App\Filament\Resources\Communications\Pages\CreateCommunication;
use App\Filament\Resources\Payments\Pages\CreatePayment;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\ReviewLinks\Pages\CreateReviewLink;
use App\Filament\Resources\Tours\Pages\CreateTour;
use App\Filament\Resources\TransfersToSort\Pages\ListTransferToSorts;
use App\Models\PartnerJob;
use App\Models\PaymentReceived;
use App\Models\Tour;
use App\Models\TransferCharge;
use App\Models\TransfersLoad;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** The smaller Δρομολόγια screens: tours, review links, communications, charges, payments, archive, to sort, partner jobs. */
class OperationsScreensTest extends TestCase
{
    private int $customerId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->emptyTables(['transfers', 'tours', 'review_links', 'transfers_comms', 'transfer_charges', 'PaymentsReceived', 'paymentlinks',
            'transfers_archive', 'transfers_load', 'price_list_load', 'transfer_notes_load', 'transfer_notes', 'price_list_transfers', 'counterparties', 'optionvalues']);
        DB::table('optionvalues')->insert(['Option' => 'TransferType', 'Value' => 'Arrival']);
        $this->customerId = DB::table('counterparties')->insertGetId(['CounterShortName' => 'GYG', 'SupplierName' => 'GetYourGuide', 'IsSupplier' => 1, 'LesseName' => 'GYG GmbH'], 'CID');
        $this->actingAs($this->makeLegacyUser('Admin'));
    }

    private function transfer(array $attributes = []): int
    {
        return DB::table('transfers')->insertGetId(array_merge([
            'ImportDate' => Carbon::today(), 'cID_Customer' => $this->customerId, 'TransferType' => 'Arrival', 'TransferDate' => Carbon::today(),
            'TransferTime' => '10:00', 'From' => 'Athens Airport', 'To' => 'Hotel', 'CarTypeID' => 'VAN', 'Status' => 'Pending', 'LesseeName' => 'X',
        ], $attributes), 'TransferID');
    }

    public function test_every_screen_opens(): void
    {
        foreach (['tours', 'review-links', 'communications', 'charges', 'payments', 'archive', 'to-sort', 'partner-jobs'] as $slug) {
            $this->get("/admin/{$slug}")->assertOk();
        }
    }

    public function test_tours_and_review_links_are_created(): void
    {
        Livewire::test(CreateTour::class)
            ->fillForm(['TourName' => 'Delphi day trip', 'DestinationType' => 'Tour', 'TourDurationHours' => 10])
            ->call('create')->assertHasNoFormErrors();
        $tour = Tour::query()->where('TourName', 'Delphi day trip')->firstOrFail();
        $this->assertSame('', $tour->TourDescription);

        Livewire::test(CreateReviewLink::class)
            ->fillForm(['TransferType' => 'Arrival', 'From' => 'Athens Airport', 'ReviewLink' => 'https://g.page/r/review'])
            ->call('create')->assertHasNoFormErrors();
        $this->assertSame('', DB::table('review_links')->value('To'));
    }

    public function test_communications_are_logged_against_a_transfer(): void
    {
        $id = $this->transfer();
        Livewire::test(CreateCommunication::class)
            ->fillForm(['TransferID' => $id, 'Notes' => 'Called the hotel'])
            ->call('create')->assertHasNoFormErrors();
        $this->assertSame([$id, auth()->user()->username], [(int) DB::table('transfers_comms')->value('TransferID'), DB::table('transfers_comms')->value('CreatedBy')]);
    }

    public function test_charges_list_splits_income_and_expenses(): void
    {
        $id = $this->transfer();
        DB::table('transfer_charges')->insert([
            ['TransferID' => $id, 'TransferCharge' => 'Waiting time', 'ChargeValue' => 20, 'Expense' => 0],
            ['TransferID' => $id, 'TransferCharge' => 'Parking', 'ChargeValue' => 0, 'Expense' => 8],
        ]);
        [$income, $expense] = TransferCharge::query()->orderBy('ID')->get()->all();

        Livewire::test(ListCharges::class)->assertCanSeeTableRecords([$income])->assertCanNotSeeTableRecords([$expense])
            ->set('activeTab', 'expenses')->assertCanSeeTableRecords([$expense])->assertCanNotSeeTableRecords([$income]);
    }

    public function test_payments_are_recorded_and_allocated_by_the_database(): void
    {
        $first = $this->transfer(['Status' => 'Completed', 'TobeInvoiced' => 'Ναι', 'SalePrice' => 100, 'TransferDate' => Carbon::today()->subDays(5)]);
        Livewire::test(CreatePayment::class)
            ->fillForm(['CID_Customer' => $this->customerId, 'PaymentDate' => Carbon::today()->toDateString(), 'PaymentAmount' => 150])
            ->call('create')->assertHasNoFormErrors();
        $payment = PaymentReceived::query()->firstOrFail();
        $this->assertSame(auth()->user()->username, $payment->CreatedBy);
        $this->assertSame([$first], DB::table('paymentlinks')->where('PaymentID', $payment->PaymentID)->pluck('TransferID')->map(fn ($id) => (int) $id)->all());
        $this->assertEquals(50, $payment->Remaining);

        // A later completed transfer takes the rest when "Allocate" is pressed.
        $second = $this->transfer(['Status' => 'Completed', 'TobeInvoiced' => 'Ναι', 'SalePrice' => 50, 'TransferDate' => Carbon::today()->subDays(2)]);
        Livewire::test(ListPayments::class)->callAction(TestAction::make('allocate')->table($payment));
        $this->assertContains($second, DB::table('paymentlinks')->where('PaymentID', $payment->PaymentID)->pluck('TransferID')->map(fn ($id) => (int) $id)->all());
    }

    public function test_archive_is_read_only(): void
    {
        $this->get('/admin/archive/create')->assertNotFound();
        Livewire::test(ListArchivedTransfers::class)->assertOk();
    }

    public function test_bookings_to_sort_move_into_transfers(): void
    {
        $loadId = DB::table('transfers_load')->insertGetId([
            'ImportDate' => Carbon::today(), 'cID_Customer' => $this->customerId, 'Pax' => 2, 'SupplierCode' => 'GYG-77', 'TransferType' => 'Arrival',
            'TransferDate' => Carbon::tomorrow(), 'TransferTime' => '09:30', 'From' => 'Athens Airport', 'To' => 'Hotel', 'Sale_Price' => '55', 'CarType' => 'VAN', 'Loaded' => 0,
        ], 'LoadID');
        DB::table('transfer_notes_load')->insert(['LoadID' => $loadId, 'Note' => 'Child seat', 'NoteDate' => Carbon::today(), 'CreatedBy' => 'x']);
        $load = TransfersLoad::findOrFail($loadId);

        Livewire::test(ListTransferToSorts::class)->assertCanSeeTableRecords([$load])
            ->callTableBulkAction('moveToTransfers', [$load])
            ->assertCanNotSeeTableRecords([$load]);

        $transfer = DB::table('transfers')->where('TransferLoadID', $loadId)->first();
        $this->assertNotNull($transfer);
        $this->assertSame('GYG-77', $transfer->SupplierCode);
        $this->assertSame('Child seat', DB::table('transfer_notes')->where('TransferID', $transfer->TransferID)->value('Note'));
    }

    public function test_entry_users_do_not_see_payments(): void
    {
        $this->actingAs($this->makeLegacyUser('Entry'));
        $this->get('/admin/payments')->assertForbidden();
    }

    public function test_partner_jobs_are_listed_and_copied_into_our_transfers(): void
    {
        $db = config('database.connections.mariadb.database').'_partner';
        DB::statement("DROP DATABASE IF EXISTS `{$db}`");
        DB::statement("CREATE DATABASE `{$db}`");
        DB::statement("CREATE TABLE `{$db}`.v_transfers2 (TransferID INT PRIMARY KEY, Status TEXT, CarType TEXT, Pax INT, TransferDate DATE, TransferType TEXT,
            TransferTime TEXT, `From` TEXT, `To` TEXT, Flight_Ship_Num_From TEXT, Assistant TEXT, PassengerName TEXT, LeadName TEXT, LeadPhone TEXT,
            Notes TEXT, PurchasePrice DOUBLE, InvoicedRcvd TEXT, CoachCompanyID TEXT)");
        DB::statement("CREATE TABLE `{$db}`.transfers (TransferID INT PRIMARY KEY, ImportDate DATE, cID_Customer INT, Pax INT, SupplierCode TEXT, TransferType TEXT,
            TransferDate DATE, TransferTime TEXT, Status TEXT, `From` TEXT, `To` TEXT, Flight_Ship_Num_From TEXT, LeadName TEXT, Notes TEXT, Assistant TEXT,
            CarTypeID TEXT, PassengerName TEXT, SalePrice DOUBLE)");
        $job = fn (int $id, string $coach) => ['TransferID' => $id, 'Status' => 'Pending', 'Pax' => 2, 'TransferDate' => Carbon::tomorrow()->toDateString(),
            'TransferType' => 'Arrival', 'From' => 'Piraeus', 'To' => 'Hotel', 'LeadName' => "Lead {$id}", 'PurchasePrice' => 40, 'CoachCompanyID' => $coach];
        DB::table("{$db}.v_transfers2")->insert([$job(1, 'MyAthensTransfers'), $job(2, 'MyAthensTransfers'), $job(3, 'Someone else')]);
        DB::table("{$db}.transfers")->insert(['TransferID' => 9, 'cID_Customer' => 71, 'TransferDate' => Carbon::today()->toDateString(), 'LeadName' => 'Ours', 'SupplierCode' => 'S9']);
        $copied = $this->transfer(['SupplierCode' => 'LS - 2']);
        config(['partners.ls.database' => $db]);

        // The real procedure names the partner databases; the test one copies from the test copy.
        DB::unprepared('DROP PROCEDURE IF EXISTS MoveToMyTransfers');
        DB::unprepared("CREATE PROCEDURE MoveToMyTransfers(IN _tid INT, IN _supid TEXT) BEGIN
            INSERT INTO transfers (ImportDate, cID_Customer, TransferType, TransferDate, `From`, `To`, LeadName, SupplierCode, LesseeName)
            SELECT CURDATE(), {$this->customerId}, TransferType, TransferDate, `From`, `To`, LeadName, CONCAT(_supid, ' - ', TransferID), ''
            FROM `{$db}`.v_transfers2 WHERE TransferID = _tid; END");

        try {
            $page = Livewire::test(PartnerJobs::class, ['partner' => 'ls'])->assertOk()->assertSee('Lead 1')->assertSee('Lead 2')->assertDontSee('Lead 3');
            $jobs = PartnerJob::forUs('ls')->get()->keyBy('TransferID');
            $this->assertSame(['New', 'Copied'], [$jobs[1]->MyatStatus, $jobs[2]->MyatStatus]);
            $this->assertSame($copied, (int) $jobs[2]->OurTransferID);

            $page->callTableBulkAction('moveToMyTransfers', $jobs->values());
            $this->assertSame(1, DB::table('transfers')->where('SupplierCode', 'LS - 1')->count());
            $this->assertSame(1, DB::table('transfers')->where('SupplierCode', 'LS - 2')->count());

            Livewire::test(PartnerJobs::class)->call('show', 'ls', 'by-us')->assertSee('Ours');
        } finally {
            DB::statement("DROP DATABASE IF EXISTS `{$db}`");
        }
    }

    public function test_partner_jobs_say_when_a_partner_database_is_out_of_reach(): void
    {
        config(['partners.ls.database' => 'no_such_partner_db']);
        Livewire::test(PartnerJobs::class, ['partner' => 'ls'])->assertOk()->assertSee('cannot be read from here');
    }
}
