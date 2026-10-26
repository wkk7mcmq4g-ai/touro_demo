<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Reports\PivotReport;
use App\Support\Lookups;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Πωλήσεις ανά οδηγό: every transfer on one of our vehicles (Coach = the vehicle's name), with the
 * driver who had that vehicle that day in the vehicle assignments (Ανάθεση). Sales, transfers,
 * passengers and income by driver, day, month, year, vehicle or transfer type. Cancelled transfers
 * are left out; partner coaches have no driver of ours and are not counted.
 */
class DriverSalesReport extends PivotReport
{
    /** Same access as PHPRunner's "Στατιστικά Οδηγών" (no mask of its own in PHPRunner). */
    protected static string $permissionTable = 'v_drivers_stats2 Report';

    protected static ?int $navigationSort = 14;

    protected static ?string $navigationLabel = 'Πωλήσεις ανά οδηγό';

    protected static ?string $title = 'Πωλήσεις ανά οδηγό';

    protected static ?string $slug = 'reports/driver-sales';

    /** No assignment for that vehicle on that day. */
    public const NO_DRIVER = 'Χωρίς ανάθεση οδηγού';

    protected function source(): Builder
    {
        // One driver per vehicle and day (if two were entered by mistake, the first one).
        $assignments = DB::table('vehicle_assignments')
            ->selectRaw('VehicleID, AssignmentDate, MIN(DriverID) AS DriverID')
            ->groupBy('VehicleID', 'AssignmentDate');

        return DB::table('transfers as t')
            ->join('vehicles as vh', 'vh.VehicleName', '=', 't.CoachCompanyID')
            ->leftJoinSub($assignments, 'va', fn ($join) => $join->on('va.VehicleID', '=', 'vh.VehicleID')->on('va.AssignmentDate', '=', 't.TransferDate'))
            ->whereRaw("IFNULL(t.Status, '') <> 'Cancelled'")
            ->where('t.CoachCompanyID', '<>', '')
            ->selectRaw('t.TransferDate, t.TransferType, t.Status, vh.VehicleID, IFNULL(va.DriverID, 0) AS DriverID')
            ->selectRaw('IFNULL(t.SalePrice, 0) AS Sales, IFNULL(t.Pax, 0) AS Pax, IFNULL(t.Income, 0) AS Income, 1 AS Transfers');
    }

    protected function dateColumn(): ?string
    {
        return 'TransferDate';
    }

    protected function dimensions(): array
    {
        $date = fn ($v): string => Carbon::parse($v)->format('d/m/Y');
        $year = fn (string $key): array => [substr($key, 0, 4), substr($key, 0, 4)];

        return [
            'DriverID' => ['label' => 'Οδηγός', 'expr' => 'DriverID', 'filter' => true,
                'format' => fn ($v): ?string => (int) $v === 0 ? self::NO_DRIVER : (Lookups::drivers()[$v] ?? null)],
            'day' => ['label' => 'Ημέρα', 'expr' => 'TransferDate', 'axis' => 'rows', 'format' => $date, 'group' => $year],
            'month' => ['label' => 'Μήνας', 'expr' => 'LAST_DAY(TransferDate)', 'format' => $date, 'group' => $year],
            'year' => ['label' => 'Έτος', 'expr' => 'YEAR(TransferDate)'],
            'VehicleID' => ['label' => 'Όχημα', 'expr' => 'VehicleID', 'filter' => true,
                'format' => fn ($v): ?string => Lookups::vehicles()[$v] ?? null],
            'TransferType' => ['label' => 'Τύπος transfer', 'expr' => 'TransferType', 'filter' => true],
            'Status' => ['label' => 'Status', 'expr' => 'Status', 'filter' => true],
        ];
    }

    protected function measures(): array
    {
        return [
            'sales' => ['label' => 'Πωλήσεις', 'expr' => 'Sales', 'money' => true],
            'transfers' => ['label' => 'Transfers', 'expr' => 'Transfers'],
            'pax' => ['label' => 'Επιβάτες', 'expr' => 'Pax'],
            'income' => ['label' => 'Έσοδο (ολοκληρωμένα)', 'expr' => 'Income', 'money' => true],
        ];
    }

    protected function defaults(): array
    {
        return ['DriverID', 'month', 'sales'];
    }
}
