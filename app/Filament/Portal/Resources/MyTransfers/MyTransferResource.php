<?php

namespace App\Filament\Portal\Resources\MyTransfers;

use App\Enums\UserGroup;
use App\Filament\Concerns\UsesPhpRunnerPermissions;
use App\Filament\Portal\Resources\MyTransfers\Pages\EditMyTransfer;
use App\Filament\Portal\Resources\MyTransfers\Pages\ListMyTransfers;
use App\Filament\Portal\Resources\MyTransfers\Pages\ViewMyTransfer;
use App\Filament\Resources\Transfers\RelationManagers\PriceLinesRelationManager;
use App\Models\Transfer;
use App\Support\Lookups;
use App\Support\Portal;
use App\Support\TransferRules;
use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * PHPRunner "Τα δρομολόγια μου" (transfers list1 / edit1 / view1, and the _hotel pages for
 * hotels): the transfers of the signed-in partner, supplier or hotel, and nobody else's.
 */
class MyTransferResource extends Resource
{
    use UsesPhpRunnerPermissions;

    protected static string $permissionTable = 'transfers';

    protected static ?string $model = Transfer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Δρομολόγια';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Τα δρομολόγια μου';

    protected static ?string $modelLabel = 'transfer';

    protected static ?string $pluralModelLabel = 'Τα δρομολόγια μου';

    /** Greek labels keep their case ("Τα δρομολόγια μου", not "Τα Δρομολόγια Μου"). */
    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'transfers';

    protected static ?string $recordTitleAttribute = 'TransferID';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('cID_Customer', Portal::scopeId());
    }

    /**
     * PHPRunner's rule (transfers list events + the datasource's EditLimit): partners, suppliers and
     * hotels may change a transfer only until 48 hours before the pickup; when the pickup time is not
     * a time (e.g. "TBD"), only while the transfer date is more than 2 days away. Same SQL as PHPRunner.
     */
    public static function canEdit(Model $record): bool
    {
        if (! static::allows('update')) {
            return false;
        }
        if (! in_array(auth()->user()?->group(), [UserGroup::Partner, UserGroup::Supplier, UserGroup::SupplierHotel], true)) {
            return true;
        }

        // Asked once per transfer and request (the list asks for every row).
        $key = 'myat.editLimit.'.$record->getKey();
        if (request()->attributes->has($key)) {
            return request()->attributes->get($key);
        }

        $allowed = (bool) DB::selectOne(
            "SELECT if(ISNULL(subtime(str_to_date(concat(`TransferDate`, ' ', TransferTime), '%Y-%m-%d %H:%i:%s'), '48:00')),
                DATEDIFF(TransferDate, CURRENT_DATE) > 2,
                ifnull(CURRENT_TIMESTAMP() < subtime(str_to_date(concat(`TransferDate`, ' ', TransferTime), '%Y-%m-%d %H:%i:%s'), '48:00'), 1)) AS EditLimit
            FROM transfers WHERE TransferID = ?",
            [$record->getKey()],
        )?->EditLimit;
        request()->attributes->set($key, $allowed);

        return $allowed;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    private static function isHotel(): bool
    {
        return Portal::group() === UserGroup::SupplierHotel;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Transfer details')->columns(3)->columnSpanFull()->schema([
                Select::make('Status')->options(fn (): array => Lookups::statusesFor(auth()->user()?->group()?->value))->required(),
                TextInput::make('SupplierCode')->label('Supplier code')->maxLength(150),
                DatePicker::make('TransferDate')->label('Transfer date')->required()
                    ->rule(fn (?Transfer $record): Closure => function (string $attribute, $value, Closure $fail) use ($record): void {
                        if ($error = TransferRules::transferDateError($value, $record, $record?->ImportDate, creating: false)) {
                            $fail($error);
                        }
                    }),
                TextInput::make('TransferTime')->label('Transfer time')->maxLength(50),
                Select::make('TransferType')->label('Transfer type')->options(fn (): array => Lookups::optionValues('TransferType')),
                Select::make('CarTypeID')->label('Car type')->options(fn (): array => Lookups::optionValues('CarType')),
                // PHPRunner edit1: Pax and Assistant are shown but not changed here.
                TextInput::make('Pax')->disabled()->dehydrated(false),
                TextInput::make('SalePrice')->label('Sale price')->prefix('€')->disabled()->dehydrated(false)
                    ->visible(fn (string $operation): bool => $operation === 'view' && ! self::isHotel()),
                TextInput::make('From')->maxLength(250),
                TextInput::make('To')->maxLength(250),
                TextInput::make('Flight_Ship_Num_From')->label('Flight / ship')->maxLength(250),
                TextInput::make('LeadName')->label('Lead name')->maxLength(250),
                TextInput::make('LeadPhone')->label('Lead phone')->maxLength(250),
                TextInput::make('Assistant')->disabled()->dehydrated(false),
                Textarea::make('PassengerName')->label('Passengers')->rows(2)->columnSpan(2),
                Textarea::make('Notes')->rows(3)->columnSpanFull()->visible(fn (): bool => ! self::isHotel()),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $hotel = self::isHotel();

        return $table
            ->stackedOnMobile()
            ->defaultSort('TransferDate')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->columns([
                TextColumn::make('TransferID')->label('ID')->sortable()->searchable()->summarize(Count::make()->label('')),
                TextColumn::make('Status')->badge()->color(fn (?string $state): string => match ($state) {
                    'Completed' => 'success', 'Cancelled' => 'danger', 'Pending', 'New' => 'warning', default => 'gray',
                }),
                TextColumn::make('SupplierCode')->label('Sup code')->searchable()->placeholder('—'),
                TextColumn::make('TransferDate')->label('Date')->date('D d/m/Y')->sortable(),
                TextColumn::make('TransferTime')->label('Time'),
                TextColumn::make('TransferType')->label('Type'),
                TextColumn::make('CarTypeID')->label('Car type')->toggleable(),
                TextColumn::make('Pax'),
                TextColumn::make('From')->wrap()->searchable(),
                TextColumn::make('To')->wrap()->searchable(),
                TextColumn::make('Flight_Ship_Num_From')->label('Flight / ship')->wrap()->toggleable(),
                TextColumn::make('LeadName')->label('Lead name')->searchable()->toggleable(isToggledHiddenByDefault: ! $hotel),
                TextColumn::make('PassengerName')->label('Passengers')->wrap()->limit(60)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('Assistant')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('Notes')->wrap()->limit(80)->toggleable()->visible(! $hotel),
                TextColumn::make('SalePrice')->label('Price')->money('EUR', locale: 'el')->alignEnd()
                    ->summarize(Sum::make()->money('EUR', locale: 'el')->label('')),
                TextColumn::make('ImportDate')->label('Booked on')->date('d/m/Y')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('Status')->options(fn (): array => Lookups::optionValues('Status'))->multiple(),
                SelectFilter::make('TransferType')->label('Transfer type')->options(fn (): array => Lookups::optionValues('TransferType')),
                SelectFilter::make('CarTypeID')->label('Car type')->options(fn (): array => Lookups::optionValues('CarType')),
                SelectFilter::make('ReferencePeriod')->label('Month')
                    ->options(fn (): array => collect(range(-2, 12))->map(fn (int $i) => now()->startOfMonth()->subMonths($i))
                        ->mapWithKeys(fn ($m): array => [$m->copy()->endOfMonth()->toDateString() => $m->format('m/Y')])->all()),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()->visible(fn (Transfer $record): bool => static::canEdit($record))]);
    }

    public static function getRelations(): array
    {
        return [PriceLinesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMyTransfers::route('/'),
            'view' => ViewMyTransfer::route('/{record}'),
            'edit' => EditMyTransfer::route('/{record}/edit'),
        ];
    }
}
