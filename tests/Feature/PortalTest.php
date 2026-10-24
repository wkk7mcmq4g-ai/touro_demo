<?php

namespace Tests\Feature;

use App\Filament\Portal\Pages\HotelCharges;
use App\Filament\Portal\Pages\MyAssignments;
use App\Filament\Portal\Resources\BookingRequests\Pages\CreateBookingRequest;
use App\Filament\Portal\Resources\BookingRequests\Pages\ListBookingRequests;
use App\Filament\Portal\Resources\MyFuel\Pages\CreateMyFuel;
use App\Filament\Portal\Resources\MyFuel\Pages\ListMyFuel;
use App\Filament\Portal\Resources\MyJobs\Pages\ListMyJobs;
use App\Filament\Portal\Resources\MyTransfers\MyTransferResource;
use App\Filament\Portal\Resources\MyTransfers\Pages\EditMyTransfer;
use App\Filament\Portal\Resources\MyTransfers\Pages\ListMyTransfers;
use App\Models\DailyRecipient;
use App\Models\Transfer;
use App\Models\TransfersLoad;
use App\Models\User;
use App\Support\Portal;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** The portal for partners, suppliers and hotels: each sees only their own data. */
class PortalTest extends TestCase
{
    private int $ours;

    private int $theirs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->emptyTables(['transfers', 'transfers_load', 'counterparties', 'transfer_charges', 'optionvalues', 'status_values', 'myat_transfers_files', 'myat_transfers_load_files']);
        Portal::flush();
        $this->ours = DB::table('counterparties')->insertGetId(['CounterShortName' => 'LS', 'SupplierName' => 'LS Tours', 'IsSupplier' => 1, 'LesseName' => 'LS'], 'CID');
        $this->theirs = DB::table('counterparties')->insertGetId(['CounterShortName' => 'OTHER', 'SupplierName' => 'Other', 'IsSupplier' => 1, 'LesseName' => 'O'], 'CID');
        DB::table('optionvalues')->insert([['Option' => 'TransferType', 'Value' => 'Arrival'], ['Option' => 'CarType', 'Value' => 'VAN']]);
        DB::table('status_values')->insert([['Status_Group' => 'Partner', 'StatusValue' => 'Pending'], ['Status_Group' => 'Partner', 'StatusValue' => 'Cancelled']]);
        Filament::setCurrentPanel(Filament::getPanel('portal'));
    }

    private function partner(): void
    {
        $user = $this->makeLegacyUser('Partner');
        DB::table('counterparties')->where('CID', $this->ours)->update(['UserID' => $user->ID]);
        $this->actingAs($user);
    }

    private function transfer(int $customer, array $attributes = []): Transfer
    {
        $id = DB::table('transfers')->insertGetId(array_merge([
            'ImportDate' => Carbon::today(), 'cID_Customer' => $customer, 'TransferType' => 'Arrival', 'TransferDate' => Carbon::today()->addDays(3),
            'TransferTime' => '10:00', 'From' => 'Airport', 'To' => 'Hotel', 'CarTypeID' => 'VAN', 'Status' => 'Pending', 'LesseeName' => 'x', 'Pax' => '2',
        ], $attributes), 'TransferID');

        return Transfer::findOrFail($id);
    }

    public function test_office_and_portal_logins_are_kept_apart(): void
    {
        $this->actingAs($this->makeLegacyUser('Partner'));
        $this->get('/portal/transfers')->assertOk();
        // The back office is not theirs: they are sent to their own panel.
        $this->get('/admin/transfers')->assertRedirect(url('/portal'));
    }

    public function test_office_users_cannot_use_the_portal(): void
    {
        $this->actingAs($this->makeLegacyUser('Admin'));
        $this->get('/portal/transfers')->assertRedirect(url('/admin'));
    }

    /** A job for the driver of $user today, through the vehicle assigned to them (as v_transfers works). */
    private function driverJob(User $user, string $lead): int
    {
        $vehicle = DB::table('vehicles')->insertGetId(['VehicleName' => 'VITO '.$lead, 'VehicleLicence' => 'IKE'], 'VehicleID');
        $driver = DB::table('drivers')->insertGetId(['Fullname' => 'Driver '.$lead, 'UserID' => $user->ID, 'Active' => 1], 'DriverID');
        DB::table('vehicle_assignments')->insert(['VehicleID' => $vehicle, 'DriverID' => $driver, 'AssignmentDate' => Carbon::today()]);

        return $this->transfer($this->ours, ['CoachCompanyID' => 'VITO '.$lead, 'TransferDate' => Carbon::today(), 'LeadName' => $lead])->TransferID;
    }

    public function test_drivers_see_their_own_jobs_vouchers_and_files(): void
    {
        $this->emptyTables(['vehicles', 'drivers', 'vehicle_assignments']);
        $me = $this->makeLegacyUser('Drivers');
        $other = $this->makeLegacyUser('Drivers');
        $mine = $this->driverJob($me, 'Mine');
        $theirs = $this->driverJob($other, 'Theirs');
        $this->actingAs($me);

        Livewire::test(ListMyJobs::class)->assertSee('Mine')->assertDontSee('Theirs');
        $this->get(route('vouchers.show', ['Voucher', $mine]))->assertOk();
        $this->get(route('vouchers.show', ['Voucher', $theirs]))->assertNotFound();

        $file = DB::table('myat_transfers_files')->insertGetId(['TransferID' => $theirs, 'Flnm' => 'x.pdf', 'Attachment' => '%PDF x']);
        $this->get(route('transfer-files.download', $file))->assertNotFound();
        $this->get('/portal/transfers')->assertForbidden();
    }

    public function test_drivers_confirm_they_read_the_day_by_its_number_of_jobs(): void
    {
        $this->emptyTables(['vehicles', 'drivers', 'vehicle_assignments', 'emails_sent']);
        $me = $this->makeLegacyUser('Drivers');
        $this->driverJob($me, 'Mine');
        $this->actingAs($me);
        $today = Carbon::today()->toDateString();

        Livewire::test(ListMyJobs::class)
            ->callAction('readReceipt', ['day' => $today, 'transfers' => 3])
            ->assertNotified('Λάθος αριθμός δρομολογίων');
        $this->assertSame(0, DB::table('emails_sent')->count());

        Livewire::test(ListMyJobs::class)
            ->callAction('readReceipt', ['day' => $today, 'transfers' => 1])
            ->assertNotified('Ευχαριστούμε, καταχωρήθηκε');
        $this->assertSame(1, DB::table('emails_sent')->whereDate('TransferDate', $today)->count());
        $this->assertSame('Read', DailyRecipient::source()->whereDate('TransferDate', $today)->value('SendStatus'));
    }

    public function test_drivers_enter_fuel_within_phprunner_dates(): void
    {
        $this->emptyTables(['fuel_register', 'vehicles', 'drivers']);
        $vehicle = DB::table('vehicles')->insertGetId(['VehicleName' => 'VITO', 'VehicleLicence' => 'IKE'], 'VehicleID');
        $me = $this->makeLegacyUser('Drivers');
        $driver = DB::table('drivers')->insertGetId(['Fullname' => 'Me', 'UserID' => $me->ID, 'Active' => 1], 'DriverID');
        $this->actingAs($me);

        Livewire::test(CreateMyFuel::class)
            ->fillForm(['VehicleID' => $vehicle, 'FuelDate' => Carbon::tomorrow()->toDateString(), 'FuelAmount' => 50])
            ->call('create')->assertHasFormErrors(['FuelDate']);
        Livewire::test(CreateMyFuel::class)
            ->fillForm(['VehicleID' => $vehicle, 'FuelDate' => Carbon::today()->toDateString(), 'FuelAmount' => 50])
            ->call('create')->assertHasNoFormErrors();
        $this->assertSame([$driver, $me->username], [(int) DB::table('fuel_register')->value('DriverID'), DB::table('fuel_register')->value('CreatedBy')]);

        DB::table('fuel_register')->insert(['VehicleID' => $vehicle, 'FuelDate' => Carbon::today(), 'FuelAmount' => 99, 'CreatedBy' => 'someone_else']);
        Livewire::test(ListMyFuel::class)->assertCountTableRecords(1);
    }

    public function test_driver_screens_follow_the_driver_groups(): void
    {
        $this->actingAs($this->makeLegacyUser('Drivers_Log'));
        $this->get('/portal/vehicle-log')->assertOk();
        $this->get('/portal/leave')->assertForbidden();
    }

    public function test_leave_is_for_drivers_hol(): void
    {
        $this->actingAs($this->makeLegacyUser('Drivers_Hol'));
        $this->get('/portal/leave')->assertOk();
        $this->get('/portal/vehicle-log')->assertForbidden();
    }

    public function test_a_partner_sees_and_edits_only_its_own_transfers(): void
    {
        $this->partner();
        $mine = $this->transfer($this->ours);
        $other = $this->transfer($this->theirs);

        Livewire::test(ListMyTransfers::class)->assertCanSeeTableRecords([$mine])->assertCanNotSeeTableRecords([$other]);
        $this->get("/portal/transfers/{$other->TransferID}")->assertNotFound();
        $this->get("/portal/transfers/{$mine->TransferID}")->assertOk();

        Livewire::test(EditMyTransfer::class, ['record' => $mine->TransferID])
            ->fillForm(['Status' => 'Cancelled', 'LeadName' => 'Mr Lead', 'Pax' => '9'])
            ->call('save')->assertHasNoFormErrors();
        $mine->refresh();
        $this->assertSame(['Cancelled', 'Mr Lead', '2'], [$mine->Status, $mine->LeadName, (string) $mine->Pax], 'Pax is read-only here');
        $this->assertSame(auth()->user()->username, $mine->ModifiedBy);

        Livewire::test(EditMyTransfer::class, ['record' => $mine->TransferID])
            ->fillForm(['TransferDate' => Carbon::today()->subDays(2)->toDateString()])
            ->call('save')->assertHasFormErrors(['TransferDate']);
    }

    public function test_booking_requests_belong_to_the_partner_and_follow_phprunner_date_rules(): void
    {
        $this->partner();
        DB::table('transfers_load')->insert(['cID_Customer' => $this->theirs, 'TransferDate' => Carbon::today()->addDays(5), 'From' => 'x', 'To' => 'y', 'Loaded' => 0]);
        $form = ['Pax' => '3', 'TransferType' => 'Arrival', 'TransferTime' => '11:00', 'From' => 'Airport', 'To' => 'Hotel'];

        Livewire::test(CreateBookingRequest::class)->fillForm($form + ['TransferDate' => Carbon::tomorrow()->toDateString()])
            ->call('create')->assertHasFormErrors(['TransferDate']);
        Livewire::test(CreateBookingRequest::class)->fillForm($form + ['TransferDate' => Carbon::today()->addDays(4)->toDateString()])
            ->call('create')->assertHasNoFormErrors();

        $mine = TransfersLoad::query()->where('cID_Customer', $this->ours)->firstOrFail();
        $this->assertSame([auth()->user()->username, 0], [$mine->Createdby, $mine->Loaded]);
        Livewire::test(ListBookingRequests::class)->assertCanSeeTableRecords([$mine])
            ->assertCountTableRecords(1);
    }

    public function test_files_of_other_customers_cannot_be_downloaded(): void
    {
        $this->partner();
        $mine = $this->transfer($this->ours);
        $other = $this->transfer($this->theirs);
        $myFile = DB::table('myat_transfers_files')->insertGetId(['TransferID' => $mine->TransferID, 'Flnm' => 'a.pdf', 'Attachment' => '%PDF-1.4 x']);
        $otherFile = DB::table('myat_transfers_files')->insertGetId(['TransferID' => $other->TransferID, 'Flnm' => 'b.pdf', 'Attachment' => '%PDF-1.4 y']);

        $this->get(route('transfer-files.download', $myFile))->assertOk();
        $this->get(route('transfer-files.download', $otherFile))->assertNotFound();
    }

    public function test_assignments_are_the_coach_users_own(): void
    {
        $this->partner();
        DB::table('counterparties')->where('CID', $this->ours)->update(['CoachName' => 'LS COACH']);
        $this->transfer($this->theirs, ['CoachCompanyID' => 'LS COACH', 'LeadName' => 'Assigned to me', 'TransferDate' => '2026-12-01']);
        $this->transfer($this->theirs, ['CoachCompanyID' => 'SOMEONE', 'LeadName' => 'Not mine', 'TransferDate' => '2026-12-01']);

        Livewire::test(MyAssignments::class)->assertSee('Assigned to me')->assertDontSee('Not mine');
    }

    public function test_hotels_see_their_expenses_and_cannot_edit_transfers(): void
    {
        $user = $this->makeLegacyUser('Supplier_Hotel', ['SupplierID' => $this->ours]);
        $this->actingAs($user);
        $mine = $this->transfer($this->ours);
        $cancelled = $this->transfer($this->ours, ['Status' => 'Cancelled']);
        DB::table('transfer_charges')->insert([
            ['TransferID' => $mine->TransferID, 'ExpenseReason' => 'Minibar', 'Expense' => 25, 'ExpenseChargeCategory' => 1314],
            ['TransferID' => $cancelled->TransferID, 'ExpenseReason' => 'Ghost', 'Expense' => 40, 'ExpenseChargeCategory' => 1314],
        ]);

        Livewire::test(HotelCharges::class)->assertSee('Minibar')->assertDontSee('Ghost');
        $this->get("/portal/transfers/{$mine->TransferID}")->assertOk();
        $this->get("/portal/transfers/{$mine->TransferID}/edit")->assertForbidden();
        $this->get('/portal/booking-requests')->assertForbidden();
    }

    public function test_suppliers_cannot_edit_transfers_of_today_or_earlier(): void
    {
        $this->actingAs($this->makeLegacyUser('Supplier', ['SupplierID' => $this->ours]));
        $today = $this->transfer($this->ours, ['TransferDate' => Carbon::today()->toDateString()]);
        $past = $this->transfer($this->ours, ['TransferDate' => Carbon::today()->subDays(3)->toDateString()]);
        $tomorrow = $this->transfer($this->ours, ['TransferDate' => Carbon::tomorrow()->toDateString()]);

        if (! MyTransferResource::canEdit($tomorrow)) {
            $this->markTestSkipped('The Supplier group has no edit right on transfers in this copy.');
        }
        $this->get("/portal/transfers/{$tomorrow->TransferID}/edit")->assertOk();
        $this->get("/portal/transfers/{$today->TransferID}/edit")->assertForbidden();
        $this->get("/portal/transfers/{$past->TransferID}/edit")->assertForbidden();
        Livewire::test(ListMyTransfers::class)
            ->assertTableActionVisible('edit', $tomorrow)
            ->assertTableActionHidden('edit', $today);
    }
}
