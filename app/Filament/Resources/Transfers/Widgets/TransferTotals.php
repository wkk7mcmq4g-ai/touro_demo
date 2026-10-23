<?php

namespace App\Filament\Resources\Transfers\Widgets;

use App\Filament\Resources\Transfers\Pages\ListTransfers;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

/**
 * Totals of the transfers the list shows right now (tab, search, filters and column filters):
 * how many, sale, purchase, receivables (Assets) and payables (Liabilities). One query.
 */
class TransferTotals extends Widget
{
    use InteractsWithPageTable;

    protected string $view = 'filament.transfers.totals';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected function getTablePage(): string
    {
        return ListTransfers::class;
    }

    /** The column (Excel-style) filters are kept in the session; redraw when they change. */
    #[On('myat-table-filtered')]
    public function refreshTotals(): void {}

    protected function getViewData(): array
    {
        $row = $this->getPageTableQuery()->toBase()->reorder()
            ->selectRaw('COUNT(*) AS jobs, SUM(SalePrice) AS sale, SUM(PurchasePrice) AS purchase, SUM(Assets) AS assets, SUM(Liabilities) AS liabilities')
            ->first();

        $mayFinance = (bool) auth()->user()?->may('Dashboard', 'view');

        return [
            'jobs' => (int) ($row->jobs ?? 0),
            'money' => $mayFinance ? [
                'Πωλήσεις' => (float) ($row->sale ?? 0),
                'Αγορές' => (float) ($row->purchase ?? 0),
                'Απαιτήσεις' => (float) ($row->assets ?? 0),
                'Υποχρεώσεις' => (float) ($row->liabilities ?? 0),
            ] : [],
        ];
    }
}
