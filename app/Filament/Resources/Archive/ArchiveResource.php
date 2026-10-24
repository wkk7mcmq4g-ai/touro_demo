<?php

namespace App\Filament\Resources\Archive;

use App\Filament\Concerns\UsesPhpRunnerPermissions;
use App\Filament\Resources\Archive\Pages\ListArchivedTransfers;
use App\Models\TransfersArchive;
use App\Support\ColorDot;
use App\Support\LegacyColumns;
use App\Support\Lookups;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use UnitEnum;

/** PHPRunner "Archive" (transfers_archive): older transfers, read-only as in PHPRunner. */
class ArchiveResource extends Resource
{
    use UsesPhpRunnerPermissions;

    protected static string $permissionTable = 'transfers_archive';

    protected static ?string $model = TransfersArchive::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Archive';

    /** List page title and breadcrumb: the same as the menu item. */
    protected static ?string $pluralModelLabel = 'Archive';

    protected static ?string $modelLabel = 'archived transfer';

    protected static ?string $slug = 'archive';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->select(LegacyColumns::except('transfers_archive', ['Attachments']));
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        $money = fn (string $name, string $label) => TextColumn::make($name)->label($label)->money('EUR', locale: 'el')->alignEnd()->sortable()->toggleable()
            ->summarize(Sum::make()->money('EUR', locale: 'el')->label(''));
        $text = fn (string $name, string $label, bool $hidden = false) => TextColumn::make($name)->label($label)->searchable()->sortable()->toggleable(isToggledHiddenByDefault: $hidden)->wrap();

        return $table
            ->defaultSort('TransferDate', 'desc')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->columns([
                TextColumn::make('TransferID')->label('ID')->sortable()->searchable(),
                TextColumn::make('cID_Customer')->label('Supplier')->formatStateUsing(fn ($state): ?string => Lookups::customers()[$state] ?? null),
                $text('SupplierCode', 'Sup Code'),
                TextColumn::make('TransferType')->label('Type')->formatStateUsing(fn ($state) => ColorDot::render($state, Lookups::color('TransferType', (string) $state))),
                TextColumn::make('TransferDate')->label('Transfer date')->date('d/m/Y')->sortable(),
                $text('TransferTime', 'Time'),
                $text('From', 'From'),
                $text('To', 'To'),
                $text('Pax', 'Pax'),
                $text('LeadName', 'Lead name'),
                $text('PassengerName', 'Passengers', true),
                $text('CoachCompanyID', 'Coach'),
                TextColumn::make('Status')->formatStateUsing(fn ($state) => ColorDot::render($state, Lookups::color('Status', (string) $state)))->sortable(),
                $money('PurchasePrice', 'Purchase'),
                $money('SalePrice', 'Sale'),
                $money('PriceRcvd', 'Received'),
                $text('SalesInvoiceNum', 'Sales invoice', true),
                $text('Notes', 'Notes', true),
            ])
            ->filters([
                Filter::make('dates')->columnSpanFull()->columns(2)->schema([
                    DatePicker::make('from')->label('Transfer date from'),
                    DatePicker::make('until')->label('until'),
                ])->query(fn (Builder $q, array $data): Builder => $q
                    ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('TransferDate', '>=', Carbon::parse($d)))
                    ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('TransferDate', '<=', Carbon::parse($d)))),
                SelectFilter::make('cID_Customer')->label('Supplier')->options(fn (): array => Lookups::customers())->multiple()->searchable(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListArchivedTransfers::route('/')];
    }
}
