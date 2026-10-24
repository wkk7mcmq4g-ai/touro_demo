<?php

namespace Tests\Feature;

use App\Filament\Pages\Reports\TaxisReport;
use App\Filament\Resources\Counterparties\Pages\ListCounterparties;
use App\Filament\Resources\Transfers\Pages\EditTransfer;
use App\Filament\Resources\Transfers\RelationManagers\ChargesRelationManager;
use App\Models\Transfer;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ImmersiveEverywhereTest extends TestCase
{
    public function test_every_table_page_has_full_screen_but_not_relation_managers(): void
    {
        $this->actingAs($this->makeLegacyUser('Admin'));

        Livewire::test(ListCounterparties::class)->assertSeeHtml('data-myat-imm="enter"');
        Livewire::test(TaxisReport::class)->assertSeeHtml('data-myat-imm="enter"');

        $id = DB::table('transfers')->insertGetId(['TransferDate' => now()->toDateString(), 'TransferType' => 'Arrival', 'From' => 'a', 'To' => 'b'], 'TransferID');
        Livewire::test(ChargesRelationManager::class, ['ownerRecord' => Transfer::find($id), 'pageClass' => EditTransfer::class])
            ->assertDontSeeHtml('data-myat-imm="enter"');
    }
}
