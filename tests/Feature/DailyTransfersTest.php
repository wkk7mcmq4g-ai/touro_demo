<?php

namespace Tests\Feature;

use App\Filament\Resources\DailyTransfers\Pages\ListDailyTransfers;
use App\Mail\DailyTransfers;
use App\Models\DailyRecipient;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/** "Αποστολή Transfers": the day's jobs to each driver and guide by e-mail or Telegram. */
class DailyTransfersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->emptyTables(['transfers', 'vehicles', 'drivers', 'vehicle_assignments', 'counterparties', 'emails_sent', 'telegram_messages', 'optionvalues']);
        DB::table('optionvalues')->insert(['Option' => 'EmailCC', 'Value' => 'office@example.test']);
        $this->actingAs($this->makeLegacyUser('Admin'));
    }

    private function driverWithJobs(string $name, ?int $telegram, int $jobs): void
    {
        $vehicle = DB::table('vehicles')->insertGetId(['VehicleName' => "VAN {$name}", 'VehicleLicence' => 'IKE'], 'VehicleID');
        $driver = DB::table('drivers')->insertGetId(['Fullname' => $name, 'Email' => strtolower($name).'@example.test', 'TelegramID' => $telegram, 'Active' => 1], 'DriverID');
        DB::table('vehicle_assignments')->insert(['VehicleID' => $vehicle, 'DriverID' => $driver, 'AssignmentDate' => Carbon::today()]);
        for ($i = 0; $i < $jobs; $i++) {
            DB::table('transfers')->insert(['ImportDate' => Carbon::today(), 'TransferDate' => Carbon::today(), 'TransferTime' => "1{$i}:00", 'TransferType' => 'Arrival',
                'From' => 'Airport', 'To' => 'Hotel', 'CoachCompanyID' => "VAN {$name}", 'Status' => 'Pending', 'LeadName' => "Lead {$name} {$i}",
                'PassengerName' => "Pax A\nPax B", 'LesseeName' => 'x']);
        }
    }

    public function test_drivers_with_jobs_today_are_listed_and_emailed(): void
    {
        Mail::fake();
        $this->driverWithJobs('Nikos', null, 2);
        $this->driverWithJobs('Kostas', null, 1);

        $page = Livewire::test(ListDailyTransfers::class)->assertSee('Nikos')->assertSee('Kostas')
            ->assertSeeHtml('data-myat-imm="enter"'); // full screen + zoom
        $recipients = DailyRecipient::source()->orderBy('Fullname')->get();
        $this->assertSame([1, 2], $recipients->pluck('Transfers')->all());

        $page->callTableBulkAction('emailSelected', $recipients, data: ['withPassengers' => true])->assertNotified();
        Mail::assertSent(DailyTransfers::class, 2);
        Mail::assertSent(DailyTransfers::class, fn (DailyTransfers $mail): bool => $mail->hasTo('nikos@example.test')
            && $mail->hasBcc('office@example.test') && $mail->jobs->count() === 2 && str_contains($mail->render(), 'Pax B')
            && str_starts_with($mail->envelope()->subject, 'Δρομολόγια ') && str_contains($mail->envelope()->subject, '2 transfers'));
    }

    public function test_read_is_recorded_like_phprunner(): void
    {
        $this->driverWithJobs('Nikos', null, 1);
        $nikos = DailyRecipient::source()->firstOrFail();

        Livewire::test(ListDailyTransfers::class)->callAction(TestAction::make('read')->table($nikos));
        $this->assertSame('Read', DailyRecipient::source()->firstOrFail()->SendStatus);
        $this->assertSame((int) $nikos->ID, (int) DB::table('emails_sent')->value('aID'));
    }

    public function test_telegram_is_sent_only_when_switched_on_and_logged(): void
    {
        $this->driverWithJobs('Nikos', 555, 1);
        $nikos = DailyRecipient::source()->firstOrFail();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 77]])]);

        config(['services.telegram.enabled' => false]);
        Livewire::test(ListDailyTransfers::class)->callAction(TestAction::make('telegram')->table($nikos));
        Http::assertNothingSent();

        config(['services.telegram.enabled' => true, 'services.telegram.token' => 'TEST']);
        Livewire::test(ListDailyTransfers::class)->callAction(TestAction::make('telegram')->table($nikos));
        Http::assertSent(fn ($request) => $request['chat_id'] == 555 && str_contains($request['text'], 'Lead Nikos 0'));
        $this->assertSame([77, 'out'], [(int) DB::table('telegram_messages')->value('telegram_msg_id'), DB::table('telegram_messages')->value('direction')]);
    }

    public function test_entry_users_cannot_send(): void
    {
        $this->actingAs($this->makeLegacyUser('Entry'));
        $this->get('/admin/send-transfers')->assertForbidden();
    }
}
