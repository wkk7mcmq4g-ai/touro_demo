<?php

namespace App\Filament\Reports;

use App\Support\CsvDownload;
use App\Support\Permissions;
use App\Support\TablePresets;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Session;
use UnitEnum;

/**
 * A PHPRunner crosstab report: pick what goes down the rows and across the columns, and which
 * amount is added up; filter, read the totals, export to Excel (CSV).
 *
 * Subclasses describe the source, its dimensions and its amounts; the database does the sums.
 */
abstract class PivotReport extends Page
{
    protected string $view = 'filament.reports.pivot-report';

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected Width|string|null $maxContentWidth = Width::Full;

    /** PHPRunner table whose permission mask applies. */
    protected static string $permissionTable;

    /** Most columns shown across; the rest are added up under "Other". */
    private const MAX_COLUMNS = 40;

    /** @var array<string, mixed> */
    public ?array $data = [];

    abstract protected function source(): Builder;

    /**
     * `group` (rows only): rows are shown under a heading with its subtotal, e.g. months under their year
     * (PHPRunner's "stepped" layout); it maps a row key to [group key, group label].
     *
     * @return array<string, array{label: string, expr: string, axis?: 'rows'|'cols'|'both', format?: Closure(mixed): ?string, filter?: bool, group?: Closure(string): array{0: string, 1: string}}>
     */
    abstract protected function dimensions(): array;

    /** @return array<string, array{label: string, expr: string, money?: bool}> */
    abstract protected function measures(): array;

    /** @return array{0: string, 1: ?string, 2: string} default rows, columns (null = none), measure */
    abstract protected function defaults(): array;

    /** A dimension always shown across (the user cannot change the columns), or null. */
    protected function fixedCols(): ?string
    {
        return null;
    }

    /** Date column for the from/until filter, or null. */
    protected function dateColumn(): ?string
    {
        return null;
    }

    public static function canAccess(): bool
    {
        return Permissions::allows(auth()->user(), static::$permissionTable, 'view');
    }

    public function mount(): void
    {
        [$rows, $cols, $measure] = $this->defaults();
        $this->form->fill(['rows' => $rows, 'cols' => $cols, 'measure' => $measure]);
    }

    public function form(Schema $schema): Schema
    {
        $dims = $this->dimensions();
        $options = fn (string $axis): array => collect($dims)
            ->filter(fn (array $d): bool => in_array($d['axis'] ?? 'both', [$axis, 'both'], true))
            ->map(fn (array $d): string => $d['label'])->all();

        // The layout stays on top; the filters are in the side panel (filtersAction).
        return $schema->statePath('data')->components([
            Section::make()->compact()->schema([
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    Select::make('rows')->label('Γραμμές')->options($options('rows'))->required()->selectablePlaceholder(false)->live(),
                    $this->fixedCols()
                        ? TextInput::make('cols_fixed')->label('Στήλες')->default($dims[$this->fixedCols()]['label'])->formatStateUsing(fn (): string => $dims[$this->fixedCols()]['label'])->disabled()->dehydrated(false)
                        : Select::make('cols')->label('Στήλες')->options($options('cols'))->placeholder('— χωρίς —')->live(),
                    Select::make('measure')->label('Άθροισμα')->options(collect($this->measures())->map(fn (array $m): string => $m['label'])->all())
                        ->required()->selectablePlaceholder(false)->live(),
                ]),
            ]),
        ]);
    }

    /** @return list<Component> dates and the dimension filters, for the side panel */
    private function filterFields(): array
    {
        $dates = $this->dateColumn() ? [
            Grid::make(2)->schema([
                DatePicker::make('from')->label('Από'),
                DatePicker::make('until')->label('Έως'),
            ]),
        ] : [];

        $filters = collect($this->dimensions())->filter(fn (array $d): bool => $d['filter'] ?? false)
            ->map(fn (array $d, string $key) => Select::make("filter.{$key}")->label($d['label'])->multiple()->searchable()
                ->options(fn (): array => $this->distinct($key)))
            ->values()->all();

        return [...$dates, ...$filters];
    }

    /** Heat shading of the cells (per user, this page). */
    public bool $heatmap = true;

    /** Quick filter "only ≠ 0": leave out the records whose chosen amount is 0 (remembered per user and page). */
    #[Session]
    public bool $nonZero = false;

    /** Does this report offer the quick filter "only ≠ 0"? */
    public function hasNonZeroFilter(): bool
    {
        return false;
    }

    /**
     * The headline figures above the table: the total, the last period against the one before
     * (when rows are periods), and the biggest columns with their share.
     *
     * @param  array<string, mixed>  $r  result()
     * @return array{total: float, rows: int, cols: int, last: ?array{label: string, value: float, prev: ?float, change: ?float}, top: list<array{label: string, value: float, share: float}>, series: list<array{label: string, value: float}>}
     */
    public function insights(array $r): array
    {
        // The periods (down or across) make the trend; the other axis makes the ranking.
        $series = [];
        if ($r['colTime']) {
            foreach ($r['cols'] as $key => $label) {
                if ($key !== '__other') {
                    $series[] = ['label' => $label, 'value' => (float) ($r['colTotals'][$key] ?? 0)];
                }
            }
        } else {
            foreach ($r['rows'] as $key => $label) {
                $series[] = ['label' => $label, 'value' => (float) $r['rowTotals'][$key]];
            }
        }

        $last = null;
        if (($r['time'] || $r['colTime']) && count($series) >= 2) {
            $current = $series[count($series) - 1];
            $prev = $series[count($series) - 2]['value'] ?? null;
            $last = $current + ['prev' => $prev, 'change' => $prev ? ($current['value'] - $prev) / abs($prev) * 100 : null];
        }

        $top = [];
        [$totals, $labels] = $r['colTime'] ? [$r['rowTotals'], $r['rows']] : [$r['colTotals'], $r['cols']];
        if (($r['hasCols'] || $r['colTime']) && $r['total'] != 0) {
            arsort($totals);
            foreach (array_slice(array_filter($totals, fn ($v): bool => $v != 0), 0, 5, true) as $key => $value) {
                $top[] = ['label' => $labels[$key] ?? (string) $key, 'value' => (float) $value, 'share' => $value / $r['total'] * 100];
            }
        }

        return ['total' => (float) $r['total'], 'rows' => count($r['rows']), 'cols' => $r['hasCols'] ? count($r['cols']) : 0, 'last' => $last, 'top' => $top, 'series' => $series,
            'trendLabel' => $r['colTime'] ? $r['colLabel'] : $r['rowLabel'], 'rankLabel' => $r['colTime'] ? $r['rowLabel'] : $r['colLabel']];
    }

    /** @return list<array{key: string, label: string, value: string}> the filters set now, as chips */
    public function activeFilters(): array
    {
        $chips = [];
        foreach (['from' => 'Από', 'until' => 'Έως'] as $key => $label) {
            if (filled($this->data[$key] ?? null)) {
                $chips[] = ['key' => $key, 'label' => $label, 'value' => Carbon::parse($this->data[$key])->format('d/m/Y')];
            }
        }
        $dims = $this->dimensions();
        foreach ((array) ($this->data['filter'] ?? []) as $key => $values) {
            if (filled($values) && isset($dims[$key])) {
                $shown = array_map(fn ($v): string => $this->label($key, $v), (array) $values);
                $chips[] = ['key' => "filter.{$key}", 'label' => $dims[$key]['label'], 'value' => count($shown) > 2 ? $shown[0].', '.$shown[1].' +'.(count($shown) - 2) : implode(', ', $shown)];
            }
        }

        return $chips;
    }

    public function removeFilter(string $key): void
    {
        if (in_array($key, ['from', 'until'], true)) {
            $this->data[$key] = null;
        } elseif (str_starts_with($key, 'filter.')) {
            $this->data['filter'][substr($key, 7)] = [];
        }
    }

    /** How many filters are set (the badge on the Φίλτρα button). */
    private function activeFilterCount(): int
    {
        return (int) filled($this->data['from'] ?? null) + (int) filled($this->data['until'] ?? null)
            + collect($this->data['filter'] ?? [])->filter(fn ($v): bool => filled($v))->count();
    }

    /** @return array{rows: mixed, cols: mixed, measure: mixed, from: mixed, until: mixed, filter: array} */
    private function presetData(): array
    {
        return [
            'rows' => $this->data['rows'] ?? null, 'cols' => $this->data['cols'] ?? null, 'measure' => $this->data['measure'] ?? null,
            'from' => $this->data['from'] ?? null, 'until' => $this->data['until'] ?? null,
            'filter' => array_filter((array) ($this->data['filter'] ?? []), fn ($v): bool => filled($v)),
        ];
    }

    /** "Φίλτρα": dates and filters in a panel from the right, with saved filters (per user). */
    protected function filtersAction(): Action
    {
        $presets = fn (): array => collect(TablePresets::list(TablePresets::FILTERS, static::class))->pluck('name', 'id')->all();

        return Action::make('filters')->label('Φίλτρα')->icon(Heroicon::OutlinedFunnel)->color('gray')
            ->badge(fn (): ?int => $this->activeFilterCount() ?: null)
            ->slideOver()->modalWidth(Width::Medium)->modalHeading('Φίλτρα')
            ->modalSubmitActionLabel('Εφαρμογή')
            ->fillForm(fn (): array => ['preset' => null, 'save_as' => ''] + $this->presetData())
            ->schema(fn (): array => [
                Select::make('preset')->label('Αποθηκευμένα φίλτρα')->options($presets)->placeholder('— επιλέξτε —')
                    ->visible(fn (): bool => $presets() !== [])->live()
                    ->afterStateUpdated(function ($state, Set $set): void {
                        $preset = $state ? TablePresets::find(TablePresets::FILTERS, static::class, (int) $state) : null;
                        if (! $preset) {
                            return;
                        }
                        foreach (['from', 'until', 'filter'] as $key) {
                            $set($key, $preset['data'][$key] ?? ($key === 'filter' ? [] : null));
                        }
                    })
                    ->suffixAction(Action::make('deletePreset')->icon(Heroicon::OutlinedTrash)->color('danger')->tooltip('Διαγραφή')
                        ->visible(fn (Get $get): bool => filled($get('preset')))
                        ->action(function (Get $get, Set $set): void {
                            TablePresets::delete(TablePresets::FILTERS, static::class, (int) $get('preset'));
                            $set('preset', null);
                        })),
                ...$this->filterFields(),
                TextInput::make('save_as')->label('Αποθήκευση ως')->placeholder('Όνομα (προαιρετικό)')->maxLength(80)
                    ->helperText('Με όνομα, η εφαρμογή αποθηκεύει και τα φίλτρα μαζί με τις γραμμές, στήλες και άθροισμα. Το ίδιο όνομα τα αντικαθιστά.'),
            ])
            ->extraModalFooterActions(fn (): array => [
                Action::make('clearFilters')->label('Καθαρισμός')->color('danger')->link()
                    ->action(function (): void {
                        $this->data['filter'] = [];
                        $this->data['from'] = $this->data['until'] = null;
                    })
                    ->cancelParentActions(),
            ])
            ->action(function (array $data): void {
                $this->data['from'] = $data['from'] ?? null;
                $this->data['until'] = $data['until'] ?? null;
                $this->data['filter'] = (array) ($data['filter'] ?? []);
                $preset = filled($data['preset'] ?? null) ? TablePresets::find(TablePresets::FILTERS, static::class, (int) $data['preset']) : null;
                $name = trim((string) ($data['save_as'] ?? ''));

                if ($name !== '') {
                    // Saved with the layout shown now; the same name replaces it.
                    TablePresets::save(TablePresets::FILTERS, static::class, $name, $this->presetData());
                    Notification::make()->title('Τα φίλτρα αποθηκεύτηκαν')->success()->send();
                } elseif ($preset) {
                    // A saved preset picked as it is brings its layout back too.
                    foreach (['rows', 'cols', 'measure'] as $key) {
                        $this->data[$key] = $preset['data'][$key] ?? $this->data[$key] ?? null;
                    }
                }
            });
    }

    /** @return array<string, string> the distinct values of a dimension, for its filter */
    private function distinct(string $key): array
    {
        return array_map(fn ($k): string => $this->label($key, $k), $this->filterValues()[$key] ?? []);
    }

    /** @var array<string, array<string, string>>|null */
    private ?array $filterValuesMemo = null;

    /**
     * The values of every filter in one pass over the source (the views are heavy), kept for
     * 10 minutes, so the filter panel opens at once.
     *
     * @return array<string, array<string, string>> dimension => [value => value]
     */
    private function filterValues(): array
    {
        if ($this->filterValuesMemo !== null) {
            return $this->filterValuesMemo;
        }
        $compute = function (): array {
            $dims = collect($this->dimensions())->filter(fn (array $d): bool => $d['filter'] ?? false);
            if ($dims->isEmpty()) {
                return [];
            }
            $query = DB::query()->fromSub($this->source(), 's')->distinct();
            foreach ($dims->keys()->values() as $i => $key) {
                $query->selectRaw("{$dims[$key]['expr']} AS d{$i}");
            }
            $values = array_fill_keys($dims->keys()->all(), []);
            foreach ($query->get() as $row) {
                foreach ($dims->keys()->values() as $i => $key) {
                    $v = $row->{"d{$i}"};
                    if ($v !== null) {
                        $values[$key][(string) $v] = (string) $v;
                    }
                }
            }

            return array_map(function (array $v): array {
                ksort($v, SORT_NATURAL | SORT_FLAG_CASE);

                return $v;
            }, $values);
        };

        return $this->filterValuesMemo = app()->runningUnitTests() ? $compute() : Cache::remember('myat:pivot-filters:'.static::class, 600, $compute);
    }

    private function label(string $key, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '(κενό)';
        }
        $format = $this->dimensions()[$key]['format'] ?? null;

        return (string) ($format ? ($format($value) ?? $value) : $value);
    }

    /** @var array{0: string, 1: array}|null */
    private ?array $resultMemo = null;

    /**
     * The crosstab for what is set now: row and column headers, the cells and the totals. Kept for
     * 30 seconds: opening the filter panel or the export re-draws the page and would otherwise run
     * the same heavy query again.
     *
     * @return array<string, mixed>
     */
    public function result(): array
    {
        $key = 'myat:pivot:'.static::class.':'.md5(json_encode([$this->data, $this->fixedCols(), $this->hasNonZeroFilter() && $this->nonZero]));
        if ($this->resultMemo && $this->resultMemo[0] === $key) {
            return $this->resultMemo[1];
        }

        $compute = fn (): array => $this->computeResult();

        return ($this->resultMemo = [$key, app()->runningUnitTests() ? $compute() : Cache::remember($key, 30, $compute)])[1];
    }

    private function computeResult(): array
    {
        $dims = $this->dimensions();
        $measures = $this->measures();
        $fixed = $this->fixedCols();
        $rowKey = array_key_exists($this->data['rows'] ?? '', $dims) && ($this->data['rows'] ?? null) !== $fixed ? $this->data['rows'] : $this->defaults()[0];
        $colKey = $fixed ?? (array_key_exists($this->data['cols'] ?? '', $dims) ? $this->data['cols'] : null);
        $colTime = in_array($colKey, ['month', 'year'], true);
        $measureKey = array_key_exists($this->data['measure'] ?? '', $measures) ? $this->data['measure'] : $this->defaults()[2];

        $query = DB::query()->fromSub($this->source(), 's')
            ->selectRaw("{$dims[$rowKey]['expr']} AS r")
            ->selectRaw(($colKey ? $dims[$colKey]['expr'] : "''").' AS c')
            ->selectRaw("SUM({$measures[$measureKey]['expr']}) AS v")
            ->groupByRaw('r, c');

        if ($this->hasNonZeroFilter() && $this->nonZero) {
            $query->whereRaw("COALESCE({$measures[$measureKey]['expr']}, 0) <> 0");
        }

        foreach ($this->data['filter'] ?? [] as $key => $values) {
            if (array_key_exists($key, $dims) && filled($values)) {
                $query->whereIn(DB::raw($dims[$key]['expr']), (array) $values);
            }
        }
        if ($date = $this->dateColumn()) {
            $query->when($this->data['from'] ?? null, fn ($q, $d) => $q->whereDate($date, '>=', $d))
                ->when($this->data['until'] ?? null, fn ($q, $d) => $q->whereDate($date, '<=', $d));
        }

        $cells = [];
        $colTotals = [];
        foreach ($query->get() as $row) {
            $cells[(string) $row->r][(string) $row->c] = (float) $row->v;
            $colTotals[(string) $row->c] = ($colTotals[(string) $row->c] ?? 0) + (float) $row->v;
        }

        // Too many to show: the widest columns, or for periods the latest ones (the rest under one column).
        $colTime ? krsort($colTotals) : arsort($colTotals);
        if (count($colTotals) > self::MAX_COLUMNS) {
            $keep = array_slice(array_keys($colTotals), 0, self::MAX_COLUMNS - 1);
            foreach ($cells as $r => $cols) {
                foreach ($cols as $c => $v) {
                    if (! in_array((string) $c, $keep, true)) {
                        $cells[$r]['__other'] = ($cells[$r]['__other'] ?? 0) + $v;
                        unset($cells[$r][$c]);
                    }
                }
            }
            $colTotals = array_intersect_key($colTotals, array_flip($keep)) + ['__other' => array_sum(array_diff_key($colTotals, array_flip($keep)))];
        }
        $colKeys = array_keys($colTotals);
        sort($colKeys);
        if (in_array('__other', $colKeys, true)) {
            // Older periods go first, other values last.
            $colKeys = $colTime ? ['__other', ...array_diff($colKeys, ['__other'])] : [...array_diff($colKeys, ['__other']), '__other'];
        }

        // Periods across, grouped by year: [year => [column keys]] (for the year header and subtotals).
        $colGroups = [];
        if ($colTime && ($group = $dims[$colKey]['group'] ?? null)) {
            foreach ($colKeys as $c) {
                $colGroups[$c === '__other' ? 'Παλαιότερα' : $group((string) $c)[1]][] = (string) $c;
            }
        }

        ksort($cells);
        $rowTotals = array_map('array_sum', $cells);

        // Subtotal per group (year), in row order.
        $groups = [];
        if ($group = $dims[$rowKey]['group'] ?? null) {
            foreach ($cells as $r => $cols) {
                [$gKey, $gLabel] = $group((string) $r);
                $groups[$gKey] ??= ['label' => $gLabel, 'rows' => [], 'cells' => [], 'total' => 0.0];
                $groups[$gKey]['rows'][] = (string) $r;
                foreach ($cols as $c => $v) {
                    $groups[$gKey]['cells'][$c] = ($groups[$gKey]['cells'][$c] ?? 0) + $v;
                }
                $groups[$gKey]['total'] += $rowTotals[$r];
            }
        }

        return [
            'groups' => $groups,
            'rows' => collect(array_keys($cells))->mapWithKeys(fn ($r): array => [$r => $this->label($rowKey, $r)])->all(),
            'cols' => collect($colKeys)->mapWithKeys(fn ($c): array => [$c => $c === '__other' ? ($colTime ? 'Παλαιότερα' : 'Λοιπά') : ($colKey ? $this->label($colKey, $c) : $measures[$measureKey]['label'])])->all(),
            'colGroups' => $colGroups,
            'colTime' => $colTime,
            'cells' => $cells,
            'rowTotals' => $rowTotals,
            'colTotals' => $colTotals,
            'total' => array_sum($rowTotals),
            'money' => $measures[$measureKey]['money'] ?? false,
            'measure' => $measures[$measureKey]['label'],
            'rowLabel' => $dims[$rowKey]['label'],
            'colLabel' => $colKey ? $dims[$colKey]['label'] : null,
            'hasCols' => $colKey !== null,
            'time' => in_array($rowKey, ['month', 'year'], true),
            'maxCell' => max([0.0, ...array_map(fn (array $c): float => max([0.0, ...array_map('abs', $c)]), $cells)]),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->filtersAction(),
            Action::make('export')->label('Export')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                ->visible(fn (): bool => Permissions::allows(auth()->user(), static::$permissionTable, 'export'))
                ->action(function () {
                    $r = $this->result();
                    $columns = ['__row' => $r['rowLabel']] + $r['cols'] + ($r['hasCols'] ? ['__total' => 'Σύνολο'] : []);
                    $rows = [];
                    $line = fn (string $label, array $cells, float $total): array => ['__row' => $label] + $cells + ['__total' => $total];
                    if ($r['groups'] !== []) {
                        foreach ($r['groups'] as $g) {
                            $rows[] = $line($g['label'], $g['cells'], $g['total']);
                            foreach ($g['rows'] as $key) {
                                $rows[] = $line('    '.$r['rows'][$key], $r['cells'][$key] ?? [], $r['rowTotals'][$key]);
                            }
                        }
                    } else {
                        foreach ($r['rows'] as $key => $label) {
                            $rows[] = $line($label, $r['cells'][$key] ?? [], $r['rowTotals'][$key]);
                        }
                    }
                    $rows[] = ['__row' => 'Σύνολο'] + $r['colTotals'] + ['__total' => $r['total']];

                    return CsvDownload::make(Str::slug(static::getNavigationLabel()) ?: 'report', $columns, $rows);
                }),
        ];
    }
}
