<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Transfers\TransferResource;
use App\Support\Permissions;
use BackedEnum;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
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
        return self::plan($this->day()) + [
            'editUrl' => TransferResource::getUrl('edit', ['record' => '__ID__']),
            'pdfUrl' => route('day-plan.pdf', ['date' => $this->day()->toDateString()]),
        ];
    }

    /** @return array{date: string, rows: list<array<string, mixed>>, jobs: list<array<string, mixed>>} the day's vehicles and jobs */
    public static function plan(CarbonImmutable $day): array
    {
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
            'jobs' => $jobs->map(fn ($j): array => self::job($j, $day, $tourHours))->values()->all(),
        ];
    }

    /**
     * The plan per vehicle, as the PDF prints it: rows with jobs (timed in order, then TBD), each job
     * flagged for overlaps on one of our vehicles and for more passengers than seats.
     *
     * @return list<array<string, mixed>>
     */
    public static function grouped(array $plan): array
    {
        return collect($plan['rows'])->map(function (array $row) use ($plan): array {
            $jobs = collect($plan['jobs'])->where('coach', $row['key']);
            $timed = $jobs->whereNotNull('start')->sortBy('start')->values();
            $timed = $timed->map(fn (array $j): array => $j + [
                'clash' => $row['kind'] === 'own' && $timed->contains(fn (array $o): bool => $o['id'] !== $j['id'] && $o['start'] < $j['end'] && $j['start'] < $o['end']),
                'over' => $row['seats'] && $j['pax'] > $row['seats'],
            ]);
            $tbd = $jobs->whereNull('start')->map(fn (array $j): array => $j + ['clash' => false, 'over' => $row['seats'] && $j['pax'] > $row['seats']]);

            return $row + ['jobs' => $timed->concat($tbd)->values()->all()];
        })->all();
    }

    /** The day's plan as an A4 landscape PDF (Dompdf, DejaVu Sans for Greek, like the vouchers). */
    public static function pdf(CarbonImmutable $day): string
    {
        $plan = self::plan($day);
        $rows = self::grouped($plan);
        $timed = collect($plan['jobs'])->whereNotNull('start');
        $from = (int) min(5 * 60, intdiv((int) ($timed->min('start') ?? 300), 60) * 60);
        $to = (int) max(24 * 60, (int) ceil(($timed->max('end') ?? 0) / 60) * 60);

        $html = view('documents.day-plan', [
            'day' => $day, 'rows' => $rows, 'from' => $from, 'to' => $to,
            'jobs' => count($plan['jobs']),
            'unassigned' => collect($plan['jobs'])->where('coach', '')->count(),
            'clashes' => collect($rows)->sum(fn (array $r): int => collect($r['jobs'])->where('clash', true)->count()),
        ])->render();

        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('DejaVu Sans');
        $options->setChroot(resource_path());
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /** One block: start and end in minutes from the day's midnight, or null start for TBD times. */
    private static function job(object $j, CarbonImmutable $day, array $tourHours): array
    {
        $start = $j->TransferTime_sort ? self::minutes((string) $j->TransferTime_sort) : null;
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
            $s = self::minutes((string) $j->Voucher_StartTime);
            $e = self::minutes((string) $j->Voucher_EndTime);
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

    private static function minutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time) + [0, 0]);

        return $h * 60 + $m;
    }
}
