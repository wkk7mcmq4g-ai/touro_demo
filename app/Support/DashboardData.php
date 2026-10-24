<?php

namespace App\Support;

use App\Models\Transfer;
use App\Models\TransfersLoad;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The figures on the dashboard, straight from the transfers table (cancelled jobs left out).
 * Each widget asks for its own part, so a slow figure never holds up the others.
 */
class DashboardData
{
    /** Pax is text in the legacy table ("2", "TBD"): count only the digits. */
    private const PAX = "SUM(CAST(IF(`Pax` REGEXP '^[0-9]+$', `Pax`, '0') AS UNSIGNED))";

    public static function active(): Builder
    {
        return Transfer::query()->where(fn (Builder $q) => $q->where('Status', '<>', 'Cancelled')->orWhereNull('Status'));
    }

    /** Jobs with no vehicle given yet (PHPRunner keeps the vehicle's name in CoachCompanyID). */
    public static function unassigned(Builder $query): Builder
    {
        return $query->whereRaw("IFNULL(`CoachCompanyID`, '') = ''");
    }

    /** @return array{jobs: int, pax: int, done: int, unassigned: int} */
    public static function day(Carbon $date): array
    {
        $row = self::active()->where('TransferDate', $date)->toBase()
            ->selectRaw('COUNT(*) AS jobs, '.self::PAX." AS pax, SUM(`Status` = 'Completed') AS done, SUM(IFNULL(`CoachCompanyID`, '') = '') AS unassigned")
            ->first();

        return ['jobs' => (int) $row->jobs, 'pax' => (int) $row->pax, 'done' => (int) $row->done, 'unassigned' => (int) $row->unassigned];
    }

    public static function pendingNextDays(int $days = 7): int
    {
        $today = Carbon::today();

        return Transfer::query()->where('Status', 'Pending')
            ->whereBetween('TransferDate', [$today, $today->copy()->addDays($days)])->count();
    }

    public static function issuesNextDays(int $days = 7): int
    {
        $today = Carbon::today();

        return Transfer::query()->withOpenIssues()
            ->where('TransferDate', '>=', $today)->where('TransferDate', '<=', $today->copy()->addDays($days))->count();
    }

    public static function unassignedNextHours(): int
    {
        return self::unassigned(self::active())
            ->whereBetween('TransferDate', [Carbon::today(), Carbon::tomorrow()])->count();
    }

    public static function toSort(): int
    {
        return TransfersLoad::query()->where('Loaded', 0)->count();
    }

    /** Today's jobs in time order, with the vehicle's current driver. */
    public static function todaysJobs(): Collection
    {
        $driver = DB::table('vehicle_assignments as va')
            ->join('vehicles as v', 'v.VehicleID', '=', 'va.VehicleID')
            ->join('drivers as d', 'd.DriverID', '=', 'va.DriverID')
            ->whereColumn('v.VehicleName', 'transfers.CoachCompanyID')
            ->orderByDesc('va.AssignmentDate')->limit(1)
            ->select('d.Fullname');

        return Transfer::query()
            ->with('customer:CID,SupplierName')
            ->where('TransferDate', Carbon::today())
            ->orderBy('TransferTime_sort')->orderBy('TransferID')
            ->select(['TransferID', 'TransferTime', 'TransferType', 'From', 'To', 'Pax', 'CoachCompanyID', 'Status', 'LeadName', 'cID_Customer', 'Flight_Ship_Num_From'])
            ->selectSub($driver, 'DriverName')
            ->limit(200)
            ->get();
    }

    /**
     * Jobs and passengers per day around today.
     *
     * @return list<array{date: Carbon, jobs: int, pax: int}>
     */
    public static function volume(int $before = 6, int $after = 7): array
    {
        $from = Carbon::today()->subDays($before);
        $to = Carbon::today()->addDays($after);

        $rows = self::active()->whereBetween('TransferDate', [$from, $to])->toBase()
            ->selectRaw('DATE(`TransferDate`) AS d, COUNT(*) AS jobs, '.self::PAX.' AS pax')
            ->groupByRaw('DATE(`TransferDate`)')->get()->keyBy('d');

        $days = [];
        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            $row = $rows[$date->toDateString()] ?? null;
            $days[] = ['date' => $date->copy(), 'jobs' => (int) ($row->jobs ?? 0), 'pax' => (int) ($row->pax ?? 0)];
        }

        return $days;
    }

    /**
     * sales: the whole month as booked; mtd/mtdLast: the 1st to today against the same days of
     * last month, for the trend.
     *
     * @return array{sales: float, mtd: float, mtdLast: float, receivable: float, payable: float, top: Collection}
     */
    public static function finance(): array
    {
        $today = Carbon::today();
        $sales = fn (Carbon $from, Carbon $to): float => (float) self::active()->whereBetween('TransferDate', [$from, $to])->sum('SalePrice');

        // Month to date against the same days of last month.
        $lastFrom = $today->copy()->subMonthNoOverflow()->startOfMonth();
        $lastTo = $lastFrom->copy()->addDays($today->day - 1)->min($lastFrom->copy()->endOfMonth());

        $top = self::active()
            ->whereBetween('TransferDate', [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()])
            ->join('counterparties', 'counterparties.CID', '=', 'transfers.cID_Customer')
            ->toBase()
            ->selectRaw('counterparties.SupplierName AS name, SUM(transfers.SalePrice) AS total, COUNT(*) AS jobs')
            ->groupBy('counterparties.SupplierName')->orderByDesc('total')->limit(5)->get();

        return [
            'sales' => $sales($today->copy()->startOfMonth(), $today->copy()->endOfMonth()),
            'mtd' => $sales($today->copy()->startOfMonth(), $today->copy()),
            'mtdLast' => $sales($lastFrom, $lastTo),
            'receivable' => (float) Transfer::query()->sum('Assets'),
            'payable' => (float) Transfer::query()->sum('Liabilities'),
            'top' => $top,
        ];
    }
}
