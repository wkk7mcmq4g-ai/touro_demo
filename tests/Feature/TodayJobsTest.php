<?php

namespace Tests\Feature;

use App\Filament\Pages\TodayJobs;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** «Σήμερα»: the day's jobs as cards for the phone, with the driver and one-tap call / WhatsApp. */
class TodayJobsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->emptyTables(['transfers', 'vehicles', 'drivers', 'vehicle_assignments', 'counterparties']);
        $van = DB::table('vehicles')->insertGetId(['VehicleName' => 'VITO 1', 'VehicleLicence' => 'IKE', 'Passengers' => 8], 'VehicleID');
        $driver = DB::table('drivers')->insertGetId(['Fullname' => 'Nikos Papas', 'MobilePhone' => '6944 123 456', 'Active' => 1], 'DriverID');
        DB::table('vehicle_assignments')->insert(['VehicleID' => $van, 'DriverID' => $driver, 'AssignmentDate' => Carbon::today()]);
        DB::table('counterparties')->insert(['CoachName' => 'PARTNER BUS', 'CounterShortName' => 'Partner Co', 'Phones' => '+30 210 1234567, 6900000000', 'IsCoach' => 1]);
    }

    private function job(array $a): int
    {
        return DB::table('transfers')->insertGetId(array_merge([
            'ImportDate' => Carbon::today(), 'TransferDate' => Carbon::today(), 'TransferTime' => '10:00', 'TransferType' => 'Arrival',
            'From' => 'Athens Airport', 'To' => 'Hotel Plaka', 'Status' => 'Pending', 'LesseeName' => 'x', 'Pax' => 2,
        ], $a), 'TransferID');
    }

    public function test_cards_with_driver_and_phones(): void
    {
        $this->actingAs($this->makeLegacyUser('Entry'));
        $own = $this->job(['CoachCompanyID' => 'VITO 1', 'TransferTime' => '11:00', 'LeadName' => 'Maria Smith', 'LeadPhone' => '+44 7700 900123', 'Flight_Ship_Num_From' => 'A3 601']);
        $partner = $this->job(['CoachCompanyID' => 'PARTNER BUS', 'TransferTime' => '09:00', 'LeadName' => 'John Doe']);
        $open = $this->job(['CoachCompanyID' => '', 'TransferTime' => '12:00', 'LeadName' => 'No Car']);
        $this->job(['CoachCompanyID' => 'VITO 1', 'Status' => 'Cancelled', 'LeadName' => 'Gone Away']);
        $this->job(['TransferDate' => Carbon::tomorrow(), 'LeadName' => 'Tomorrow Lead']);

        $cards = collect(TodayJobs::cards(CarbonImmutable::today()))->keyBy('id');
        $this->assertSame([$partner, $own, $open], $cards->keys()->all());

        $this->assertSame('Nikos Papas', $cards[$own]['driver']);
        $this->assertTrue($cards[$own]['ownVehicle']);
        $this->assertSame('tel:+306944123456', $cards[$own]['driverPhone']['tel']);
        $this->assertStringStartsWith('https://wa.me/447700900123', $cards[$own]['leadPhone']['wa']);
        $this->assertSame('Partner Co', $cards[$partner]['driver']);
        $this->assertSame('tel:+302101234567', $cards[$partner]['driverPhone']['tel']);
        $this->assertNull($cards[$open]['driverPhone']);

        Livewire::test(TodayJobs::class)->assertOk()
            ->assertSee('Nikos Papas')->assertSee('Maria Smith')->assertSee('A3 601')->assertSee('Χωρίς όχημα')
            ->assertDontSee('Gone Away')->assertDontSee('Tomorrow Lead')
            ->call('move', 1)->assertSee('Tomorrow Lead')->assertSet('date', Carbon::tomorrow()->toDateString());
    }

    public function test_a_vehicle_is_given_from_the_card(): void
    {
        $this->actingAs($this->makeLegacyUser('Entry'));
        $open = $this->job(['CoachCompanyID' => '']);

        Livewire::test(TodayJobs::class)->assertSee('myat-today-fix', false)
            ->call('saveTransferCell', $open, 'CoachCompanyID', 'VITO 1')
            ->assertReturned(null)
            ->assertSee('Nikos Papas');
        $this->assertSame('VITO 1', DB::table('transfers')->where('TransferID', $open)->value('CoachCompanyID'));
    }

    public function test_rights(): void
    {
        $this->actingAs($this->makeLegacyUser('Driver'));
        $this->get(TodayJobs::getUrl())->assertForbidden();
    }
}
