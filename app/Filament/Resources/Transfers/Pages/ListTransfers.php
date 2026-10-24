<?php

namespace App\Filament\Resources\Transfers\Pages;

use App\Filament\Resources\Transfers\Actions\TransferActions;
use App\Filament\Resources\Transfers\TransferResource;
use App\Filament\Resources\Transfers\Widgets\TransferTotals;
use App\Models\Transfer;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Session;

class ListTransfers extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = TransferResource::class;

    /** Totals of what the list shows (count, sale, purchase, receivables, payables) above it. */
    protected function getHeaderWidgets(): array
    {
        return [TransferTotals::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    /** Use the whole screen: the list has PHPRunner's 57 columns. */
    protected Width|string|null $maxContentWidth = Width::Full;

    /** PHPRunner's inline edit: the configured columns become editable cells. */
    #[Session]
    public bool $inlineEditing = false;

    /** At most 50 editable rows per page, whatever was chosen before switching editing on. */
    public function getTableRecordsPerPage(): int|string|null
    {
        $perPage = parent::getTableRecordsPerPage();

        return $this->inlineEditing && ($perPage === 'all' || (int) $perPage > 50) ? 50 : $perPage;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggleInlineEditing')
                ->label(fn (): string => $this->inlineEditing ? 'Done editing' : 'Edit in list')
                ->icon(fn () => $this->inlineEditing ? Heroicon::OutlinedCheck : Heroicon::OutlinedPencilSquare)
                ->color(fn (): string => $this->inlineEditing ? 'success' : 'gray')
                ->visible(fn (): bool => (bool) auth()->user()?->may('transfers', 'edit'))
                ->action(function (): void {
                    $this->inlineEditing = ! $this->inlineEditing;
                    $this->resetTable();
                }),
            Action::make('resetColumnWidths')
                ->label('Reset column widths')
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->color('gray')
                ->tooltip('Drag the edge of a column title to resize it; double-click the edge to reset that column.')
                ->alpineClickHandler('window.myatResetColumnWidths?.()'),
            ActionGroup::make([TransferActions::incomingPayment()])
                ->label('Τιμολόγηση')
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('gray')
                ->button(),
            CreateAction::make()->label('New transfer')->icon(Heroicon::OutlinedPlus),
        ];
    }

    /**
     * The quick date buttons of the PHPRunner list (Today, Tomorrow, Next five days, ...).
     */
    public function getTabs(): array
    {
        $today = Carbon::today();

        return [
            'today' => $this->dateTab('Today', $today, $today, 'today'),
            'tomorrow' => $this->dateTab('Tomorrow', $today->copy()->addDay(), $today->copy()->addDay(), 'tomorrow'),
            'next5' => $this->dateTab('Next 5 days', $today, $today->copy()->addDays(4), 'next5'),
            'pending' => Tab::make('Pending')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('Status', 'Pending')
                    ->where('TransferDate', '>=', $today))
                ->badge(fn (): int => $this->tabCounts()['pending'])
                ->badgeColor('warning'),
            // "Εκκρεμότητες 7 ημερών": open issues (TBD details, issue flags) in the coming week.
            'issues' => Tab::make('Issues (7 days)')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->withOpenIssues()->where('TransferDate', '>=', $today)->where('TransferDate', '<=', $today->copy()->addDays(7)))
                ->badge(fn (): int => $this->tabCounts()['issues'])
                ->badgeColor('danger'),
            // PHPRunner's list starts at 2025; older transfers are in the archive.
            'all' => Tab::make('All')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('TransferDate', '>=', '2025-01-01')),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'today';
    }

    private function dateTab(string $label, Carbon $from, Carbon $until, string $key): Tab
    {
        $range = fn (Builder $query): Builder => $query
            ->where('TransferDate', '>=', $from)
            ->where('TransferDate', '<=', $until);

        return Tab::make($label)
            ->modifyQueryUsing($range)
            ->badge(fn (): int => $this->tabCounts()[$key]);
    }

    /**
     * The numbers on the tabs, worked out in two queries and kept for a minute: every click on the
     * page (filters, sorting, pages) redrew them, one count over all transfers per tab.
     *
     * @return array{today: int, tomorrow: int, next5: int, pending: int, issues: int}
     */
    private function tabCounts(): array
    {
        $today = Carbon::today();
        $compute = function () use ($today): array {
            [$d0, $d1, $d4, $d7] = [$today->toDateString(), $today->copy()->addDay()->toDateString(), $today->copy()->addDays(4)->toDateString(), $today->copy()->addDays(7)->toDateString()];
            $row = Transfer::query()->toBase()->where('TransferDate', '>=', $d0)
                ->selectRaw('SUM(TransferDate = ?) AS today, SUM(TransferDate = ?) AS tomorrow, SUM(TransferDate <= ?) AS next5, SUM(Status = ?) AS pending', [$d0, $d1, $d4, 'Pending'])
                ->first();

            return [
                'today' => (int) $row->today, 'tomorrow' => (int) $row->tomorrow, 'next5' => (int) $row->next5, 'pending' => (int) $row->pending,
                'issues' => Transfer::query()->withOpenIssues()->where('TransferDate', '>=', $d0)->where('TransferDate', '<=', $d7)->count(),
            ];
        };

        return app()->runningUnitTests() ? $compute() : Cache::remember('myat:transfer-tab-counts:'.$today->toDateString(), 60, $compute);
    }
}
