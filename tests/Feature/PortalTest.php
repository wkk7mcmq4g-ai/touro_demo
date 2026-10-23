<?php

namespace Tests\Feature;

use App\Filament\Portal\Pages\HotelCharges;
use App\Filament\Portal\Pages\MyAssignments;
use App\Filament\Portal\Resources\BookingRequests\Pages\CreateBookingRequest;
use App\Filament\Portal\Resources\BookingRequests\Pages\ListBookingRequests;
use App\Filament\Portal\Resources\MyTransfers\Pages\EditMyTransfer;
use App\Filament\Portal\Resources\MyTransfers\Pages\ListMyTransfers;
use App\Models\Transfer;
use App\Models\TransfersLoad;
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
        $this->get('/admin/transfers')->assertForbidden();
    }

    public function test_office_users_cannot_use_the_portal(): void
    {
        $this->actingAs($this->makeLegacyUser('Admin'));
        $this->get('/portal/transfers')->assertForbidden();
    }

    public function test_drivers_are_not_in_the_portal_yet(): void
    {
        $this->actingAs($this->makeLegacyUser('Drivers'));
        $this->get('/portal/transfers')->assertForbidden();
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
}
