<?php

namespace Tests\Feature;

use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Models\MyatActivityLog;
use App\Models\Vehicle;
use App\Support\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->emptyTables(['myat_activity_log', 'vehicles']);
    }

    /** @return list<MyatActivityLog> */
    private function rows(): array
    {
        return MyatActivityLog::query()->orderBy('ID')->get()->all();
    }

    public function test_inserts_updates_and_deletes_of_a_model_with_old_and_new_values(): void
    {
        $user = $this->makeLegacyUser('Admin');
        $this->actingAs($user);
        $this->emptyTables(['myat_activity_log']);

        $vehicle = Vehicle::query()->create(['VehicleName' => 'VITO 2022', 'VehicleLicence' => 'IKE']);
        $vehicle->update(['VehicleLicence' => 'Rental']);
        $vehicle->update(['VehicleLicence' => 'Rental']); // nothing changed: no row
        $vehicle->delete();

        $rows = $this->rows();
        $this->assertSame(['insert', 'update', 'delete'], array_column(array_map(fn ($r) => $r->toArray(), $rows), 'Action'));

        [$insert, $update, $delete] = $rows;
        $this->assertSame('vehicles', $insert->TableName);
        $this->assertSame((string) $vehicle->getKey(), $insert->RecordID);
        $this->assertSame('VITO 2022', $insert->Changes['VehicleName']);
        $this->assertSame(['VehicleLicence' => ['IKE', 'Rental']], $update->Changes);
        $this->assertSame('VITO 2022', $delete->Changes['VehicleName']);
        $this->assertSame($user->username, $update->Username);
        $this->assertSame(ActivityLog::version(), $update->AppVersion);
        $this->assertNotSame('', $update->AppVersion);
    }

    public function test_other_writes_are_kept_as_the_statement_and_values(): void
    {
        $this->actingAs($this->makeLegacyUser('Admin'));
        $this->emptyTables(['myat_activity_log']);
        $id = DB::table('vehicles')->insertGetId(['VehicleName' => 'E CLASS 2015', 'VehicleLicence' => 'IKE'], 'VehicleID');
        // insertGetId still returns the new vehicle's id, not the log row's (the log has its own connection).
        $this->assertSame('E CLASS 2015', DB::table('vehicles')->where('VehicleID', $id)->value('VehicleName'));
        DB::table('vehicles')->where('VehicleID', $id)->update(['VehicleLicence' => 'PVT']);
        DB::table('vehicles')->where('VehicleID', $id)->delete();

        $rows = $this->rows();
        $this->assertSame(['insert', 'update', 'delete'], array_map(fn ($r) => $r->Action, $rows));
        $this->assertSame([(string) $id, (string) $id, (string) $id], array_map(fn ($r) => $r->RecordID, $rows));
        $this->assertStringContainsString('update `vehicles`', $rows[1]->Changes['sql']);
        $this->assertContains('PVT', $rows[1]->Changes['bindings']);
    }

    public function test_logins_and_logouts_are_logged_and_passwords_and_screen_settings_never(): void
    {
        $user = $this->makeLegacyUser('Admin');
        $this->emptyTables(['myat_activity_log']);

        Auth::login($user);
        Auth::logout();
        $this->assertSame(['login', 'logout'], array_map(fn ($r) => $r->Action, $this->rows()));
        $this->assertSame([$user->username, $user->username], array_map(fn ($r) => $r->Username, $this->rows()));

        $this->emptyTables(['myat_activity_log']);
        $this->actingAs($user);
        DB::table('MyAthensTransfersAPP_users')->where('ID', $user->getKey())->update(['password' => 'secret-hash']);
        DB::table('MyAthensTransfersAPP_settings')->insert(['USERNAME' => $user->username, 'TYPE' => 100, 'TABLENAME' => 'x', 'SEARCH' => '{}']);
        DB::table('MyAthensTransfersAPP_settings')->where('TABLENAME', 'x')->delete();

        $rows = $this->rows();
        $this->assertCount(1, $rows, 'the password change only; screen settings are not logged');
        $this->assertSame(['***'], array_values(array_unique($rows[0]->Changes['bindings'])));
        $this->assertStringNotContainsString('secret-hash', (string) DB::table('myat_activity_log')->value('Changes'));
    }

    public function test_file_contents_are_replaced_by_their_size(): void
    {
        $this->actingAs($this->makeLegacyUser('Admin'));
        $this->emptyTables(['myat_activity_log']);

        DB::table('vehicles')->insert(['VehicleName' => "\xFF\xD8\xFF\xE0binary", 'VehicleLicence' => 'IKE']);

        $this->assertStringStartsWith('(αρχείο, ', $this->rows()[0]->Changes['bindings'][0]);
    }

    public function test_without_the_table_nothing_breaks_and_the_screen_is_hidden(): void
    {
        $this->actingAs($this->makeLegacyUser('Admin'));
        $this->assertTrue(\App\Filament\Resources\ActivityLogs\ActivityLogResource::canAccess());

        DB::statement('RENAME TABLE myat_activity_log TO myat_activity_log_off');
        try {
            ActivityLog::reset();
            Vehicle::query()->create(['VehicleName' => 'VITO 2022', 'VehicleLicence' => 'IKE']);
            $this->assertFalse(\App\Filament\Resources\ActivityLogs\ActivityLogResource::canAccess());
        } finally {
            DB::statement('RENAME TABLE myat_activity_log_off TO myat_activity_log');
            ActivityLog::reset();
        }
    }

    public function test_the_screen_lists_and_opens_a_row(): void
    {
        $this->actingAs($this->makeLegacyUser('Admin'));
        $vehicle = Vehicle::query()->create(['VehicleName' => 'VITO 2022', 'VehicleLicence' => 'IKE']);
        $vehicle->update(['VehicleLicence' => 'Rental']);
        $log = MyatActivityLog::query()->where('Action', 'update')->firstOrFail();

        Livewire::test(ListActivityLogs::class)
            ->assertCanSeeTableRecords([$log])
            ->assertSee('VehicleLicence: IKE → Rental')
            ->mountTableAction('details', $log)
            ->assertHasNoErrors();

        $details = view('filament.activity-log.details', ['log' => $log])->render();
        $this->assertStringContainsString('Πριν', $details);
        $this->assertStringContainsString('Rental', $details);
    }
}
