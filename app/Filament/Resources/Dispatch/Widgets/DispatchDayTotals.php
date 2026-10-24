<?php

namespace App\Filament\Resources\Dispatch\Widgets;

use App\Filament\Resources\Dispatch\Pages\ListDispatch;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Livewire\Attributes\On;

/**
 * Totals per day of what the dispatch list shows now (tab, search, filters, column filters):
 * jobs, passengers, drivers/partners, and how many our own cars run vs partner cars. One query.
 * Stays visible in full screen mode.
 */
class DispatchDayTotals extends Widget
{
    use InteractsWithPageTable;

    protected string $view = 'filament.dispatch.day-totals';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected function getTablePage(): string
    {
        return ListDispatch::class;
    }

    #[On('myat-table-filtered')]
    public function refreshTotals(): void {}

    protected function getViewData(): array
    {
        $rows = $this->getPageTableQuery()->toBase()->reorder()
            ->selectRaw("DATE(TransferDate) AS day, COUNT(*) AS jobs, SUM(CAST(Pax AS DECIMAL(10,0))) AS pax, COUNT(DISTINCT Fullname) AS drivers, SUM(Runby = 'MYAT' COLLATE utf8mb4_unicode_ci) AS own")
            ->groupByRaw('DATE(TransferDate)')
            ->orderBy('day')
            ->get();

        return [
            'days' => $rows->map(fn ($row): array => [
                'date' => Carbon::parse($row->day),
                'jobs' => (int) $row->jobs,
                'pax' => (int) $row->pax,
                'drivers' => (int) $row->drivers,
                'own' => (int) $row->own,
                'partner' => (int) $row->jobs - (int) $row->own,
            ])->all(),
        ];
    }
}
