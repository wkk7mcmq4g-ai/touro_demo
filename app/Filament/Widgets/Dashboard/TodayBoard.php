<?php

namespace App\Filament\Widgets\Dashboard;

use App\Filament\Resources\Transfers\TransferResource;
use App\Support\DashboardData;
use App\Support\Lookups;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

/** Today's jobs as a timeline, with a "now" line between what is done and what is coming. */
class TodayBoard extends Widget
{
    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 8];

    protected string $view = 'filament.dashboard.today-board';

    protected function getViewData(): array
    {
        $jobs = DashboardData::todaysJobs();
        $now = Carbon::now()->format('H:i');

        // The first job not yet started: the "now" line goes above it, earlier jobs are greyed.
        $nextIndex = $jobs->search(fn ($job): bool => substr((string) $job->TransferTime, 0, 5) >= $now);

        return [
            'jobs' => $jobs,
            'now' => $now,
            'nextIndex' => $nextIndex === false ? null : $nextIndex,
            'nextId' => $nextIndex === false ? null : $jobs[$nextIndex]->TransferID,
            'colour' => fn (?string $type): string => Lookups::color('TransferType', $type) ?? '#94a3b8',
            'edit' => fn (int $id): ?string => TransferResource::canAccess() ? TransferResource::getUrl('edit', ['record' => $id]) : null,
            'all' => TransferResource::canAccess() ? TransferResource::getUrl('index', ['tab' => 'today']) : null,
        ];
    }
}
