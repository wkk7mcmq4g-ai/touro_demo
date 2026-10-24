<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Reports\PivotReport;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** PHPRunner "Stats Report" (v_stats crosstab): transfers, income and turnover by period. */
class StatsReport extends PivotReport
{
    protected static string $permissionTable = 'v_stats Report';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Stats Report';

    protected static ?string $title = 'Stats Report';

    protected static ?string $slug = 'reports/stats';

    protected function source(): Builder
    {
        return DB::table('v_stats');
    }

    protected function dateColumn(): ?string
    {
        return 'ReferencePeriod';
    }

    /**
     * The months always across, under their year with a subtotal per year (George's choice; PHPRunner
     * had them down the side); partner (default), coach type, vehicle, car type or transfer type down.
     */
    protected function fixedCols(): ?string
    {
        return 'month';
    }

    protected function dimensions(): array
    {
        $date = fn ($v): string => Carbon::parse($v)->format('d/m/Y');

        return [
            'month' => ['label' => 'Μήνας', 'expr' => 'LAST_DAY(ReferencePeriod)', 'axis' => 'cols', 'format' => $date,
                'group' => fn (string $key): array => [substr($key, 0, 4), substr($key, 0, 4)]],
            'CounterShortName' => ['label' => 'Πελάτης', 'expr' => 'CounterShortName', 'axis' => 'rows', 'filter' => true],
            'CoachType' => ['label' => 'Coach type', 'expr' => 'CoachType', 'axis' => 'rows', 'filter' => true],
            'VehicleName' => ['label' => 'Όχημα', 'expr' => 'VehicleName', 'axis' => 'rows', 'filter' => true],
            'Cartype' => ['label' => 'Car type', 'expr' => 'Cartype', 'filter' => true],
            'TransferType' => ['label' => 'Transfer type', 'expr' => 'TransferType', 'filter' => true],
        ];
    }

    protected function measures(): array
    {
        return [
            'count' => ['label' => 'Transfers', 'expr' => 'Transfers_count'],
            'income' => ['label' => 'Έσοδο', 'expr' => '`Έσοδο`', 'money' => true],
            'turnover' => ['label' => 'Τζίρος', 'expr' => '`Τζίρος`', 'money' => true],
        ];
    }

    protected function defaults(): array
    {
        return ['CounterShortName', 'month', 'count'];
    }
}
