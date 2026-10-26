<?php

namespace Tests\Feature;

use App\Filament\Resources\Counterparties\Pages\CreateCounterparty;
use App\Filament\Resources\Counterparties\Pages\EditCounterparty;
use App\Filament\Resources\Counterparties\Pages\ListCounterparties;
use App\Filament\Resources\Counterparties\RelationManagers\PartnerFilesRelationManager;
use App\Filament\Resources\Transfers\Pages\CreateTransfer;
use App\Models\Counterparty;
use App\Models\MyatPartnerFile;
use App\Support\Lookups;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class CounterpartiesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->emptyTables(['counterparties', 'myat_partner_files', 'optionvalues', 'elorus_Settings', 'status_values']);
    }

    private function partner(array $attributes = []): Counterparty
    {
        $id = DB::table('counterparties')->insertGetId(array_merge(['CounterShortName' => 'Viator', 'SupplierName' => 'Viator', 'IsSupplier' => 1], $attributes), 'CID');

        return Counterparty::findOrFail($id);
    }

    public function test_create_and_edit_a_partner_like_phprunner(): void
    {
        $user = $this->makeLegacyUser('Admin');
        $this->actingAs($user);

        Livewire::test(CreateCounterparty::class)
            ->fillForm(['CounterShortName' => 'Blue Coaches', 'CoachName' => 'BLUE BUS', 'IsCoach' => true, 'Email' => 'ops@blue.test'])
            ->call('create')
            ->assertHasNoFormErrors();

        $partner = Counterparty::query()->where('CounterShortName', 'Blue Coaches')->firstOrFail();
        $this->assertSame([$user->username, 1, '1', 'BLUE BUS'], [$partner->CreatedBy, $partner->IsCoach, $partner->Active, $partner->CoachName]);
        $this->assertNotNull($partner->CreatedDate);
        // Blank NOT NULL columns get the database's own "not set" value.
        $this->assertSame(['NotRegistered', 'NotRegistered'], [$partner->ElorusID, $partner->SupplierName]);

        Livewire::test(EditCounterparty::class, ['record' => $partner->CID])
            ->assertSchemaStateSet(['ElorusID' => null])
            ->fillForm(['VAT' => '123456789', 'Active' => false])
            ->call('save')
            ->assertHasNoFormErrors();
        $partner->refresh();
        $this->assertSame(['123456789', '0', $user->username], [$partner->VAT, $partner->Active, $partner->LastUpdatedBy]);
        $this->assertSame('NotRegistered', $partner->ElorusID);
    }

    public function test_entry_users_have_no_partners_screen_as_in_phprunner(): void
    {
        $this->actingAs($this->makeLegacyUser('Entry'));
        $this->get('/admin/counterparties')->assertForbidden();

        Livewire::test(CreateTransfer::class)->assertActionHidden(TestAction::make('createOption')->schemaComponent('cID_Customer'));
    }

    public function test_list_tabs_by_role(): void
    {
        $this->actingAs($this->makeLegacyUser('Admin'));
        $supplier = $this->partner();
        $guide = $this->partner(['CounterShortName' => 'Maria', 'IsSupplier' => 0, 'IsAssistant' => 1, 'AssistantName' => 'Maria']);

        Livewire::test(ListCounterparties::class)->set('activeTab', 'guides')
            ->assertCanSeeTableRecords([$guide])->assertCanNotSeeTableRecords([$supplier]);
        Livewire::test(ListCounterparties::class)->set('activeTab', 'suppliers')
            ->assertCanSeeTableRecords([$supplier])->assertCanNotSeeTableRecords([$guide]);
    }

    public function test_partner_files_are_blobs_and_downloadable(): void
    {
        $this->actingAs($this->makeLegacyUser('Admin'));
        $partner = $this->partner();

        Livewire::test(PartnerFilesRelationManager::class, ['ownerRecord' => $partner, 'pageClass' => EditCounterparty::class])
            ->callAction(TestAction::make('create')->table(), data: ['upload' => UploadedFile::fake()->createWithContent('contract.pdf', "%PDF-1.4\n% contract\n")])
            ->assertHasNoFormErrors();

        $file = MyatPartnerFile::query()->where('cid', $partner->CID)->firstOrFail();
        $this->get(route('partner-files.download', $file->ID))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_add_to_elorus_stores_the_contact_id(): void
    {
        $this->actingAs($this->makeLegacyUser('Admin'));
        config(['elorus.enabled' => true, 'elorus.buttons' => true]);
        DB::table('elorus_Settings')->insert(['OrgID' => 'ORG1', 'apikey' => 'KEY1', 'DefaultProductID' => 'PRD1']);
        Http::fake(['api.elorus.com/v1.2/contacts/' => Http::response(['id' => '2208819599999999999'])]);
        $new = $this->partner(['CounterShortName' => 'New Hotel']);
        $known = $this->partner(['CounterShortName' => 'Known', 'ElorusID' => '1234567890']);

        Livewire::test(ListCounterparties::class)
            ->callTableBulkAction('addToElorus', [$new, $known], data: ['again' => false]);

        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r['first_name'] === 'New Hotel' && $r['last_name'] === 'New Hotel');
        $this->assertSame('2208819599999999999', $new->fresh()->ElorusID);
        $this->assertSame('1234567890', $known->fresh()->ElorusID);
    }

    public function test_new_customer_from_the_transfer_form(): void
    {
        $this->actingAs($this->makeLegacyUser('Admin'));

        $page = Livewire::test(CreateTransfer::class)
            ->callFormComponentAction('cID_Customer', 'createOption', data: ['CounterShortName' => 'Hotel Plaka', 'SupplierName' => 'Hotel Plaka', 'LesseName' => 'Plaka SA']);

        $customer = Counterparty::query()->where('SupplierName', 'Hotel Plaka')->firstOrFail();
        $this->assertSame(1, $customer->IsSupplier);
        $page->assertSchemaStateSet(['cID_Customer' => $customer->CID]);
        $this->assertArrayHasKey($customer->CID, Lookups::customers());
    }
}
