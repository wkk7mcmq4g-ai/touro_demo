<?php

namespace App\Filament\Widgets\Dashboard;

use App\Filament\Resources\Transfers\TransferResource;
use App\Support\DashboardData;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

class KpiTiles extends Widget
{
    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.dashboard.kpi-tiles';

    protected function getViewData(): array
    {
        $list = fn (string $tab): ?string => TransferResource::canAccess() ? TransferResource::getUrl('index', ['tab' => $tab]) : null;

        return [
            'today' => DashboardData::day(Carbon::today()),
            'tomorrow' => DashboardData::day(Carbon::tomorrow()),
            'tomorrowLabel' => Carbon::tomorrow()->locale('el')->translatedFormat('l j/n'),
            'pending' => DashboardData::pendingNextDays(),
            'issues' => DashboardData::issuesNextDays(),
            'urls' => ['today' => $list('today'), 'tomorrow' => $list('tomorrow'), 'pending' => $list('pending'), 'issues' => $list('issues')],
        ];
    }
}
