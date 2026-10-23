<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Reports\PivotReport;
use Illuminate\Database\Query\Builder;
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

    protected function dimensions(): array
    {
        return [
            'month' => ['label' => 'Μήνας', 'expr' => "DATE_FORMAT(ReferencePeriod, '%Y-%m')", 'axis' => 'rows'],
            'year' => ['label' => 'Έτος', 'expr' => 'YEAR(ReferencePeriod)', 'axis' => 'rows'],
            'CoachType' => ['label' => 'Coach type', 'expr' => 'CoachType', 'filter' => true],
            'CounterShortName' => ['label' => 'Πελάτης', 'expr' => 'CounterShortName', 'filter' => true],
            'VehicleName' => ['label' => 'Όχημα', 'expr' => 'VehicleName', 'filter' => true],
            'Cartype' => ['label' => 'Car type', 'expr' => 'Cartype', 'filter' => true],
            'TransferType' => ['label' => 'Transfer type', 'expr' => 'TransferType', 'filter' => true],
            'Status' => ['label' => 'Status', 'expr' => 'Status', 'filter' => true],
            'TobeInvoiced' => ['label' => 'Τύπος παραστ.', 'expr' => 'TobeInvoiced', 'filter' => true],
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
        return ['month', 'CoachType', 'count'];
    }
}
