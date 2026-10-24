<?php

namespace Tests\Feature;

use App\Filament\Widgets\Dashboard\TodayBoard;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTodayTest extends TestCase
{
    public function test_cancelled_jobs_are_hidden_until_switched_on(): void
    {
        $this->actingAs($this->makeLegacyUser('Admin'));
        $row = fn (string $to, string $status): array => ['TransferDate' => now()->toDateString(), 'TransferTime' => '10:00', 'TransferType' => 'Arrival', 'From' => 'Athens Airport', 'To' => $to, 'Status' => $status];
        DB::table('transfers')->insert([$row('Hotel Kept', 'Completed'), $row('Hotel Dropped', 'Cancelled')]);

        Livewire::test(TodayBoard::class)
            ->assertSee('Hotel Kept')->assertDontSee('Hotel Dropped')->assertSee('Ακυρωμένα (1)')
            ->set('showCancelled', true)
            ->assertSee('Hotel Kept')->assertSee('Hotel Dropped');
    }
}
