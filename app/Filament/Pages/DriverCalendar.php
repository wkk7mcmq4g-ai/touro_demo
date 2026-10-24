<?php

namespace App\Filament\Pages;

use App\Support\Permissions;
use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * PHPRunner "Calendar" (the separate driver_cal/index.php page): the drivers' work schedule,
 * two months from the 1st of the current month, one digit per driver and day in `schedule`
 * (0 ΡΕΠΟ, 1 ΕΡΓΑΣΙΜΗ, 2 ΕΡΓΑΣΙΜΗ όχι Sprinter, 3 Sprinter, 5 ΣΥΝΤΗΡΗΣΗ, 8 ΑΝΑΡΡΩΤΙΚΗ, 9 ΑΔΕΙΑ).
 * Click a cell and type a digit; driver_cal/update_schedule.php's upsert does the saving.
 */
class DriverCalendar extends Page
{
    protected string $view = 'filament.pages.driver-calendar';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static string|UnitEnum|null $navigationGroup = 'Fleet & drivers';

    protected static ?int $navigationSort = 12;

    protected static ?string $navigationLabel = 'Calendar';

    protected static ?string $title = 'Calendar';

    protected Width|string|null $maxContentWidth = Width::Full;

    /** Months from the current one, as PHPRunner's ?offset=. */
    #[Url]
    public int $offset = 0;

    public const STATUSES = [
        0 => ['ΡΕΠΟ', '#FFC7CE'],
        1 => ['ΕΡΓΑΣΙΜΗ', '#D9F2D0'],
        2 => ['ΕΡΓΑΣΙΜΗ (ΟΧΙ SPRINTER)', '#FFEB9C'],
        3 => ['Sprinter', '#00B0F0'],
        5 => ['ΣΥΝΤΗΡΗΣΗ', '#D2691E'],
        9 => ['ΑΔΕΙΑ', '#b4a7d6'],
        8 => ['ΑΝΑΡΡΩΤΙΚΗ', '#c90076'],
    ];

    public static function canAccess(): bool
    {
        return Permissions::allows(auth()->user(), 'drivers', 'view');
    }

    public function canEditSchedule(): bool
    {
        return Permissions::allows(auth()->user(), 'drivers', 'edit');
    }

    public function move(int $by): void
    {
        $this->offset = $by === 0 ? 0 : $this->offset + $by;
    }

    public function start(): CarbonImmutable
    {
        return CarbonImmutable::now()->startOfMonth()->addMonthsNoOverflow($this->offset);
    }

    /** Everything the grid needs, built once per render and handed to Alpine. */
    public function calendar(): array
    {
        $start = $this->start();
        $end = $start->addMonthsNoOverflow(2)->subDay();

        $days = [];
        $months = [];
        foreach (CarbonPeriod::create($start, $end) as $day) {
            $key = $day->format('Y-m');
            $months[$key] ??= ['name' => $day->format('F Y'), 'count' => 0];
            $months[$key]['count']++;
            $days[] = [
                'date' => $day->toDateString(),
                'n' => $day->day,
                'w' => $day->format('D')[0],
                'last' => $day->day === $day->daysInMonth,
                'weekend' => $day->isWeekend(),
            ];
        }

        $drivers = DB::table('drivers')->where('Active', 1)->whereNotNull('CalOrder')->orderBy('CalOrder')
            ->get(['DriverID', 'shortname', 'Flag'])
            ->map(fn ($d): array => ['id' => (int) $d->DriverID, 'name' => (string) $d->shortname, 'flag' => (int) $d->Flag === 1])
            ->all();

        $cells = [];
        DB::table('schedule')->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('driver_id', array_column($drivers, 'id'))->whereNotNull('status')
            ->get(['driver_id', 'date', 'status'])
            ->each(function ($row) use (&$cells): void {
                $cells[$row->driver_id][$row->date] = (string) $row->status;
            });

        return [
            'days' => $days,
            'months' => array_values($months),
            'drivers' => $drivers,
            'cells' => (object) array_map(fn (array $c): object => (object) $c, $cells),
            'firstMonth' => $start->format('Y-m'),
            'today' => now()->toDateString(),
            'colors' => array_map(fn (array $s): string => $s[1], self::STATUSES),
        ];
    }

    /** update_schedule.php: upsert one cell. A blank cell is deleted (PHPRunner only cleared it on screen). */
    public function setStatus(int $driverId, string $date, string $status): bool
    {
        abort_unless($this->canEditSchedule(), 403);
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && preg_match('/^\d?$/', $status), 422);
        abort_unless(DB::table('drivers')->where('DriverID', $driverId)->exists(), 422);

        if ($status === '') {
            DB::table('schedule')->where('driver_id', $driverId)->where('date', $date)->delete();

            return true;
        }

        DB::statement(
            'INSERT INTO schedule (driver_id, `date`, status) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)',
            [$driverId, $date, (int) $status],
        );

        return true;
    }
}
