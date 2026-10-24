<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Transfers\Tables\TransferCellColors;
use App\Support\Permissions;
use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

    /** Bumped after the settings change so the grid is drawn again with the new order. */
    public int $revision = 0;

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

    /** Name colour before a colour was picked: PHPRunner's pink for Flag 1. */
    public const PINK = '#F2CEEF';

    /**
     * drivers.CalColor (VARCHAR(20) NULL) holds each driver's name colour. It was added for this page;
     * until it exists in a database the colour picker stays hidden and Flag 1 shows pink, as driver_cal did.
     */
    public static ?bool $colorColumn = null;

    public static function hasColorColumn(): bool
    {
        return static::$colorColumn ??= Schema::hasColumn('drivers', 'CalColor');
    }

    private static function colorOf(object $driver): ?string
    {
        $color = self::hasColorColumn() ? trim((string) ($driver->CalColor ?? '')) : '';

        if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) {
            return $color;
        }

        return (int) $driver->Flag === 1 ? self::PINK : null;
    }

    /**
     * Ρυθμίσεις: the active drivers in calendar order (drag to reorder), whether each is on the
     * calendar (CalOrder filled), the name colour (CalColor) and "Χωρίς αντιγραφή" (Flag = 1:
     * skipped by Copy_Driver_Schedule).
     */
    protected function getHeaderActions(): array
    {
        $color = self::hasColorColumn();

        return [
            $this->copyMonthAction(),
            Action::make('settings')->label('Ρυθμίσεις')->icon(Heroicon::OutlinedCog6Tooth)->color('gray')
                ->visible(fn (): bool => $this->canEditSchedule())
                ->slideOver()->modalWidth(Width::ThreeExtraLarge)->modalSubmitActionLabel('Αποθήκευση')
                ->modalHeading('Οδηγοί στο ημερολόγιο')
                ->modalDescription('Αλλάξτε τη σειρά σύροντας ή με τα βέλη. Εμφάνιση: ο οδηγός φαίνεται στο ημερολόγιο. '
                    .($color ? 'Χρώμα: φόντο στο όνομα. ' : '').'Χωρίς αντιγραφή: δεν αντιγράφεται το πρόγραμμά του στον επόμενο μήνα'
                    .($color ? '.' : ' (και ροζ όνομα).'))
                ->fillForm(fn (): array => ['drivers' => DB::table('drivers')->where('Active', 1)
                    ->orderByRaw('CalOrder IS NULL')->orderBy('CalOrder')->orderBy('shortname')
                    ->get(array_merge(['DriverID', 'shortname', 'Fullname', 'CalOrder', 'Flag'], $color ? ['CalColor'] : []))
                    ->map(fn ($d): array => [
                        'id' => (int) $d->DriverID,
                        'name' => filled($d->shortname) ? $d->shortname : $d->Fullname,
                        'shown' => $d->CalOrder !== null,
                        'color' => self::colorOf($d),
                        'nocopy' => (int) $d->Flag === 1,
                    ])->all()])
                ->schema([
                    Repeater::make('drivers')->hiddenLabel()
                        ->addable(false)->deletable(false)->reorderable()->reorderableWithButtons()
                        ->table(array_values(array_filter([
                            TableColumn::make('Οδηγός'),
                            TableColumn::make('Εμφάνιση'),
                            $color ? TableColumn::make('Χρώμα') : null,
                            TableColumn::make('Χωρίς αντιγραφή'),
                        ])))
                        ->schema(array_values(array_filter([
                            Hidden::make('id'),
                            TextInput::make('name')->hiddenLabel()->readOnly()->dehydrated(false),
                            Toggle::make('shown')->hiddenLabel(),
                            $color ? ColorPicker::make('color')->hiddenLabel()->regex('/^#[0-9a-fA-F]{3,8}$/') : null,
                            Toggle::make('nocopy')->hiddenLabel(),
                        ]))),
                ])
                ->action(function (array $data): void {
                    $this->saveSettings(array_values($data['drivers'] ?? []));
                    Notification::make()->success()->title('Αποθηκεύτηκε')->send();
                }),
        ];
    }

    /**
     * Αντιγραφή μήνα: the database's Copy_Driver_Schedule with the months chosen instead of fixed.
     * Day 1 goes to day 1 and so on; days the target month does not have are skipped.
     */
    private function copyMonthAction(): Action
    {
        $months = [];
        foreach (CarbonPeriod::create(now()->startOfMonth()->subMonths(12), '1 month', now()->startOfMonth()->addMonths(12)) as $m) {
            $months[$m->format('Y-m')] = $m->translatedFormat('F Y');
        }

        return Action::make('copyMonth')->label('Αντιγραφή μήνα')->icon(Heroicon::OutlinedDocumentDuplicate)->color('gray')
            ->visible(fn (): bool => $this->canEditSchedule())
            ->modalHeading('Αντιγραφή προγράμματος')
            ->modalDescription('Η 1η πάει στην 1η κ.ο.κ. Αντιγράφονται μόνο οι οδηγοί που φαίνονται στο ημερολόγιο.')
            ->modalSubmitActionLabel('Αντιγραφή')
            ->fillForm(fn (): array => [
                'from' => $this->start()->format('Y-m'),
                'to' => $this->start()->addMonthNoOverflow()->format('Y-m'),
                'drivers' => 'unflagged',
                'existing' => 'keep',
            ])
            ->schema([
                Select::make('from')->label('Από μήνα')->options($months)->required(),
                Select::make('to')->label('Σε μήνα')->options($months)->required()->different('from'),
                Radio::make('drivers')->label('Οδηγοί')->required()->options([
                    'all' => 'Όλοι',
                    'unflagged' => 'Όλοι εκτός από όσους έχουν «Χωρίς αντιγραφή» (Flag = 1)',
                ]),
                Radio::make('existing')->label('Μέρες που έχουν ήδη τιμή στον μήνα προορισμού')->required()->options([
                    'keep' => 'Να μείνουν όπως είναι',
                    'overwrite' => 'Να αντικατασταθούν',
                ]),
            ])
            ->action(function (array $data): void {
                $copied = $this->copyMonth($data['from'], $data['to'], $data['drivers'] === 'unflagged', $data['existing'] === 'overwrite');
                $this->offset = (int) CarbonImmutable::now()->startOfMonth()->diffInMonths(CarbonImmutable::parse($data['to'].'-01'));
                $this->revision++;
                Notification::make()->success()->title("Αντιγράφηκαν {$copied} μέρες")->send();
            });
    }

    /** @return int days written */
    public function copyMonth(string $from, string $to, bool $skipFlagged, bool $overwrite): int
    {
        abort_unless($this->canEditSchedule(), 403);
        abort_unless(preg_match('/^\d{4}-\d{2}$/', $from) && preg_match('/^\d{4}-\d{2}$/', $to) && $from !== $to, 422);

        $source = CarbonImmutable::parse($from.'-01');
        $target = CarbonImmutable::parse($to.'-01');

        $drivers = DB::table('drivers')->where('Active', 1)->whereNotNull('CalOrder')
            ->when($skipFlagged, fn ($q) => $q->where(fn ($q) => $q->whereNull('Flag')->orWhere('Flag', '<>', 1)))
            ->pluck('DriverID')->map(fn ($id): int => (int) $id)->all();

        $rows = DB::table('schedule')->whereIn('driver_id', $drivers)->whereNotNull('status')
            ->whereBetween('date', [$source->toDateString(), $source->endOfMonth()->toDateString()])
            ->get(['driver_id', 'date', 'status'])
            ->filter(fn ($r): bool => (int) substr($r->date, 8, 2) <= $target->daysInMonth)
            ->map(fn ($r): array => [(int) $r->driver_id, $target->addDays((int) substr($r->date, 8, 2) - 1)->toDateString(), (int) $r->status])
            ->values();

        if (! $overwrite) {
            $taken = DB::table('schedule')->whereIn('driver_id', $drivers)->whereNotNull('status')
                ->whereBetween('date', [$target->toDateString(), $target->endOfMonth()->toDateString()])
                ->get(['driver_id', 'date'])->map(fn ($r): string => $r->driver_id.'|'.$r->date)->flip();
            $rows = $rows->reject(fn (array $r): bool => $taken->has($r[0].'|'.$r[1]))->values();
        }

        foreach ($rows->chunk(500) as $chunk) {
            DB::statement(
                'INSERT INTO schedule (driver_id, `date`, status) VALUES '.implode(',', array_fill(0, $chunk->count(), '(?, ?, ?)'))
                    .' ON DUPLICATE KEY UPDATE status = VALUES(status)',
                $chunk->flatten()->all(),
            );
        }
        $written = $rows->count();

        return $written;
    }

    /** @param  list<array{id: int|string, shown: bool, color?: ?string, nocopy: bool}>  $rows  in calendar order */
    public function saveSettings(array $rows): void
    {
        abort_unless($this->canEditSchedule(), 403);

        $order = 0;
        foreach ($rows as $row) {
            $current = DB::table('drivers')->where('DriverID', (int) $row['id'])->first(['CalOrder', 'Flag']);
            if (! $current) {
                continue;
            }
            $changes = [
                'CalOrder' => ! empty($row['shown']) ? ++$order : null,
                'Flag' => ! empty($row['nocopy']) ? 1 : ((int) $current->Flag === 1 ? 0 : $current->Flag),
            ];
            if (self::hasColorColumn() && array_key_exists('color', $row)) {
                $color = trim((string) $row['color']);
                $changes['CalColor'] = preg_match('/^#[0-9a-fA-F]{3,8}$/', $color) ? $color : null;
            }
            DB::table('drivers')->where('DriverID', (int) $row['id'])->update($changes);
        }
        $this->revision++;
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
            ->get(array_merge(['DriverID', 'shortname', 'Flag'], self::hasColorColumn() ? ['CalColor'] : []))
            ->map(function ($d): array {
                $color = self::colorOf($d);

                return [
                    'id' => (int) $d->DriverID,
                    'name' => (string) $d->shortname,
                    'style' => $color ? 'background:'.$color.';color:'.TransferCellColors::textOn($color) : '',
                ];
            })
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
