<?php

namespace Tests\Feature;

use App\Filament\Pages\DriverCalendar;
use App\Filament\Resources\DriverLeaves\Pages\ListDriverLeaves;
use App\Filament\Resources\Drivers\Pages\EditDriver;
use App\Filament\Resources\FuelRegisters\Pages\ListFuelRegisters;
use App\Filament\Resources\VehicleAssignments\Pages\CreateVehicleAssignment;
use App\Filament\Resources\Vehicles\Pages\CreateVehicle;
use App\Filament\Resources\Vehicles\Pages\EditVehicle;
use App\Filament\Resources\Vehicles\RelationManagers\KilometresRelationManager;
use App\Filament\Resources\Vehicles\RelationManagers\LogsRelationManager;
use App\Models\Driver;
use App\Models\DriverLeaveEntry;
use App\Models\FuelRegister;
use App\Models\Vehicle;
use App\Models\VehiclesLog;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class FleetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->emptyTables(['vehicles', 'vehicles_log', 'vehicles_klm', 'vehicle_assignments', 'fuel_register', 'drivers', 'drivers_holidays_master', 'schedule']);
        $this->actingAs($this->makeLegacyUser('Admin'));
    }

    public function test_entry_users_have_no_fleet_screens(): void
    {
        $this->actingAs($this->makeLegacyUser('Entry'));
        foreach (['vehicles', 'vehicle-log', 'vehicle-assignments', 'fuel', 'vehicle-km', 'drivers', 'driver-leave'] as $slug) {
            $this->get("/admin/{$slug}")->assertForbidden();
        }
    }

    public function test_vehicles_with_log_book_and_km(): void
    {
        Livewire::test(CreateVehicle::class)
            ->fillForm(['VehicleName' => 'VITO 2024', 'VehicleLicence' => 'IKE', 'LicencePlate' => 'ΙΚΗ 2024', 'Passengers' => 7])
            ->call('create')->assertHasNoFormErrors();
        $vehicle = Vehicle::query()->where('VehicleName', 'VITO 2024')->firstOrFail();

        $rm = fn (string $class) => Livewire::test($class, ['ownerRecord' => $vehicle, 'pageClass' => EditVehicle::class]);
        $rm(LogsRelationManager::class)
            ->callAction(TestAction::make('create')->table(), data: ['LogDate' => '2026-10-20', 'LogType' => 'Σερβις', 'Description' => 'Λάδια, φίλτρα', 'Kilometers' => 120500])
            ->assertHasNoFormErrors();
        $log = VehiclesLog::query()->firstOrFail();
        $this->assertSame([$vehicle->VehicleID, 'Σερβις', auth()->user()->username, ''], [$log->VehicleID, $log->LogType, $log->CreatedBy, $log->ModifiedBy]);

        $rm(KilometresRelationManager::class)
            ->callAction(TestAction::make('create')->table(), data: ['KLM' => 120600, 'RecordDate' => '2026-10-21'])
            ->assertHasNoFormErrors();
        $this->assertEquals(120600, $vehicle->kilometres()->value('KLM'));

        $this->get('/admin/vehicles')->assertOk()->assertSee('VITO 2024');
        $this->get('/admin/vehicle-log')->assertOk()->assertSee('Λάδια, φίλτρα');
    }

    public function test_assignments_one_vehicle_per_driver_per_day(): void
    {
        $v1 = DB::table('vehicles')->insertGetId(['VehicleName' => 'V1', 'VehicleLicence' => 'IKE'], 'VehicleID');
        $v2 = DB::table('vehicles')->insertGetId(['VehicleName' => 'V2', 'VehicleLicence' => 'IKE'], 'VehicleID');
        $d1 = DB::table('drivers')->insertGetId(['Fullname' => 'Nikos', 'Active' => 1], 'DriverID');
        $d2 = DB::table('drivers')->insertGetId(['Fullname' => 'Kostas', 'Active' => 1], 'DriverID');
        DB::table('vehicle_assignments')->insert(['VehicleID' => $v1, 'DriverID' => $d1, 'AssignmentDate' => '2026-10-24']);

        Livewire::test(CreateVehicleAssignment::class)
            ->fillForm(['AssignmentDate' => '2026-10-24', 'DriverID' => $d2, 'VehicleID' => $v1])->call('create')
            ->assertHasFormErrors(['VehicleID']);
        Livewire::test(CreateVehicleAssignment::class)
            ->fillForm(['AssignmentDate' => '2026-10-24', 'DriverID' => $d1, 'VehicleID' => $v2])->call('create')
            ->assertHasFormErrors(['DriverID']);
        Livewire::test(CreateVehicleAssignment::class)
            ->fillForm(['AssignmentDate' => '2026-10-24', 'DriverID' => $d2, 'VehicleID' => $v2])->call('create')
            ->assertHasNoFormErrors();
        $this->assertSame(2, DB::table('vehicle_assignments')->whereDate('AssignmentDate', '2026-10-24')->count());
    }

    public function test_fuel_receipts_are_checked_with_one_click(): void
    {
        $v = DB::table('vehicles')->insertGetId(['VehicleName' => 'TAXI 1', 'VehicleLicence' => 'Taxi'], 'VehicleID');
        $id = DB::table('fuel_register')->insertGetId(['FuelDate' => '2026-10-22', 'VehicleID' => $v, 'FuelAmount' => 55.4, 'CreatedBy' => 'nikos',
            'Files' => '[{"name":"files/receipt_ab12.jpg","usrName":"receipt.jpg","size":100,"type":"image/jpeg"}]'], 'ID');
        $fuel = FuelRegister::findOrFail($id);

        Livewire::test(ListFuelRegisters::class)
            ->assertSee('receipt.jpg')
            ->callAction(TestAction::make('approve')->table($fuel));
        $fuel->refresh();
        $this->assertSame(['OK', auth()->user()->username], [$fuel->ApprovedStatus, $fuel->ApprovedBy]);
        $this->assertSame(Carbon::today()->toDateString(), $fuel->ApprovedDate->toDateString());
    }

    public function test_driver_files_are_added_to_the_phprunner_folder(): void
    {
        $root = sys_get_temp_dir().'/phprunner-'.uniqid();
        mkdir($root.'/files', 0777, true);
        config(['services.phprunner.root' => $root, 'filesystems.disks.phprunner_files.root' => $root.'/files']);
        $id = DB::table('drivers')->insertGetId(['Fullname' => 'Nikos', 'Active' => 1, 'Files' => '[{"name":"files/old_x.pdf","usrName":"old.pdf","size":1,"type":"application/pdf"}]'], 'DriverID');

        Livewire::test(EditDriver::class, ['record' => $id])
            ->assertSee('old.pdf')
            ->fillForm(['Files__upload' => [UploadedFile::fake()->createWithContent('Δίπλωμα.pdf', '%PDF-1.4')]])
            ->call('save')->assertHasNoFormErrors();

        $files = json_decode(Driver::findOrFail($id)->Files, true);
        $this->assertCount(2, $files);
        $this->assertSame('Δίπλωμα.pdf', $files[1]['usrName']);
        $this->assertFileExists($root.'/'.$files[1]['name']);
    }

    public function test_leave_list_combines_periods_and_calendar_days(): void
    {
        $d = DB::table('drivers')->insertGetId(['Fullname' => 'Nikos', 'Active' => 1], 'DriverID');
        DB::table('drivers_holidays_master')->insert(['DriverID' => $d, 'HolidayStartDate' => '2026-08-01', 'HolidayEndDate' => '2026-08-10', 'HolidayType' => 'ΑΔΕΙΑ']);
        DB::table('schedule')->insert([['driver_id' => $d, 'date' => '2026-09-05', 'status' => 0], ['driver_id' => $d, 'date' => '2026-09-06', 'status' => 1]]);

        $rows = DriverLeaveEntry::source()->get();
        $this->assertSame([10, 1], $rows->pluck('HolidayDays')->sortDesc()->values()->all());
        $this->assertEqualsCanonicalizing(['ΑΔΕΙΑ', 'ΡΕΠΟ'], $rows->pluck('HolidayType')->all());

        Livewire::test(ListDriverLeaves::class)->assertCanSeeTableRecords($rows)->assertSee('Nikos');
    }

    public function test_driver_calendar_shows_and_saves_the_schedule(): void
    {
        $d = DB::table('drivers')->insertGetId(['Fullname' => 'Nikos K', 'shortname' => 'NIKOS', 'Active' => 1, 'CalOrder' => 1, 'Flag' => 1], 'DriverID');
        DB::table('drivers')->insert(['Fullname' => 'Hidden', 'shortname' => 'HIDDEN', 'Active' => 1, 'CalOrder' => null]);
        $day = now()->startOfMonth()->addDays(2)->toDateString();
        DB::table('schedule')->insert(['driver_id' => $d, 'date' => $day, 'status' => 0]);

        $this->get(DriverCalendar::getUrl())->assertOk()->assertSee('NIKOS')->assertDontSee('HIDDEN')->assertDontSee('driver_cal');
        $cal = Livewire::test(DriverCalendar::class)->instance()->calendar();
        $this->assertSame('0', $cal['cells']->{$d}->{$day});
        $this->assertCount(now()->startOfMonth()->daysInMonth + now()->startOfMonth()->addMonthNoOverflow()->daysInMonth, $cal['days']);

        $page = Livewire::test(DriverCalendar::class)->call('setStatus', $d, $day, '3');
        $this->assertSame(3, (int) DB::table('schedule')->where('driver_id', $d)->where('date', $day)->value('status'));
        $this->assertSame(1, DB::table('schedule')->where('driver_id', $d)->count());

        $page->call('setStatus', $d, $day, '');
        $this->assertSame(0, DB::table('schedule')->where('driver_id', $d)->count());

        $page->call('move', -1)->assertSet('offset', -1)->call('move', 0)->assertSet('offset', 0);
    }

    public function test_calendar_settings_reorder_hide_colour_and_no_copy(): void
    {
        $a = DB::table('drivers')->insertGetId(['Fullname' => 'A', 'shortname' => 'AAA', 'Active' => 1, 'CalOrder' => 1, 'Flag' => 1], 'DriverID');
        $b = DB::table('drivers')->insertGetId(['Fullname' => 'B', 'shortname' => 'BBB', 'Active' => 1, 'CalOrder' => 2], 'DriverID');
        $c = DB::table('drivers')->insertGetId(['Fullname' => 'C', 'shortname' => 'CCC', 'Active' => 1, 'CalOrder' => null], 'DriverID');

        // Before any colour is picked, Flag 1 shows pink as driver_cal did.
        $names = collect(Livewire::test(DriverCalendar::class)->instance()->calendar()['drivers'])->keyBy('id');
        $this->assertStringContainsString(DriverCalendar::PINK, $names[$a]['style']);
        $this->assertSame('', $names[$b]['style']);

        // Saving the panel untouched keeps the order, the hidden driver hidden and A pink.
        Livewire::test(DriverCalendar::class)->callAction('settings')->assertHasNoActionErrors();
        $this->assertSame([1, 2, null], DB::table('drivers')->whereIn('DriverID', [$a, $b, $c])->orderBy('DriverID')->pluck('CalOrder')->all());
        $this->assertSame(DriverCalendar::PINK, DB::table('drivers')->where('DriverID', $a)->value('CalColor'));

        Livewire::test(DriverCalendar::class)->call('saveSettings', [
            ['id' => $c, 'shown' => true, 'color' => '#1d4ed8', 'nocopy' => true],
            ['id' => $b, 'shown' => true, 'color' => 'red;x:y', 'nocopy' => false],
            ['id' => $a, 'shown' => false, 'color' => null, 'nocopy' => false],
        ])->assertSet('revision', 1);

        $rows = DB::table('drivers')->whereIn('DriverID', [$a, $b, $c])->get(['DriverID', 'CalOrder', 'Flag', 'CalColor'])->keyBy('DriverID');
        $this->assertSame([1, 2, null], [$rows[$c]->CalOrder, $rows[$b]->CalOrder, $rows[$a]->CalOrder]);
        $this->assertSame([1, null, 0], [$rows[$c]->Flag, $rows[$b]->Flag, $rows[$a]->Flag]);
        $this->assertSame(['#1d4ed8', null, null], [$rows[$c]->CalColor, $rows[$b]->CalColor, $rows[$a]->CalColor]);

        $style = collect(Livewire::test(DriverCalendar::class)->instance()->calendar()['drivers'])->keyBy('id')[$c]['style'];
        $this->assertSame('background:#1d4ed8;color:#ffffff', $style);

        $this->actingAs($this->makeLegacyUser('Entry'));
        $this->get(DriverCalendar::getUrl())->assertForbidden();
    }

    public function test_calendar_works_before_the_colour_column_exists(): void
    {
        DriverCalendar::$colorColumn = false;
        $this->beforeApplicationDestroyed(fn () => DriverCalendar::$colorColumn = null);
        $a = DB::table('drivers')->insertGetId(['Fullname' => 'A', 'shortname' => 'AAA', 'Active' => 1, 'CalOrder' => 1, 'Flag' => 1, 'CalColor' => '#000000'], 'DriverID');

        $page = Livewire::test(DriverCalendar::class);
        $this->assertStringContainsString(DriverCalendar::PINK, $page->instance()->calendar()['drivers'][0]['style']);
        $page->call('saveSettings', [['id' => $a, 'shown' => true, 'color' => '#123456', 'nocopy' => false]]);
        $this->assertSame('#000000', DB::table('drivers')->where('DriverID', $a)->value('CalColor'));
        $this->assertSame(0, DB::table('drivers')->where('DriverID', $a)->value('Flag'));
    }

    public function test_copy_a_month_of_schedule(): void
    {
        $a = DB::table('drivers')->insertGetId(['Fullname' => 'A', 'shortname' => 'AAA', 'Active' => 1, 'CalOrder' => 1], 'DriverID');
        $b = DB::table('drivers')->insertGetId(['Fullname' => 'B', 'shortname' => 'BBB', 'Active' => 1, 'CalOrder' => 2, 'Flag' => 1], 'DriverID');
        $off = DB::table('drivers')->insertGetId(['Fullname' => 'X', 'shortname' => 'XXX', 'Active' => 1, 'CalOrder' => null], 'DriverID');
        DB::table('schedule')->insert([
            ['driver_id' => $a, 'date' => '2026-10-01', 'status' => 1],
            ['driver_id' => $a, 'date' => '2026-10-02', 'status' => 0],
            ['driver_id' => $a, 'date' => '2026-10-31', 'status' => 9], // November has no 31st
            ['driver_id' => $b, 'date' => '2026-10-01', 'status' => 3],
            ['driver_id' => $off, 'date' => '2026-10-01', 'status' => 3],
            ['driver_id' => $a, 'date' => '2026-11-02', 'status' => 5], // already filled in
        ]);
        $nov = fn (): array => DB::table('schedule')->where('date', '>=', '2026-11-01')->orderBy('driver_id')->orderBy('date')
            ->get()->map(fn ($r) => "{$r->driver_id} {$r->date} {$r->status}")->all();

        $page = Livewire::test(DriverCalendar::class);
        $this->assertSame(1, $page->instance()->copyMonth('2026-10', '2026-11', true, false));
        $this->assertSame(["{$a} 2026-11-01 1", "{$a} 2026-11-02 5"], $nov());

        $this->assertSame(3, $page->instance()->copyMonth('2026-10', '2026-11', false, true));
        $this->assertSame(["{$a} 2026-11-01 1", "{$a} 2026-11-02 0", "{$b} 2026-11-01 3"], $nov());

        $page->callAction('copyMonth', ['from' => '2026-10', 'to' => '2026-10'])->assertHasActionErrors(['to']);
    }

    public function test_clear_a_month_of_schedule(): void
    {
        $a = DB::table('drivers')->insertGetId(['Fullname' => 'A', 'shortname' => 'AAA', 'Active' => 1, 'CalOrder' => 1], 'DriverID');
        $b = DB::table('drivers')->insertGetId(['Fullname' => 'B', 'shortname' => 'BBB', 'Active' => 1, 'CalOrder' => 2, 'Flag' => 1], 'DriverID');
        DB::table('schedule')->insert([
            ['driver_id' => $a, 'date' => '2026-11-01', 'status' => 1],
            ['driver_id' => $a, 'date' => '2026-11-30', 'status' => 0],
            ['driver_id' => $b, 'date' => '2026-11-05', 'status' => 3],
            ['driver_id' => $a, 'date' => '2026-12-01', 'status' => 1], // another month
        ]);

        Livewire::test(DriverCalendar::class)
            ->callAction('clearMonth', ['month' => '2026-11', 'drivers' => 'unflagged'])->assertHasNoActionErrors();
        $this->assertSame(["{$b} 2026-11-05", "{$a} 2026-12-01"], DB::table('schedule')->orderBy('date')->get()->map(fn ($r) => "{$r->driver_id} {$r->date}")->all());

        $this->assertSame(1, Livewire::test(DriverCalendar::class)->instance()->clearMonth('2026-11', false));
        $this->assertSame(1, DB::table('schedule')->count());
    }
}
