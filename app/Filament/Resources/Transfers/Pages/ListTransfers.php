<?php

namespace App\Filament\Resources\Transfers\Pages;

use App\Filament\Concerns\CopiesPhpRunnerLayout;
use App\Filament\Concerns\PinsFlagsColumn;
use App\Filament\Resources\Transfers\Actions\TransferActions;
use App\Filament\Resources\Transfers\Tables\TransfersTable;
use App\Filament\Resources\Transfers\TransferResource;
use App\Filament\Resources\Transfers\Widgets\TransferTotals;
use App\Models\Transfer;
use App\Support\PhpRunnerLayouts;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
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
    use CopiesPhpRunnerLayout;
    use ExposesTableToWidgets;
    use PinsFlagsColumn;

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

    /**
     * Quick toggle "Ακυρωμένα": cancelled transfers shown (default) or left out, on every tab, in the
     * totals above the list and in the tab counts. Kept in the session directly (not #[Session]) so
     * the totals widget, which rebuilds this page to read its query, sees the same choice.
     */
    public const HIDE_CANCELLED = 'myat.transfers.hide_cancelled';

    public function showsCancelled(): bool
    {
        return ! session(self::HIDE_CANCELLED, false);
    }

    public function toggleCancelled(): void
    {
        session()->put(self::HIDE_CANCELLED, $this->showsCancelled());
        $this->resetPage();
    }

    /**
     * The toolbar's Ημερομηνίες menu (PHPRunner's "Φίλτρα" date shortcuts): over all transfers (tab
     * All), as the Period filter, so it shows as a filter chip and clears like any filter. '' clears.
     */
    public function applyPeriod(string $key): void
    {
        abort_unless($key === '' || array_key_exists($key, TransfersTable::PERIODS), 422);
        $this->activeTab = 'all';
        $this->tableFilters['period'] = ['values' => $key === '' ? [] : [$key]];
        $this->updatedTableFilters();
    }

    public function activePeriod(): ?string
    {
        $values = (array) ($this->tableFilters['period']['values'] ?? []);

        return count($values) === 1 ? (string) reset($values) : null;
    }

    /** Cancelled transfers in the open tab, for the toggle's label. */
    public function cancelledCount(): int
    {
        return parent::modifyQueryWithActiveTab(TransferResource::getEloquentQuery())->where('Status', 'Cancelled')->count();
    }

    protected function modifyQueryWithActiveTab(Builder $query, bool $isResolvingRecord = false): Builder
    {
        $query = parent::modifyQueryWithActiveTab($query, $isResolvingRecord);

        return $isResolvingRecord || $this->showsCancelled() ? $query : $query->where('Status', '<>', 'Cancelled');
    }

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
            ActionGroup::make([
                Action::make('phprunnerLayout')
                    ->label('Τις δικές μου στήλες')
                    ->icon(Heroicon::OutlinedUser)
                    ->visible(fn (): bool => self::isAdminLogin() && PhpRunnerLayouts::read(static::class, (string) auth()->user()?->username) !== null)
                    ->requiresConfirmation()
                    ->modalHeading('Στήλες όπως στο PHPRunner')
                    ->modalDescription('Η σειρά, οι εμφανείς στήλες και τα πλάτη παίρνουν τις ρυθμίσεις σας από την παλιά εφαρμογή. Οι τωρινές σας ρυθμίσεις εδώ αντικαθίστανται.')
                    ->modalSubmitActionLabel('Εφαρμογή')
                    ->action(function (): void {
                        PhpRunnerLayouts::apply($this, (string) auth()->user()?->username);
                        $this->redirect(static::getUrl(['tab' => $this->activeTab]));
                    }),
                // One-off for the switch-over: every user's old Transfers columns, without waiting for their first visit.
                Action::make('phprunnerLayoutAll')
                    ->label('Για όλους τους χρήστες')
                    ->icon(Heroicon::OutlinedUserGroup)
                    ->visible(fn (): bool => self::isAdminLogin())
                    ->modalHeading('Στήλες από PHPRunner για όλους')
                    ->modalDescription('Κάθε χρήστης παίρνει τη σειρά, τις εμφανείς στήλες και τα πλάτη που είχε στο PHPRunner. Όσοι είναι συνδεδεμένοι τώρα τα βλέπουν στην επόμενη σύνδεση.')
                    ->schema([
                        Toggle::make('overwrite')->label('Και όσοι έχουν ήδη αλλάξει στήλες εδώ')
                            ->helperText('Αλλιώς, όσοι έχουν δικές τους ρυθμίσεις στη νέα εφαρμογή μένουν όπως είναι.'),
                    ])
                    ->modalSubmitActionLabel('Αντιγραφή')
                    ->action(function (array $data): void {
                        $result = PhpRunnerLayouts::copyForAllUsers($this, (bool) ($data['overwrite'] ?? false));
                        $me = (string) auth()->user()?->username;
                        if (in_array($me, $result['copied'], true)) {
                            PhpRunnerLayouts::apply($this, $me); // keep this session from saving the old layout back
                        }
                        Notification::make()->success()
                            ->title('Αντιγράφηκαν '.count($result['copied']).' χρήστες')
                            ->body(collect([
                                $result['copied'] ? 'Αντιγράφηκαν: '.implode(', ', $result['copied']) : null,
                                $result['skipped'] ? 'Έμειναν ως είχαν: '.implode(', ', $result['skipped']) : null,
                            ])->filter()->implode("\n"))
                            ->persistent()->send();
                        if (in_array($me, $result['copied'], true)) {
                            $this->redirect(static::getUrl(['tab' => $this->activeTab]));
                        }
                    }),
            ])
                ->label('Στήλες από PHPRunner')
                ->icon(Heroicon::OutlinedArrowDownOnSquare)
                ->color('gray')
                ->visible(fn (): bool => self::isAdminLogin()) // the switch-over tool is only for the login "admin"
                ->button(),
            ActionGroup::make([TransferActions::incomingPayment()])
                ->label('Τιμολόγηση')
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('gray')
                ->visible(fn (): bool => (bool) config('transfers.incoming_payment')) // Demo: off, its only item is hidden
                ->button(),
            CreateAction::make()->label('New transfer')->icon(Heroicon::OutlinedPlus),
        ];
    }

    /**
     * The quick date buttons of the PHPRunner list (Today, Tomorrow, Next five days, ...).
     */
    /** The PHPRunner column copy (a switch-over tool) is only for the login called "admin". */
    private static function isAdminLogin(): bool
    {
        return strtolower((string) auth()->user()?->username) === 'admin';
    }

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
        $hide = ! $this->showsCancelled();
        $compute = function () use ($today, $hide): array {
            [$d0, $d1, $d4, $d7] = [$today->toDateString(), $today->copy()->addDay()->toDateString(), $today->copy()->addDays(4)->toDateString(), $today->copy()->addDays(7)->toDateString()];
            $row = Transfer::query()->toBase()->where('TransferDate', '>=', $d0)->when($hide, fn ($q) => $q->where('Status', '<>', 'Cancelled'))
                ->selectRaw('SUM(TransferDate = ?) AS today, SUM(TransferDate = ?) AS tomorrow, SUM(TransferDate <= ?) AS next5, SUM(Status = ?) AS pending', [$d0, $d1, $d4, 'Pending'])
                ->first();

            return [
                'today' => (int) $row->today, 'tomorrow' => (int) $row->tomorrow, 'next5' => (int) $row->next5, 'pending' => (int) $row->pending,
                'issues' => Transfer::query()->withOpenIssues()->where('TransferDate', '>=', $d0)->where('TransferDate', '<=', $d7)
                    ->when($hide, fn ($q) => $q->where('Status', '<>', 'Cancelled'))->count(),
            ];
        };

        return app()->runningUnitTests() ? $compute() : Cache::remember('myat:transfer-tab-counts:'.$today->toDateString().($hide ? ':nc' : ''), 60, $compute);
    }
}
