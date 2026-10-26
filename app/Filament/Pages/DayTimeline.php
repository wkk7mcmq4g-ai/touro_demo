<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Transfers\TransferResource;
use App\Support\Permissions;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Ημερήσιο πλάνο: one row per vehicle (own vehicles with the day's driver, then outside coaches
 * with jobs that day), the day's jobs as blocks by time, so gaps and overlaps show at a glance.
 * Read only: nothing is changed from here; clicking a block opens the transfer.
 *
 * How long a job lasts: the voucher start/end times when someone set them, a tour's duration from
 * the tours list (matched by name), otherwise an estimate per transfer type (DURATIONS).
 */
class DayTimeline extends Page
{
    protected string $view = 'filament.pages.day-timeline';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Ημερήσιο πλάνο';

    protected static ?string $title = 'Ημερήσιο πλάνο';

    protected static ?string $slug = 'timeline';

    protected Width|string|null $maxContentWidth = Width::Full;

    /** Minutes per transfer type when nothing better is known; tours default to TOUR_MINUTES. */
    public const DURATIONS = ['Arrival' => 90, 'Departure' => 75, 'Transfer' => 60];

    public const DEFAULT_MINUTES = 60;

    public const TOUR_MINUTES = 240;

    /** The vehicles v_coach offers as coaches (PHPRunner): not sold, rented or private. */
    private const NOT_OUR_FLEET = ['Sold', 'Rental', 'PVT'];

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

    /** Everything the timeline draws, built once per render and handed to Alpine. */
    public function timeline(): array
    {
        $day = $this->day();

        $jobs = DB::table('transfers as t')
            ->leftJoin('counterparties as c', 'c.CID', '=', 't.cID_Customer')
            ->where('t.TransferDate', $day->toDateString())
            ->whereRaw("IFNULL(t.Status, '') <> 'Cancelled'")
            ->orderBy('t.TransferTime_sort')
            ->get(['t.TransferID', 't.TransferTime', 't.TransferTime_sort', 't.TransferType', 't.From', 't.To', 't.Pax', 't.CoachCompanyID',
                't.LeadName', 't.Flight_Ship_Num_From', 't.Status', 't.Voucher_StartDt', 't.Voucher_StartTime', 't.Voucher_EndDt', 't.Voucher_EndTime',
                'c.CounterShortName as Customer']);

        $tourHours = DB::table('tours')->where('TourDurationHours', '>', 0)->pluck('TourDurationHours', 'TourName')
            ->mapWithKeys(fn ($h, $name): array => [mb_strtolower(trim((string) $name)) => (float) $h])->all();

        $vehicles = DB::table('vehicles')->whereNotIn('VehicleLicence', self::NOT_OUR_FLEET)
            ->orderBy('VehicleName')->get(['VehicleID', 'VehicleName', 'Passengers', 'LicencePlate']);
        $drivers = DB::table('vehicle_assignments as va')->join('drivers as d', 'd.DriverID', '=', 'va.DriverID')
            ->where('va.AssignmentDate', $day->toDateString())
            ->pluck('d.Fullname', 'va.VehicleID')->all();

        $rows = [['key' => '', 'name' => 'Χωρίς όχημα', 'sub' => 'Δεν έχει ανατεθεί', 'kind' => 'none', 'seats' => null]];
        foreach ($vehicles as $v) {
            $rows[] = ['key' => (string) $v->VehicleName, 'name' => (string) $v->VehicleName,
                'sub' => $drivers[$v->VehicleID] ?? 'Χωρίς οδηγό', 'kind' => 'own', 'seats' => $v->Passengers ? (int) $v->Passengers : null,
                'noDriver' => ! isset($drivers[$v->VehicleID])];
        }
        $ours = array_column($rows, 'key');
        foreach ($jobs->pluck('CoachCompanyID')->map(fn ($c): string => trim((string) $c))->filter()->unique()->sort() as $coach) {
            if (! in_array($coach, $ours, true)) {
                $rows[] = ['key' => $coach, 'name' => $coach, 'sub' => 'Συνεργάτης', 'kind' => 'partner', 'seats' => null];
            }
        }

        return [
            'date' => $day->toDateString(),
            'rows' => $rows,
            'jobs' => $jobs->map(fn ($j): array => $this->job($j, $day, $tourHours))->values()->all(),
            'editUrl' => TransferResource::getUrl('edit', ['record' => '__ID__']),
        ];
    }

    /** One block: start and end in minutes from the day's midnight, or null start for TBD times. */
    private function job(object $j, CarbonImmutable $day, array $tourHours): array
    {
        $start = $j->TransferTime_sort ? $this->minutes((string) $j->TransferTime_sort) : null;
        $estimated = true;
        $length = self::DURATIONS[$j->TransferType] ?? self::DEFAULT_MINUTES;

        if ($j->TransferType === 'Tour') {
            $hours = $tourHours[mb_strtolower(trim((string) $j->To))] ?? null;
            $length = $hours ? (int) round($hours * 60) : self::TOUR_MINUTES;
            $estimated = $hours === null;
        }

        // Times someone set on the voucher win, when both ends are that day.
        if ($j->Voucher_StartTime && $j->Voucher_EndTime
            && (! $j->Voucher_StartDt || $j->Voucher_StartDt === $day->toDateString())
            && (! $j->Voucher_EndDt || $j->Voucher_EndDt === $day->toDateString())) {
            $s = $this->minutes((string) $j->Voucher_StartTime);
            $e = $this->minutes((string) $j->Voucher_EndTime);
            if ($e > $s) {
                $start ??= $s;
                $length = $e - $start;
                $estimated = false;
            }
        }

        return [
            'id' => (int) $j->TransferID,
            'coach' => trim((string) $j->CoachCompanyID),
            'start' => $start,
            'end' => $start === null ? null : $start + max(15, $length),
            'estimated' => $estimated,
            'time' => (string) $j->TransferTime,
            'type' => (string) $j->TransferType,
            'from' => (string) $j->From,
            'to' => (string) $j->To,
            'pax' => (int) $j->Pax,
            'lead' => (string) $j->LeadName,
            'flight' => (string) $j->Flight_Ship_Num_From,
            'customer' => (string) $j->Customer,
            'status' => (string) $j->Status,
        ];
    }

    private function minutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time) + [0, 0]);

        return $h * 60 + $m;
    }
}
