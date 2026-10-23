<?php

namespace Tests\Feature;

use App\Filament\Resources\Dispatch\Pages\ListDispatch;
use App\Mail\GuideAssignment;
use App\Mail\OlpEntryRequest;
use App\Models\VTransfer;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class DispatchTest extends TestCase
{
    private int $driverId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->emptyTables(['transfers', 'vehicles', 'vehicle_assignments', 'drivers', 'counterparties', 'tours', 'review_links', 'optionvalues', 'status_values']);

        $ike = DB::table('vehicles')->insertGetId(['VehicleName' => 'VITO 2022', 'VehicleLicence' => 'IKE', 'LicencePlate' => 'IKH 1234', 'VoucherName' => 'MYAT'], 'VehicleID');
        $taxi = DB::table('vehicles')->insertGetId(['VehicleName' => 'TAXI 1', 'VehicleLicence' => 'Taxi', 'VoucherName' => 'MYAT'], 'VehicleID');
        $this->driverId = DB::table('drivers')->insertGetId(['Fullname' => 'Γιώργος Οδηγός', 'Email' => 'driver@example.test', 'MobilePhone' => '6971111111', 'IDNumber' => 'Χ 123456'], 'DriverID');
        foreach ([$ike, $taxi] as $vehicle) {
            DB::table('vehicle_assignments')->insert(['VehicleID' => $vehicle, 'DriverID' => $this->driverId, 'AssignmentDate' => Carbon::today()]);
        }
        DB::table('counterparties')->insert(['CounterShortName' => 'Maria Guide', 'AssistantName' => 'Maria', 'IsAssistant' => 1, 'Email' => 'guide@example.test', 'Phones' => '6970000000', 'SupplierName' => 'Maria']);
        DB::table('tours')->insert(['TourName' => 'Delphi', 'TourDescription' => "Delphi full day\nMuseum included", 'DestinationType' => 'Tour']);
    }

    private function job(array $attributes = []): int
    {
        return DB::table('transfers')->insertGetId(array_merge([
            'ImportDate' => Carbon::today()->subDays(3), 'cID_Customer' => 1, 'TransferType' => 'Arrival',
            'TransferDate' => Carbon::today(), 'TransferTime' => '10:00', 'From' => 'Athens Airport', 'To' => 'Hotel Plaka',
            'CarTypeID' => 'VAN', 'Status' => 'Pending', 'LesseeName' => 'X', 'TobeInvoiced' => 'Όχι', 'PaymentType' => 'Cash',
            'Pax' => '2', 'LeadName' => 'John Smith', 'LeadPhone' => '+44 7700 900123', 'CoachCompanyID' => 'VITO 2022', 'Flight_Ship_Num_From' => 'A3 601',
        ], $attributes), 'TransferID');
    }

    public function test_entry_users_cannot_open_the_dispatch_list(): void
    {
        $this->actingAs($this->makeLegacyUser('Entry'));
        $this->get('/admin/dispatch')->assertForbidden();

    }

    public function test_admin_opens_the_dispatch_list(): void
    {
        $this->actingAs($this->makeLegacyUser('Admin'));
        $this->job();

        $this->get('/admin/dispatch')->assertOk()->assertSee('Γιώργος Οδηγός');
    }

    public function test_jobs_of_today_with_voucher_for_own_vehicles_only(): void
    {
        $this->actingAs($this->makeLegacyUser('Admin'));
        $own = $this->job();
        $taxi = $this->job(['CoachCompanyID' => 'TAXI 1', 'TransferTime' => '12:00']);
        $tomorrow = $this->job(['TransferDate' => Carbon::tomorrow()]);
        $cancelled = $this->job(['Status' => 'Cancelled']);

        Livewire::test(ListDispatch::class)
            ->assertCanSeeTableRecords(VTransfer::query()->whereIn('TransferID', [$own, $taxi])->get())
            ->assertCanNotSeeTableRecords(VTransfer::query()->whereIn('TransferID', [$tomorrow])->get())
            ->assertActionVisible(TestAction::make('voucher')->table(VTransfer::find($own)))
            ->assertActionHidden(TestAction::make('voucher')->table(VTransfer::find($taxi)));
        $this->assertNull(VTransfer::find($cancelled));

        $response = $this->get(route('vouchers.show', ['Voucher', $own]));
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_olp_request_is_emailed_with_the_pdf(): void
    {
        Mail::fake();
        $this->actingAs($this->makeLegacyUser('Admin'));
        $id = $this->job(['From' => 'Piraeus Port', 'Flight_Ship_Num_From' => 'Celebrity Edge']);
        $job = VTransfer::find($id);
        $this->assertTrue($job->needsOlpRequest());

        Livewire::test(ListDispatch::class)
            ->callAction(TestAction::make('olp')->table($job), [
                'to' => 'crsecurity@olp.gr', 'cc' => 'ksaveri@hcg.gr', 'reply_to' => 'aris@myathenstransfers.com', 'bcc' => 'info@myathenstransfers.com',
            ])
            ->assertNotified('Επιτυχής αποστολή');

        Mail::assertSent(OlpEntryRequest::class, function (OlpEntryRequest $mail): bool {
            $pdf = $mail->attachments()[0];

            return $mail->hasTo('crsecurity@olp.gr') && $mail->hasCc('ksaveri@hcg.gr')
                && $mail->hasBcc('info@myathenstransfers.com') && $mail->hasBcc('driver@example.test')
                && $mail->hasReplyTo('aris@myathenstransfers.com')
                && $pdf->as === 'ΑΙΤΗΣΗ.pdf';
        });
    }

    public function test_guide_assignment_sms_text_and_tour_description(): void
    {
        Mail::fake();
        $this->actingAs($this->makeLegacyUser('Admin'));
        $id = $this->job(['Assistant' => 'Maria', 'TransferType' => 'Tour', 'To' => 'Delphi']);
        $job = VTransfer::find($id);

        Livewire::test(ListDispatch::class)
            ->callAction(TestAction::make('guide')->table($job))
            ->assertNotified('Επιτυχής αποστολή');
        Mail::assertSent(GuideAssignment::class, fn (GuideAssignment $mail): bool => $mail->hasTo('guide@example.test'));

        $text = $job->smsText();
        $this->assertStringContainsString('*Ωρα Pickup:* 10:00', $text);
        $this->assertStringContainsString('*Οδηγός:* Γιώργος Οδηγός-6971111111', $text);
        $this->assertSame("Delphi full day\nMuseum included", $job->TourDescription);
    }

    public function test_every_voucher_renders(): void
    {
        $this->actingAs($this->makeLegacyUser('Admin'));
        $id = $this->job(['From' => 'Piraeus Port', 'Assistant' => 'Maria']);

        foreach (['Voucher', 'Voucher1', 'VoucherOLP', 'Voucher_assistant'] as $page) {
            $this->assertStringStartsWith('%PDF', $this->get(route('vouchers.show', [$page, $id]))->assertOk()->getContent(), $page);
        }
        $this->get(route('vouchers.show', ['Nope', $id]))->assertNotFound();
    }
}
