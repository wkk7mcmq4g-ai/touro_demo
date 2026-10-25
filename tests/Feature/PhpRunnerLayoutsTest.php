<?php

namespace Tests\Feature;

use App\Filament\Resources\Transfers\Pages\ListTransfers;
use App\Support\PhpRunnerLayouts;
use App\Support\TableLayouts;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** Each user's Transfers columns from the old app (order, shown, widths) copied to the new one. */
class PhpRunnerLayoutsTest extends TestCase
{
    private string $username;

    protected function setUp(): void
    {
        parent::setUp();
        $this->emptyTables(['MyAthensTransfersAPP_settings']);
        $user = $this->makeLegacyUser('Admin');
        $this->username = $user->username;
        $this->actingAs($user);

        DB::table('MyAthensTransfersAPP_settings')->insert([
            // what PHPRunner's paramsLogger writes for the transfers list
            ['TYPE' => 4, 'USERNAME' => $this->username, 'TABLENAME' => 'transfers', 'NAME' => null,
                'SEARCH' => json_encode(['TransferDate', 'TransferID', 'cID_Customer', 'LeadName', 'NoSuchField'])],
            ['TYPE' => 3, 'USERNAME' => $this->username, 'TABLENAME' => 'transfers', 'NAME' => '0',
                'SEARCH' => json_encode(['Pax', 'Notes'])],
            ['TYPE' => 2, 'USERNAME' => $this->username, 'TABLENAME' => 'transfers', 'NAME' => null,
                'SEARCH' => json_encode(['gridWidth' => 1800, 'thWidths' => ['headcell_field' => 64.4, 'headcell_field2' => 150, 'headcell_field99' => 80]])],
            ['TYPE' => 4, 'USERNAME' => 'someone_else', 'TABLENAME' => 'transfers', 'NAME' => null, 'SEARCH' => json_encode(['Pax'])],
        ]);
    }

    public function test_reads_the_three_phprunner_settings_in_new_column_names(): void
    {
        $layout = PhpRunnerLayouts::read(ListTransfers::class, $this->username);

        $this->assertSame(['TransferDate', 'TransferID', 'customer.SupplierName', 'LeadName', 'NoSuchField'], $layout['order']);
        $this->assertSame(['Pax', 'Notes'], $layout['hidden']);
        $this->assertSame(['TransferID' => 64, 'customer.SupplierName' => 150], $layout['widths']);
        $this->assertNull(PhpRunnerLayouts::read(ListTransfers::class, 'nobody'));
    }

    public function test_first_visit_copies_the_layout_and_it_is_saved_for_next_time(): void
    {
        $page = $this->get('/admin/transfers')->assertOk();

        $key = Livewire::test(ListTransfers::class)->instance()->getTableColumnsSessionKey();
        $columns = collect(session($key));
        $this->assertSame(['TransferDate', 'TransferID', 'customer.SupplierName', 'LeadName'], $columns->pluck('name')->take(4)->all());
        $this->assertFalse($columns->firstWhere('name', 'Pax')['isToggled']);
        $this->assertTrue($columns->firstWhere('name', 'SupplierCode')['isToggled']);
        $this->assertSame(['transfer-i-d' => 64, 'customer.-supplier-name' => 150], session('myat.widths')[ListTransfers::class]);

        $this->assertTrue(DB::table('MyAthensTransfersAPP_settings')->where('TYPE', TableLayouts::TYPE)
            ->where('USERNAME', $this->username)->where('TABLENAME', $key)->exists());
    }

    public function test_button_recopies_over_a_layout_made_in_the_new_app(): void
    {
        $key = Livewire::test(ListTransfers::class)->instance()->getTableColumnsSessionKey();
        DB::table('MyAthensTransfersAPP_settings')->insert(['TYPE' => TableLayouts::TYPE, 'USERNAME' => $this->username, 'TABLENAME' => $key, 'SEARCH' => '[]', 'NAME' => 'table layout']);
        session()->forget($key);

        $page = Livewire::test(ListTransfers::class);
        $this->assertNotSame('TransferDate', collect(session($key))->pluck('name')->first()); // not copied automatically

        $page->callAction(TestAction::make('phprunnerLayout'))->assertRedirect();
        $this->assertSame('TransferDate', collect(session($key))->pluck('name')->first());
    }

    public function test_admin_copies_every_users_layout_at_once(): void
    {
        $other = $this->makeLegacyUser('Entry');
        $third = $this->makeLegacyUser('Entry');
        DB::table('MyAthensTransfersAPP_settings')->insert([
            ['TYPE' => 4, 'USERNAME' => $other->username, 'TABLENAME' => 'transfers', 'NAME' => null, 'SEARCH' => json_encode(['LeadName', 'TransferID'])],
            ['TYPE' => 3, 'USERNAME' => $third->username, 'TABLENAME' => 'transfers', 'NAME' => '1', 'SEARCH' => json_encode(['Pax'])],
        ]);
        $key = Livewire::test(ListTransfers::class)->instance()->getTableColumnsSessionKey();
        // the third user already arranged the page in the new app
        DB::table('MyAthensTransfersAPP_settings')->insert(['TYPE' => TableLayouts::TYPE, 'USERNAME' => $third->username, 'TABLENAME' => $key, 'SEARCH' => '[]', 'NAME' => 'table layout']);

        Livewire::test(ListTransfers::class)
            ->callAction(TestAction::make('phprunnerLayoutAll'), ['overwrite' => false])
            ->assertNotified();

        $saved = fn (string $user) => json_decode((string) DB::table('MyAthensTransfersAPP_settings')
            ->where('TYPE', TableLayouts::TYPE)->where('USERNAME', $user)->where('TABLENAME', $key)->value('SEARCH'), true);
        $this->assertSame(['LeadName', 'TransferID'], collect($saved($other->username))->pluck('name')->take(2)->all());
        $this->assertSame([], $saved($third->username)); // kept: has a layout of their own
        $this->assertSame(['TransferDate', 'TransferID'], collect(session($key))->pluck('name')->take(2)->all()); // admin's own, also in this session

        // overwrite: the third user gets the old layout too
        Livewire::test(ListTransfers::class)->callAction(TestAction::make('phprunnerLayoutAll'), ['overwrite' => true]);
        $this->assertFalse(collect($saved($third->username))->firstWhere('name', 'Pax')['isToggled']);

        // the copied user sees it when they sign in
        $this->actingAs($other)->withSession([])->get('/admin/transfers')->assertOk();
        $this->assertSame(['LeadName', 'TransferID'], collect(session($key))->pluck('name')->take(2)->all());

        $this->actingAs($other);
        Livewire::test(ListTransfers::class)->assertActionHidden(TestAction::make('phprunnerLayoutAll'));
    }
}
