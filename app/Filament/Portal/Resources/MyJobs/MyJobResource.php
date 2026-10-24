<?php

namespace App\Filament\Portal\Resources\MyJobs;

use App\Filament\Portal\Resources\MyJobs\Pages\ListMyJobs;
use App\Models\VTransfer;
use App\Services\Documents\Vouchers;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\View;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * PHPRunner "Εικόνα οδηγών" for drivers and coach companies (v_transfers where Username is
 * theirs): today's and the next days' jobs with the voucher, the tour description, the files
 * the office shares with drivers (Αρχεία), comments (Σχόλια) and the taxi receipt upload.
 */
class MyJobResource extends Resource
{
    protected static ?string $model = VTransfer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|UnitEnum|null $navigationGroup = 'Δρομολόγια';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Εικόνα οδηγών';

    protected static ?string $modelLabel = 'job';

    protected static ?string $pluralModelLabel = 'Εικόνα οδηγών';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'jobs';

    /** Taxi receipt file type (optionvalues), as PHPRunner's myat_transfers_files1_taxi. */
    public const TAXI_RECEIPT = 1363;

    public static function canViewAny(): bool
    {
        return Permissions::allows(auth()->user(), 'v_transfers', 'view');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('username', (string) auth()->user()?->username);
    }

    /**
     * The signed-in user's jobs from today on, read ONCE per request: v_transfers is a heavy view
     * (materialised on every query), so tab badges, the day summary and the cards' "n/total" all
     * work from this one list instead of querying the view again.
     */
    public static function upcoming(): Collection
    {
        static $cache = [];
        $user = (string) auth()->user()?->username;

        return $cache[$user] ??= VTransfer::query()->where('username', $user)->where('TransferDate', '>=', today())
            ->orderBy('TransferDate')->orderBy('TransferTime_sort')
            ->get(['TransferID', 'TransferDate', 'TransferTime', 'Pax', 'aid'])
            ->each(fn (VTransfer $job) => $job->setAttribute('day', $job->TransferDate?->toDateString()));
    }

    /** @return Collection<int, VTransfer> */
    public static function upcomingOn(Carbon $day): Collection
    {
        return self::upcoming()->where('day', $day->toDateString())->values();
    }

    /** Files of the job the office marked for drivers (PHPRunner myat_drivers_files datasource), once per job per request. */
    public static function driverFiles(VTransfer $job): Collection
    {
        static $cache = [];

        return $cache[$job->TransferID] ??= self::loadDriverFiles($job);
    }

    /** Comments on the job, counted once per request. */
    public static function commentCount(VTransfer $job): int
    {
        static $cache = [];

        return $cache[$job->TransferID] ??= DB::table('transfers_comms')->where('TransferID', $job->TransferID)->count();
    }

    private static function loadDriverFiles(VTransfer $job): Collection
    {
        $transferFiles = DB::table('myat_transfers_files as m')
            ->join('optionvalues as o', 'm.Type', '=', 'o.OptionID')
            ->where('m.TransferID', $job->TransferID)->where('o.DriverView', 1)
            ->get(['m.ID', 'm.Flnm', 'o.Value as type'])
            ->map(fn ($f) => ['name' => $f->Flnm, 'type' => $f->type, 'url' => route('transfer-files.download', $f->ID)]);

        $partnerFiles = DB::table('myat_partner_files as p')
            ->join('optionvalues as o', 'p.Type', '=', 'o.OptionID')
            ->where('p.cid', $job->cID_Customer)->where('o.PartnerView', 1)
            ->get(['p.ID', 'p.Flnm', 'o.Value as type'])
            ->map(fn ($f) => ['name' => $f->Flnm, 'type' => $f->type, 'url' => route('partner-files.download', $f->ID)]);

        return $transferFiles->concat($partnerFiles);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query) => $query->orderBy('TransferDate')->orderBy('TransferTime_sort'))
            ->paginated(false)
            ->poll('60s')
            ->columnManager(false)
            ->emptyStateHeading('Δεν υπάρχουν δρομολόγια')
            ->emptyStateDescription('Όταν σας ανατεθεί δρομολόγιο θα εμφανιστεί εδώ.')
            ->emptyStateIcon(Heroicon::OutlinedSun)
            // One card per job (big time, route, tap-to-call), two or three across on wide screens.
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->columns([
                View::make('filament.portal.job-card'),
            ])
            ->recordActions([
                Action::make('voucher')->label('Voucher')->icon(Heroicon::OutlinedDocumentText)->button()->size('sm')
                    ->visible(fn (VTransfer $record): bool => Vouchers::forLicence($record->VehicleLicence) !== null)
                    ->url(fn (VTransfer $record): string => route('vouchers.show', [Vouchers::forLicence($record->VehicleLicence), $record->TransferID]))
                    ->openUrlInNewTab(),
                Action::make('files')->button()->outlined()->size('sm')->label('Αρχεία')->icon(Heroicon::OutlinedPaperClip)->color('gray')
                    ->badge(fn (VTransfer $record): ?int => self::driverFiles($record)->count() ?: null)
                    ->visible(fn (VTransfer $record): bool => self::driverFiles($record)->isNotEmpty())
                    ->modalHeading('Αρχεία')
                    ->modalContent(fn (VTransfer $record) => new HtmlString(self::driverFiles($record)->map(fn (array $f): string => '<p><a class="text-primary-600 underline" target="_blank" href="'.e($f['url']).'">'.e($f['name'] ?: 'file').'</a> <span class="text-gray-500 text-sm">'.e((string) $f['type']).'</span></p>')->implode('')))
                    ->modalSubmitAction(false)->modalCancelActionLabel('Close'),
                Action::make('comments')->button()->outlined()->size('sm')->label('Σχόλια')->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)->color('gray')
                    ->badge(fn (VTransfer $record): ?int => self::commentCount($record) ?: null)
                    ->visible(fn (): bool => Permissions::allows(auth()->user(), 'transfers_comms', 'view'))
                    ->modalHeading('Σχόλια')
                    ->modalDescription(fn (VTransfer $record) => new HtmlString(DB::table('transfers_comms')->where('TransferID', $record->TransferID)->orderBy('CreatedDate')->get()
                        ->map(fn ($c): string => '<p><b>'.e($c->CreatedBy).'</b> <span class="text-gray-500">'.e(date('d/m H:i', strtotime((string) $c->CreatedDate))).'</span><br>'.nl2br(e($c->Notes)).'</p>')->implode('') ?: 'Κανένα σχόλιο ακόμα.'))
                    ->schema(fn (): array => Permissions::allows(auth()->user(), 'transfers_comms', 'add')
                        ? [Textarea::make('Notes')->label('Νέο σχόλιο')->rows(3)->maxLength(2000)] : [])
                    ->modalSubmitActionLabel('Save')
                    ->action(function (VTransfer $record, array $data): void {
                        if (blank($data['Notes'] ?? null) || ! Permissions::allows(auth()->user(), 'transfers_comms', 'add')) {
                            return;
                        }
                        DB::table('transfers_comms')->insert([
                            'TransferID' => $record->TransferID, 'Notes' => $data['Notes'], 'CreatedDate' => now(), 'CreatedBy' => auth()->user()->username,
                        ]);
                        Notification::make()->success()->title('Saved')->send();
                    }),
                Action::make('taxi')->button()->outlined()->size('sm')->label('Taxi')->icon(Heroicon::OutlinedReceiptPercent)->color('gray')
                    ->tooltip('Upload the taxi receipt')
                    ->visible(fn (): bool => Permissions::allows(auth()->user(), 'myat_transfers_files1_taxi', 'add'))
                    ->modalHeading('Taxi receipt')
                    ->schema([FileUpload::make('upload')->label('Receipt (photo or PDF)')->storeFiles(false)->maxSize(10 * 1024)
                        ->acceptedFileTypes(['image/*', 'application/pdf'])->required()])
                    ->action(function (VTransfer $record, array $data): void {
                        $upload = $data['upload'];
                        DB::table('myat_transfers_files')->insert([
                            'TransferID' => $record->TransferID, 'Flnm' => $upload->getClientOriginalName(), 'Attachment' => $upload->get(), 'Type' => self::TAXI_RECEIPT,
                        ]);
                        Notification::make()->success()->title('Receipt uploaded')->send();
                    }),
                Action::make('tour')->button()->outlined()->size('sm')->label('Περιγραφή')->icon(Heroicon::OutlinedMagnifyingGlass)->color('gray')
                    ->visible(fn (VTransfer $record): bool => filled($record->TourDescription))
                    ->modalHeading(fn (VTransfer $record): string => (string) $record->To)
                    ->modalContent(fn (VTransfer $record) => str(e((string) $record->TourDescription))->replace("\n", '<br>')->toHtmlString())
                    ->modalSubmitAction(false)->modalCancelActionLabel('Close'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListMyJobs::route('/')];
    }
}
