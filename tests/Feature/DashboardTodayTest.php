<?php

namespace Tests\Feature;

use App\Filament\Widgets\Dashboard\NeedsAttention;
use App\Filament\Widgets\Dashboard\TodayBoard;
use App\Support\DashboardData;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTodayTest extends TestCase
{
    public function test_the_dashboard_has_a_transfers_button(): void
    {
        $this->actingAs($this->makeLegacyUser('Entry'));

        $html = $this->get('/admin')->assertOk()->getContent();
        $header = substr($html, (int) strpos($html, 'fi-header'), 6000);
        $this->assertStringContainsString('href="'.e(\App\Filament\Resources\Transfers\TransferResource::getUrl()).'"', $header, 'Transfers button in the page header');
    }

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

    public function test_jobs_without_a_vehicle_today_and_tomorrow_are_separate_and_today_stands_out(): void
    {
        $this->emptyTables(['transfers']);
        $this->travelTo(now()->setTime(10, 0)); // fixed clock: "next pickup" depends on the time
        $this->actingAs($this->makeLegacyUser('Admin'));
        $row = fn (string $day, string $time, string $coach, string $status = 'Pending'): array => ['TransferDate' => $day, 'TransferTime' => $time,
            'TransferType' => 'Arrival', 'From' => 'Airport', 'To' => 'Hotel', 'Status' => $status, 'CoachCompanyID' => $coach];
        $today = now()->toDateString();
        $tomorrow = now()->addDay()->toDateString();
        DB::table('transfers')->insert([
            $row($today, '08:00', ''), $row($today, '13:00', ''), $row($today, '11:00', 'VITO'), $row($today, '12:00', '', 'Cancelled'),
            $row($tomorrow, '07:15', ''),
        ]);

        $this->assertSame(['count' => 2, 'next' => '13:00'], DashboardData::unassignedOn(now()));
        $this->assertSame(['count' => 1, 'next' => '07:15'], DashboardData::unassignedOn(now()->addDay()));

        Livewire::test(NeedsAttention::class)
            ->assertSeeInOrder(['Χωρίς όχημα · Σήμερα', 'Επόμενη παραλαβή χωρίς όχημα 13:00', 'Χωρίς όχημα · Αύριο', 'Πρώτη παραλαβή 07:15'])
            ->assertSeeHtml('is-urgent')
            ->assertSeeHtml('tab=tomorrow');

        DB::table('transfers')->where('TransferDate', $today)->update(['CoachCompanyID' => 'VITO']);
        Livewire::test(NeedsAttention::class)->assertSee('Όλα τα σημερινά έχουν όχημα')->assertDontSeeHtml('is-urgent');
    }
}
