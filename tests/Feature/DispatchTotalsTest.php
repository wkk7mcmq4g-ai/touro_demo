<?php

namespace Tests\Feature;

use App\Filament\Resources\Dispatch\Pages\ListDispatch;
use App\Filament\Resources\Dispatch\Widgets\DispatchDayTotals;
use Livewire\Livewire;
use Tests\TestCase;

class DispatchTotalsTest extends TestCase
{
    public function test_dispatch_shows_day_totals_and_full_screen_controls(): void
    {
        $this->actingAs($this->makeLegacyUser('Admin'));

        Livewire::test(ListDispatch::class)->assertOk()->assertSeeHtml('data-myat-imm="enter"');
        Livewire::test(DispatchDayTotals::class, ['tableClass' => ListDispatch::class])->assertOk()->assertSeeHtml('myat-daytotals');
    }
}
