<?php

namespace App\Filament\Widgets;

use App\Models\Transfer;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;

class TransfersOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $today = Carbon::today();

        $active = fn (): Builder => Transfer::query()->where(fn (Builder $q) => $q->where('Status', '<>', 'Cancelled')->orWhereNull('Status'));

        $stats = [
            Stat::make('Today', $active()->whereDate('TransferDate', $today)->count())
                ->description('Transfers & tours, excluding cancelled')
                ->icon('heroicon-o-calendar'),
            Stat::make('Tomorrow', $active()->whereDate('TransferDate', $today->copy()->addDay())->count())
                ->description($today->copy()->addDay()->translatedFormat('l j M'))
                ->icon('heroicon-o-calendar-days'),
            Stat::make('Pending, next 7 days', Transfer::query()
                ->where('Status', 'Pending')
                ->whereBetween('TransferDate', [$today, $today->copy()->addDays(7)])
                ->count())
                ->description('Still to be completed')
                ->icon('heroicon-o-clock')
                ->color('warning'),
        ];

        // Money figures only for groups that may see the PHPRunner dashboard.
        if (auth()->user()?->may('Dashboard', 'view')) {
            $sales = (float) $active()
                ->whereBetween('TransferDate', [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()])
                ->sum('SalePrice');

            $stats[] = Stat::make('Sales this month', Number::currency($sales, 'EUR', 'el'))
                ->description($today->translatedFormat('F Y'))
                ->icon('heroicon-o-banknotes')
                ->color('success');
        }

        return $stats;
    }
}
