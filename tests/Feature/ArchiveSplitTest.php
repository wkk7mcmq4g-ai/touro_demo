<?php

namespace Tests\Feature;

use App\Filament\Resources\Archive\Pages\ListArchivedTransfers;
use App\Filament\Resources\Transfers\Pages\ListTransfers;
use App\Models\Transfer;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ArchiveSplitTest extends TestCase
{
    public function test_transfers_from_2025_on_and_earlier_ones_in_archive(): void
    {
        $this->actingAs($this->makeLegacyUser('Admin'));
        $make = fn (string $date): Transfer => Transfer::find(DB::table('transfers')->insertGetId(
            ['TransferDate' => $date, 'TransferType' => 'Arrival', 'From' => 'a', 'To' => 'b', 'Status' => 'Completed'], 'TransferID'));
        $old = $make('2024-12-31');
        $new = $make('2025-01-01');

        Livewire::test(ListTransfers::class)->set('activeTab', 'all')
            ->assertCanSeeTableRecords([$new])->assertCanNotSeeTableRecords([$old]);
        Livewire::test(ListArchivedTransfers::class)
            ->assertCanSeeTableRecords([$old])->assertCanNotSeeTableRecords([$new]);
    }
}
