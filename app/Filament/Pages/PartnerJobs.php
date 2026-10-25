<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Transfers\TransferResource;
use App\Models\PartnerJob;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * PHPRunner's partner job lists (LS Tours, Beskos, Keep, TTA, Euro, Yomadic): jobs a
 * partner gave MyAT in their own app, to copy into our transfers, and the jobs MyAT gave
 * them ("Αναθέσεις σε ...").
 */
class PartnerJobs extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.partner-jobs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Partner jobs';

    protected static ?string $title = 'Partner jobs';

    protected Width|string|null $maxContentWidth = Width::Full;

    #[Url]
    public ?string $partner = null;

    /** 'for-us': jobs they gave us; 'by-us': jobs we gave them. */
    #[Url]
    public string $direction = 'for-us';

    /** @return array<string, string> partners this user may see, key => label */
    public static function partners(string $direction = 'for-us'): array
    {
        return collect(config('partners'))
            ->filter(fn (array $p, string $key): bool => Permissions::allows(auth()->user(), self::permissionTable($key, $direction), 'view'))
            ->map(fn (array $p): string => $p['label'])
            ->all();
    }

    /** PHPRunner tables: lstours_jobs / beskos_jobs ... and myat_jobs_ls / myat_jobs_beskos ... */
    private static function permissionTable(string $key, string $direction): string
    {
        return $direction === 'by-us' ? "myat_jobs_{$key}" : ($key === 'ls' ? 'lstours' : $key).'_jobs';
    }

    /**
     * New jobs per partner the user may see (PartnerJob::newCount), kept a minute: the dashboard
     * polls it and each partner is a query on another database. Unreadable partners are left out.
     *
     * @return array<string, int> partner key => new jobs
     */
    public static function newCounts(): array
    {
        $partners = array_keys(self::partners('for-us'));
        $compute = fn (): array => collect($partners)
            ->mapWithKeys(fn (string $key): array => [$key => PartnerJob::newCount($key)])
            ->filter(fn (?int $count): bool => $count !== null)
            ->all();

        return app()->runningUnitTests() ? $compute() : Cache::remember('myat:partner-new:'.implode(',', $partners), 60, $compute);
    }

    public static function canAccess(): bool
    {
        return self::partners() !== [] || self::partners('by-us') !== [];
    }

    public function mount(): void
    {
        $this->direction = in_array($this->direction, ['for-us', 'by-us'], true) ? $this->direction : 'for-us';
        if (! array_key_exists((string) $this->partner, self::partners($this->direction))) {
            $this->partner = array_key_first(self::partners($this->direction)) ?? array_key_first(self::partners('by-us'));
            $this->direction = array_key_exists((string) $this->partner, self::partners($this->direction)) ? $this->direction : 'by-us';
        }
    }

    public function show(string $partner, string $direction): void
    {
        abort_unless(array_key_exists($partner, self::partners($direction)), 403);
        $this->partner = $partner;
        $this->direction = $direction;
        $this->resetTable();
    }

    /** The direction switch: stays on the same partner when it exists in the other direction. */
    public function switchDirection(string $direction): void
    {
        $partners = self::partners($direction);
        abort_unless($partners !== [], 403);
        $this->show(array_key_exists((string) $this->partner, $partners) ? (string) $this->partner : (string) array_key_first($partners), $direction);
    }

    public function reachable(): bool
    {
        return $this->partner !== null && PartnerJob::reachable($this->partner);
    }

    public function table(Table $table): Table
    {
        $forUs = $this->direction === 'for-us';
        $partner = $this->partner;
        $reachable = $partner !== null && PartnerJob::reachable($partner);

        return $table
            ->query(fn (): Builder => match (true) {
                ! $reachable => PartnerJob::query()->fromSub(DB::query()->selectRaw('NULL AS TransferID')->whereRaw('0 = 1'), 'partner_jobs'),
                $forUs => PartnerJob::forUs($partner),
                default => PartnerJob::byUs($partner),
            })
            ->defaultSort(fn (Builder $query) => $forUs ? $query->orderByRaw("MyatStatus = 'New' DESC")->orderByDesc('TransferDate') : $query->orderByDesc('TransferDate'))
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->emptyStateHeading($reachable ? 'No jobs' : 'This partner\'s database cannot be read from here')
            ->columns(array_filter([
                TextColumn::make('TransferID')->label('Their ID')->sortable(),
                $forUs ? TextColumn::make('MyatStatus')->label(config('business.short_name').' status')->badge()->color(fn (?string $state): string => $state === 'New' ? 'warning' : 'success')
                    ->url(fn (PartnerJob $r): ?string => $r->OurTransferID ? TransferResource::getUrl('edit', ['record' => $r->OurTransferID]) : null) : null,
                TextColumn::make('Status')->badge()->color('gray'),
                TextColumn::make('TransferDate')->label('Date')->date('D d/m/Y')->sortable(),
                TextColumn::make('TransferTime')->label('Time'),
                TextColumn::make('TransferType')->label('Type'),
                TextColumn::make($forUs ? 'CarType' : 'CarTypeID')->label('Car type'),
                TextColumn::make('Pax'),
                TextColumn::make('From')->wrap()->searchable(),
                TextColumn::make('To')->wrap()->searchable(),
                TextColumn::make('Flight_Ship_Num_From')->label('Flight / ship')->wrap()->toggleable(),
                TextColumn::make('LeadName')->label('Lead name')->searchable(),
                $forUs ? TextColumn::make('LeadPhone')->label('Lead phone')->toggleable() : TextColumn::make('SupplierCode')->label('Sup code'),
                TextColumn::make('Assistant')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('PassengerName')->label('Passengers')->toggleable(isToggledHiddenByDefault: true)->wrap(),
                TextColumn::make('Notes')->wrap()->limit(100)->toggleable(),
                $forUs ? TextColumn::make('PurchasePrice')->label('Price')->money('EUR', locale: 'el') : TextColumn::make('SalePrice')->label('Price')->money('EUR', locale: 'el'),
            ]))
            ->filters($forUs ? [SelectFilter::make('MyatStatus')->label(config('business.short_name').' status')->options(['New' => 'New', 'Copied' => 'Copied'])] : [])
            ->toolbarActions($forUs ? [
                // PHPRunner "Μεταφορά στα δρομολόγια μου" (Move_To_My_Transfers).
                BulkAction::make('moveToMyTransfers')
                    ->label('Μεταφορά στα δρομολόγια μου')
                    ->icon(Heroicon::OutlinedArrowDownOnSquareStack)
                    ->requiresConfirmation()
                    ->modalDescription('The ticked jobs are copied into our transfers, with their files. Jobs already copied are skipped.')
                    ->visible(fn (): bool => (bool) auth()->user()?->may('transfers', 'add'))
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records) use ($partner): void {
                        $new = $records->where('MyatStatus', 'New');
                        foreach ($new as $job) {
                            DB::statement('CALL MoveToMyTransfers(?, ?)', [$job->TransferID, config("partners.{$partner}.code")]);
                        }
                        Notification::make()->success()
                            ->title($new->count().' δρομολόγια έχουν καταχωρηθεί')
                            ->body($records->count() > $new->count() ? ($records->count() - $new->count()).' were already copied.' : null)
                            ->send();
                    }),
            ] : []);
    }
}
