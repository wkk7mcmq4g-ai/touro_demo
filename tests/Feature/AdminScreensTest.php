<?php

namespace Tests\Feature;

use App\Filament\Pages\ElorusContacts;
use App\Filament\Pages\ElorusSettings;
use App\Filament\Resources\AccessLogs\Pages\ListAccessLogs;
use App\Filament\Resources\OptionValues\Pages\ManageOptionValues;
use App\Models\AccessLog;
use App\Models\OptionValue;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** Admin: access log, Elorus settings and contacts, field settings (optionvalues). */
class AdminScreensTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['elorus.buttons' => true]); // hidden in the Demo; the Elorus screens themselves stay tested
        $this->emptyTables(['elorus_Settings', 'optionvalues']);
        DB::table('elorus_Settings')->insert(['OrgID' => '111', 'apikey' => 'secret-key-123', 'PrivateIndividualID' => '5', 'DefaultProductID' => '9']);
        $this->actingAs($this->makeLegacyUser('Admin'));
    }

    public function test_every_screen_opens(): void
    {
        foreach (['access-log', 'elorus-settings', 'elorus-contacts', 'field-settings'] as $slug) {
            $this->get("/admin/{$slug}")->assertOk();
        }
    }

    public function test_entry_users_have_no_admin_screens(): void
    {
        $this->actingAs($this->makeLegacyUser('Entry'));
        foreach (['access-log', 'elorus-settings', 'elorus-contacts', 'field-settings'] as $slug) {
            $this->get("/admin/{$slug}")->assertForbidden();
        }
    }

    public function test_access_log_lists_logins(): void
    {
        DB::table('access_log')->insert(['userid' => 'test_someone', 'AccessStatus' => 'In', 'LogDate' => now()]);
        $row = AccessLog::query()->where('userid', 'test_someone')->firstOrFail();
        Livewire::test(ListAccessLogs::class)->assertCanSeeTableRecords([$row]);
    }

    public function test_elorus_key_is_never_shown_and_kept_when_left_empty(): void
    {
        Livewire::test(ElorusSettings::class)
            ->assertDontSee('secret-key-123')
            ->set('data.DefaultProductID', '10')
            ->call('save')->assertHasNoErrors();
        $this->assertSame(['secret-key-123', '10'], [DB::table('elorus_Settings')->value('apikey'), DB::table('elorus_Settings')->value('DefaultProductID')]);

        Livewire::test(ElorusSettings::class)->set('data.apikey', 'new-key')->call('save');
        $this->assertSame('new-key', DB::table('elorus_Settings')->value('apikey'));
        $this->assertSame(1, DB::table('elorus_Settings')->count());
    }

    public function test_elorus_contacts_are_read_from_the_api(): void
    {
        config(['elorus.enabled' => true]);
        Http::fake(['*contacts/*' => Http::response(['count' => 1, 'results' => [
            ['id' => '42', 'display_name' => 'Viator Inc', 'company' => 'Viator', 'vat_number' => 'US123', 'addresses' => [['address' => '1 Main St', 'city' => 'Boston', 'zip' => '02110', 'country' => 'US']]],
        ]])]);

        Livewire::test(ElorusContacts::class)->assertSee('Viator Inc')->assertSee('Boston');
        Http::assertSent(fn ($request) => $request->hasHeader('X-Elorus-Organization', '111') && $request->hasHeader('Authorization', 'Token secret-key-123'));
    }

    public function test_elorus_contacts_say_when_elorus_is_off(): void
    {
        config(['elorus.enabled' => false]);
        Http::fake();
        Livewire::test(ElorusContacts::class)->assertSee('switched off');
        Http::assertNothingSent();
    }

    public function test_field_settings_add_and_edit_drop_down_values(): void
    {
        Livewire::test(ManageOptionValues::class)
            ->callAction('create', data: ['Option' => 'CarType', 'Value' => 'MINIBUS', 'Color' => '#ffcc00'])
            ->assertHasNoActionErrors();
        $value = OptionValue::query()->where('Value', 'MINIBUS')->firstOrFail();

        Livewire::test(ManageOptionValues::class)
            ->callAction(TestAction::make('edit')->table($value), data: ['Color' => 'Orange'])
            ->assertHasNoActionErrors();
        $this->assertSame('Orange', $value->fresh()->Color);

        Livewire::test(ManageOptionValues::class)
            ->callAction(TestAction::make('edit')->table($value), data: ['Color' => 'red;}body{display:none'])
            ->assertHasActionErrors(['Color']);
    }
}
