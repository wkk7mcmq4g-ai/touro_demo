<?php

namespace App\Filament\Widgets\Dashboard;

use App\Support\DashboardData;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

/** The month's sales and what is owed either way; only for groups that may see the PHPRunner dashboard. */
class FinanceSnapshot extends Widget
{
    protected static ?int $sort = 5;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 4];

    protected string $view = 'filament.dashboard.finance-snapshot';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->may('Dashboard', 'view');
    }

    protected function getViewData(): array
    {
        $data = DashboardData::finance();
        $data['month'] = Carbon::today()->locale('el')->translatedFormat('F Y');
        $data['change'] = $data['mtdLast'] > 0 ? ($data['mtd'] - $data['mtdLast']) / $data['mtdLast'] * 100 : null;
        $data['max'] = (float) ($data['top']->max('total') ?: 1);

        return $data;
    }
}
