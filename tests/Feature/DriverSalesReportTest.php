<?php

namespace Tests\Feature;

use App\Filament\Pages\Reports\DriverSalesReport;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Πωλήσεις ανά οδηγό: the transfer's vehicle, then that day's driver from the vehicle assignments. */
class DriverSalesReportTest extends TestCase
{
    public function test_sales_go_to_the_driver_who_had_the_vehicle_that_day(): void
    {
        $this->emptyTables(['transfers', 'vehicles', 'drivers', 'vehicle_assignments']);
        $van = DB::table('vehicles')->insertGetId(['VehicleName' => 'VITO 1', 'VehicleLicence' => 'IKE'], 'VehicleID');
        $bus = DB::table('vehicles')->insertGetId(['VehicleName' => 'BUS 2', 'VehicleLicence' => 'IKE'], 'VehicleID');
        $nikos = DB::table('drivers')->insertGetId(['Fullname' => 'Nikos', 'Active' => 1], 'DriverID');
        $maria = DB::table('drivers')->insertGetId(['Fullname' => 'Maria', 'Active' => 1], 'DriverID');
        DB::table('vehicle_assignments')->insert([
            ['VehicleID' => $van, 'DriverID' => $nikos, 'AssignmentDate' => '2026-09-10'],
            ['VehicleID' => $van, 'DriverID' => $maria, 'AssignmentDate' => '2026-10-05'], // same van, another driver
            ['VehicleID' => $bus, 'DriverID' => $nikos, 'AssignmentDate' => '2026-10-05'],
        ]);
        $job = fn (string $date, string $coach, float $price, array $more = []) => DB::table('transfers')->insert($more + [
            'ImportDate' => $date, 'TransferDate' => $date, 'TransferTime' => '10:00', 'TransferType' => 'Arrival', 'From' => 'ATH', 'To' => 'Hotel',
            'Status' => 'Completed', 'LesseeName' => 'x', 'Pax' => 2, 'CoachCompanyID' => $coach, 'SalePrice' => $price,
        ]);
        $job('2026-09-10', 'VITO 1', 60);
        $job('2026-09-10', 'VITO 1', 40, ['TransferType' => 'Departure']);
        $job('2026-10-05', 'VITO 1', 300, ['TransferType' => 'Tour']);
        $job('2026-10-05', 'BUS 2', 200);
        $job('2026-10-06', 'VITO 1', 70);                               // nobody assigned that day
        $job('2026-10-05', 'VITO 1', 999, ['Status' => 'Cancelled']);   // left out
        $job('2026-10-05', 'PARTNER COACH', 500);                       // not our vehicle

        $this->actingAs($this->makeLegacyUser('Admin'));
        $page = Livewire::test(DriverSalesReport::class)->assertOk()->assertSee('Πωλήσεις ανά οδηγό');

        $r = $page->instance()->result();
        $this->assertEquals(670, $r['total']);
        $this->assertEquals([(string) $nikos => 300.0, (string) $maria => 300.0, '0' => 70.0], array_intersect_key($r['rowTotals'], [(string) $nikos => 1, (string) $maria => 1, '0' => 1]));
        $this->assertSame(DriverSalesReport::NO_DRIVER, $r['rows']['0']);
        $this->assertSame('Nikos', $r['rows'][(string) $nikos]);

        $page->set('data.cols', 'VehicleID');
        $this->assertEquals(['2026-09-10' => 100.0, '2026-10-05' => 300.0], $page->set('data.cols', 'TransferType')->set('data.rows', 'day')
            ->set('data.filter.DriverID', [(string) $nikos, (string) $maria])->set('data.filter.VehicleID', [(string) $van])->instance()->result()['rowTotals']);

        $page->set('data.filter', [])->set('data.rows', 'year')->set('data.measure', 'transfers');
        $this->assertSame(['2026' => 5.0], $page->instance()->result()['rowTotals']);
    }
}
