<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Reports\ListReport;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * PHPRunner "Van Taxi Report" (v_vantaxi SQL datasource): transfers per day and driver of the
 * minivan taxis (vehicle_assignments decide who drove), since 2026.
 */
class VanTaxiReport extends ListReport
{
    protected static string $permissionTable = 'v_vantaxi';

    protected static ?int $navigationSort = 14;

    protected static ?string $navigationLabel = 'Van Taxi Report';

    protected static ?string $title = 'Van Taxi Report';

    protected static ?string $slug = 'reports/van-taxi';

    protected function source(): Builder
    {
        $taxis = DB::table('vehicles')->where('VehicleType', 'Taxi (MiniVan)')->where('VehicleLicence', 'Taxi')->select(['VehicleName', 'VehicleID']);

        return DB::table('transfers as t')
            ->joinSub($taxis, 'vv', 'vv.VehicleName', '=', 't.CoachCompanyID')
            ->leftJoin('vehicle_assignments as a', fn ($j) => $j->on('t.TransferDate', '=', 'a.AssignmentDate')->on('vv.VehicleID', '=', 'a.VehicleID'))
            ->leftJoin('drivers as d', 'a.DriverID', '=', 'd.DriverID')
            ->whereYear('t.TransferDate', '>=', 2026)
            ->whereNotNull('d.Fullname')
            ->groupBy('t.TransferDate', 'd.Fullname')
            ->selectRaw('t.TransferDate, d.Fullname, LAST_DAY(t.TransferDate) AS RefPeriod, COUNT(*) AS transfers_count');
    }

    protected function defaultSort(): ?array
    {
        return ['TransferDate', 'desc'];
    }

    protected function thenSortBy(): array
    {
        return [['Fullname', 'asc']];
    }

    protected function columns(): array
    {
        return [
            TextColumn::make('TransferDate')->label('Transfer date')->date('D d/m/Y')->sortable(),
            TextColumn::make('Fullname')->label('Οδηγός')->searchable()->sortable(),
            TextColumn::make('transfers_count')->label('Transfers')->numeric()->alignEnd()->sortable()->summarize(Sum::make()->label('')),
        ];
    }

    protected function filters(): array
    {
        return [
            SelectFilter::make('RefPeriod')->label('Period')->multiple()
                ->options(fn (): array => collect(range(0, 23))->map(fn (int $i) => now()->startOfMonth()->subMonths($i))
                    ->filter(fn ($m) => $m->year >= 2026)
                    ->mapWithKeys(fn ($m): array => [$m->endOfMonth()->toDateString() => $m->format('m/Y')])->all()),
            SelectFilter::make('Fullname')->label('Οδηγός')->multiple()
                ->options(fn (): array => DB::table('drivers')->orderBy('Fullname')->pluck('Fullname', 'Fullname')->all()),
        ];
    }
}
