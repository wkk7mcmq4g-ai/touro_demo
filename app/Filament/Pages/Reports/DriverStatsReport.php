<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Reports\PivotReport;
use App\Support\Lookups;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** PHPRunner "Στατιστικά Οδηγών" (v_drivers_stats2 crosstab): transfers per driver, vehicle and type. */
class DriverStatsReport extends PivotReport
{
    protected static string $permissionTable = 'v_drivers_stats2 Report';

    protected static ?int $navigationSort = 13;

    protected static ?string $navigationLabel = 'Στατιστικά Οδηγών';

    protected static ?string $title = 'Στατιστικά Οδηγών';

    protected static ?string $slug = 'reports/drivers';

    protected function source(): Builder
    {
        return DB::table('v_drivers_stats2');
    }

    protected function dateColumn(): ?string
    {
        return 'Period';
    }

    protected function dimensions(): array
    {
        return [
            'month' => ['label' => 'Period', 'expr' => "DATE_FORMAT(Period, '%Y-%m')"],
            'DriverID' => ['label' => 'Οδηγός', 'expr' => 'DriverID', 'filter' => true,
                'format' => fn ($v): ?string => Lookups::drivers()[$v] ?? null],
            'VehicleID' => ['label' => 'Όχημα', 'expr' => 'VehicleID', 'filter' => true,
                'format' => fn ($v): ?string => Lookups::vehicles()[$v] ?? null],
            'TransferType' => ['label' => 'Transfer type', 'expr' => 'TransferType', 'filter' => true],
        ];
    }

    protected function measures(): array
    {
        return ['transfers' => ['label' => 'Transfers', 'expr' => 'Transfers_r']];
    }

    protected function defaults(): array
    {
        return ['month', 'DriverID', 'transfers'];
    }
}
