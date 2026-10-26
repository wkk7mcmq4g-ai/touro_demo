<?php

namespace Tests\Feature;

use App\Filament\Pages\DayTimeline;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Ημερήσιο πλάνο: vehicles as rows, the day's jobs as blocks, drag to assign. */
class DayTimelineTest extends TestCase
{
    private int $van;

    protected function setUp(): void
    {
        parent::setUp();
        $this->emptyTables(['transfers', 'vehicles', 'drivers', 'vehicle_assignments', 'counterparties', 'tours']);
        $this->van = DB::table('vehicles')->insertGetId(['VehicleName' => 'VITO 1', 'VehicleLicence' => 'IKE', 'Passengers' => 8], 'VehicleID');
        DB::table('vehicles')->insert(['VehicleName' => 'OLD BUS', 'VehicleLicence' => 'Sold']);
        $driver = DB::table('drivers')->insertGetId(['Fullname' => 'Nikos', 'Active' => 1], 'DriverID');
        DB::table('vehicle_assignments')->insert(['VehicleID' => $this->van, 'DriverID' => $driver, 'AssignmentDate' => Carbon::today()]);
        DB::table('counterparties')->insert(['CoachName' => 'PARTNER BUS', 'CounterShortName' => 'PB', 'IsCoach' => 1]);
        DB::table('tours')->insert(['TourName' => 'Delphi Day Trip', 'TourDescription' => '', 'TourDurationHours' => 10]);
        $this->actingAs($this->makeLegacyUser('Admin'));
    }

    private function job(array $a): int
    {
        return DB::table('transfers')->insertGetId(array_merge([
            'ImportDate' => Carbon::today(), 'TransferDate' => Carbon::today(), 'TransferTime' => '10:00', 'TransferType' => 'Arrival',
            'From' => 'Airport', 'To' => 'Hotel', 'Status' => 'Pending', 'LesseeName' => 'x', 'Pax' => 2,
        ], $a), 'TransferID');
    }

    public function test_rows_blocks_durations_and_overlaps_data(): void
    {
        $a = $this->job(['CoachCompanyID' => 'VITO 1', 'TransferTime' => '09:00']);                                  // 09:00–10:30
        $b = $this->job(['CoachCompanyID' => 'VITO 1', 'TransferTime' => '10:00', 'Pax' => 12]);                     // overlaps a; too many pax
        $tour = $this->job(['CoachCompanyID' => 'PARTNER BUS', 'TransferType' => 'Tour', 'To' => 'Delphi Day Trip', 'TransferTime' => '08:00']);
        $tbd = $this->job(['CoachCompanyID' => '', 'TransferTime' => 'TBD']);
        $this->job(['CoachCompanyID' => 'VITO 1', 'Status' => 'Cancelled']);
        $this->job(['CoachCompanyID' => 'VITO 1', 'TransferDate' => Carbon::tomorrow()]);

        $t = Livewire::test(DayTimeline::class)->assertOk()->assertSee('Ημερήσιο πλάνο')->instance()->timeline();

        $this->assertSame(['', 'VITO 1', 'PARTNER BUS'], array_column($t['rows'], 'key')); // no sold vehicle
        $this->assertSame('Nikos', $t['rows'][1]['sub']);
        $this->assertSame(8, $t['rows'][1]['seats']);
        $jobs = collect($t['jobs'])->keyBy('id');
        $this->assertCount(4, $jobs); // not the cancelled one nor tomorrow's
        $this->assertSame([540, 630, true], [$jobs[$a]['start'], $jobs[$a]['end'], $jobs[$a]['estimated']]);
        $this->assertSame([480, 1080, false], [$jobs[$tour]['start'], $jobs[$tour]['end'], $jobs[$tour]['estimated']]); // tour: 10 hours
        $this->assertNull($jobs[$tbd]['start']);
        $this->assertSame(12, $jobs[$b]['pax']);
    }

    public function test_voucher_times_set_by_hand_win(): void
    {
        $id = $this->job(['CoachCompanyID' => 'VITO 1', 'TransferTime' => '10:00', 'Voucher_StartTime' => '09:30', 'Voucher_EndTime' => '13:00']);
        $job = collect(Livewire::test(DayTimeline::class)->instance()->timeline()['jobs'])->firstWhere('id', $id);
        $this->assertSame([600, 780, false], [$job['start'], $job['end'], $job['estimated']]);
    }

    public function test_dragging_assigns_and_undo_puts_it_back(): void
    {
        $id = $this->job(['CoachCompanyID' => '']);

        $page = Livewire::test(DayTimeline::class)->call('assign', $id, 'VITO 1')->assertNotified()->assertSet('revision', 1);
        $this->assertSame('VITO 1', DB::table('transfers')->where('TransferID', $id)->value('CoachCompanyID'));

        $page->dispatch('timeline-undo', transferId: $id, coach: '');
        $this->assertSame('', (string) DB::table('transfers')->where('TransferID', $id)->value('CoachCompanyID'));

        // only coaches from v_coach, only jobs of the day shown
        $page->call('assign', $id, 'NO SUCH VAN');
        $this->assertSame('', (string) DB::table('transfers')->where('TransferID', $id)->value('CoachCompanyID'));
        $other = $this->job(['TransferDate' => Carbon::tomorrow(), 'CoachCompanyID' => '']);
        $page->call('assign', $other, 'VITO 1');
        $this->assertSame('', (string) DB::table('transfers')->where('TransferID', $other)->value('CoachCompanyID'));
    }

    public function test_other_days_and_rights(): void
    {
        $this->job(['TransferDate' => Carbon::tomorrow(), 'LeadName' => 'Tomorrow Lead']);
        $page = Livewire::test(DayTimeline::class)->call('move', 1);
        $this->assertSame(Carbon::tomorrow()->toDateString(), $page->get('date'));
        $this->assertSame('Tomorrow Lead', $page->instance()->timeline()['jobs'][0]['lead']);

        $this->actingAs($this->makeLegacyUser('Driver'));
        $this->get(DayTimeline::getUrl())->assertForbidden();
    }
}
