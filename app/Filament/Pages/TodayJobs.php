<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\EditsTransferCells;
use App\Filament\Resources\Transfers\Actions\TransferActions;
use App\Filament\Resources\Transfers\TransferResource;
use App\Support\Permissions;
use App\Support\TransferCellEdit;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * «Σήμερα»: the day's jobs as cards, made for the phone. Time and route first, the driver big, and
 * one tap to call or WhatsApp the passenger and the driver. A job without a vehicle gets an «Όχημα»
 * button (the quick-edit window of the Transfers list). Cancelled jobs are left out.
 *
 * The driver: for one of our vehicles the driver assigned that day (vehicle_assignments), for a
 * partner's coach the partner (counterparties.CoachName), as in the driver SMS (Transfer::smsForDriver).
 */
class TodayJobs extends Page
{
    use EditsTransferCells;

    protected string $view = 'filament.pages.today-jobs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Σήμερα';

    protected static ?string $title = 'Σήμερα';

    protected static ?string $slug = 'today';

    #[Url]
    public ?string $date = null;

    public static function canAccess(): bool
    {
        return Permissions::allows(auth()->user(), 'transfers', 'view');
    }

    public function mount(): void
    {
        $this->date = $this->day()->toDateString();
    }

    public function day(): CarbonImmutable
    {
        try {
            return $this->date ? CarbonImmutable::createFromFormat('Y-m-d', $this->date)->startOfDay() : CarbonImmutable::today();
        } catch (\Throwable) {
            return CarbonImmutable::today();
        }
    }

    public function move(int $days): void
    {
        $this->date = $days === 0 ? CarbonImmutable::today()->toDateString() : $this->day()->addDays($days)->toDateString();
    }

    public function updatedDate(): void
    {
        $this->date = $this->day()->toDateString();
    }

    public function getTitle(): string
    {
        $day = $this->day();

        return match (true) {
            $day->isToday() => 'Σήμερα',
            $day->isTomorrow() => 'Αύριο',
            $day->isYesterday() => 'Χθες',
            default => $day->locale('el')->translatedFormat('l j/n'),
        };
    }

    public function canFix(): bool
    {
        return TransferCellEdit::allowed();
    }

    /** @return list<array<string, mixed>> the day's jobs in time order */
    public static function cards(CarbonImmutable $day): array
    {
        $ownDriver = 'FROM vehicle_assignments va INNER JOIN vehicles v ON va.VehicleID = v.VehicleID INNER JOIN drivers d ON d.DriverID = va.DriverID'
            .' WHERE va.AssignmentDate = t.TransferDate AND v.VehicleName = t.CoachCompanyID LIMIT 1';
        $partner = "FROM counterparties p WHERE p.CoachName = t.CoachCompanyID AND IFNULL(p.CoachName, '') <> '' LIMIT 1";

        $rows = DB::table('transfers as t')
            ->leftJoin('counterparties as c', 'c.CID', '=', 't.cID_Customer')
            ->where('t.TransferDate', $day->toDateString())
            ->whereRaw("IFNULL(t.Status, '') <> 'Cancelled'")
            ->orderBy('t.TransferTime_sort')->orderBy('t.TransferID')
            ->select(['t.TransferID', 't.TransferTime', 't.TransferType', 't.From', 't.To', 't.Pax', 't.CoachCompanyID', 't.Flight_Ship_Num_From',
                't.LeadName', 't.LeadPhone', 't.PassengerName', 't.Assistant', 't.Status', 't.DriverNotes', 'c.CounterShortName as Customer'])
            ->selectRaw("IFNULL((SELECT d.Fullname {$ownDriver}), (SELECT p.CounterShortName {$partner})) AS DriverName")
            ->selectRaw("IFNULL((SELECT d.MobilePhone {$ownDriver}), (SELECT p.Phones {$partner})) AS DriverPhone")
            ->selectRaw("(SELECT 1 {$ownDriver}) AS OwnVehicle")
            ->get();

        return $rows->map(fn ($r): array => [
            'id' => (int) $r->TransferID,
            'time' => trim((string) $r->TransferTime),
            'type' => (string) $r->TransferType,
            'from' => trim((string) $r->From),
            'to' => trim((string) $r->To),
            'flight' => trim((string) $r->Flight_Ship_Num_From),
            'pax' => $r->Pax !== null ? (int) $r->Pax : null,
            'customer' => (string) $r->Customer,
            'lead' => trim((string) ($r->LeadName ?: $r->PassengerName)),
            'leadPhone' => self::phone($r->LeadPhone),
            'coach' => trim((string) $r->CoachCompanyID),
            'driver' => trim((string) $r->DriverName),
            'driverPhone' => self::phone($r->DriverPhone),
            'ownVehicle' => (bool) $r->OwnVehicle,
            'assistant' => trim((string) $r->Assistant),
            'status' => (string) $r->Status,
            'notes' => trim((string) $r->DriverNotes),
            'url' => TransferResource::getUrl('edit', ['record' => $r->TransferID]),
        ])->all();
    }

    /**
     * A phone as tel: and WhatsApp links (first number when the field holds several), or null.
     *
     * @return array{shown: string, tel: string, wa: string}|null
     */
    public static function phone(?string $raw): ?array
    {
        $first = trim((string) preg_split('/[,;\/]| {2,}/', (string) $raw)[0]);
        $digits = preg_replace('/\D/', '', $first);
        if (strlen((string) $digits) < 6) {
            return null;
        }
        $international = strlen($digits) === 10 ? '30'.$digits : ltrim($digits, '0');

        return ['shown' => $first, 'tel' => 'tel:+'.$international, 'wa' => (string) TransferActions::whatsAppUrl($international, '')];
    }
}
