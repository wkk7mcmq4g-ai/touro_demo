<?php

namespace App\Filament\Resources\TransfersToSort;

use App\Filament\Concerns\UsesPhpRunnerPermissions;
use App\Filament\Resources\TransfersToSort\Pages\EditTransferToSort;
use App\Filament\Resources\TransfersToSort\Pages\ListTransferToSorts;
use App\Filament\Resources\TransfersToSort\RelationManagers\LoadFilesRelationManager;
use App\Filament\Resources\TransfersToSort\RelationManagers\LoadNotesRelationManager;
use App\Filament\Resources\TransfersToSort\RelationManagers\LoadPriceLinesRelationManager;
use App\Filament\Resources\TransfersToSort\Schemas\LoadFormTabs;
use App\Models\TransfersLoad;
use App\Support\Lookups;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * PHPRunner "Προς Διαλογή" (transfers_load): bookings sent in by partners and suppliers,
 * checked here and moved into the transfers with the MoveToTransfers procedure.
 */
class TransferToSortResource extends Resource
{
    use UsesPhpRunnerPermissions;

    protected static string $permissionTable = 'transfers_load';

    protected static ?string $model = TransfersLoad::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Προς Διαλογή';

    /** List page title and breadcrumb: the same as the menu item. */
    protected static ?string $pluralModelLabel = 'Προς Διαλογή';

    protected static ?string $modelLabel = 'booking to sort';

    protected static ?string $slug = 'to-sort';

    /** PHPRunner lists only the bookings not moved yet. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('Loaded', 0);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            LoadFormTabs::make(
                left: [
                    TextInput::make('SupplierCode')->label('Supplier code')->maxLength(50),
                    TextInput::make('Pax')->maxLength(50),
                    Select::make('TransferType')->label('Type')->options(fn (): array => Lookups::optionValues('TransferType')),
                    DatePicker::make('TransferDate')->label('Transfer date'),
                    TextInput::make('TransferTime')->label('Transfer time')->maxLength(50),
                    Select::make('CarType')->label('Car type')->options(fn (): array => Lookups::optionValues('CarType')),
                    Textarea::make('Notes')->label('Παρατηρήσεις')->rows(4)->maxLength(2500)->columnSpanFull(),
                ],
                right: [
                    TextInput::make('From')->datalist(fn (): array => array_keys(Lookups::pickupPlaces()))->maxLength(150),
                    TextInput::make('To')->datalist(fn (): array => array_keys(Lookups::destinations()))->maxLength(150),
                    TextInput::make('Flight_Ship_Num_From')->label('Πτήση / Πλοίο')->maxLength(150),
                    TextInput::make('Assistant')->label('Συνοδός')->maxLength(500),
                    TextInput::make('Sale_Price')->label('Sale price')->numeric()->prefix('€'),
                    Textarea::make('PassengerList')->label('Passenger list')->rows(2)->maxLength(500),
                    TextInput::make('LeadName')->label('Lead name')->maxLength(150),
                    TextInput::make('LeadPhone')->label('Lead phone')->maxLength(150),
                ],
                pageClass: EditTransferToSort::class,
                top: [Select::make('cID_Customer')->label('Customer')->options(fn (): array => Lookups::customers())->searchable()->disabled()],
            ),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('TransferDate')
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->columns([
                TextColumn::make('LoadID')->label('ID')->sortable(),
                TextColumn::make('ImportDate')->label('Εισαγωγή')->date('d/m/Y')->sortable(),
                TextColumn::make('cID_Customer')->label('Customer')->formatStateUsing(fn ($state): ?string => Lookups::customers()[$state] ?? null),
                TextColumn::make('SupplierCode')->label('Sup code')->searchable(),
                TextColumn::make('TransferType')->label('Type'),
                TextColumn::make('TransferDate')->label('Transfer date')->date('D d/m/Y')->sortable(),
                TextColumn::make('TransferTime')->label('Time'),
                TextColumn::make('Pax'),
                TextColumn::make('From')->wrap()->searchable(),
                TextColumn::make('To')->wrap()->searchable(),
                TextColumn::make('Flight_Ship_Num_From')->label('Πτήση / Πλοίο')->wrap()->toggleable(),
                TextColumn::make('CarType')->label('Car type')->toggleable(),
                TextColumn::make('LeadName')->label('Lead name')->searchable(),
                TextColumn::make('LeadPhone')->label('Lead phone')->toggleable(),
                TextColumn::make('Assistant')->label('Συνοδός')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('PassengerList')->label('Passenger list')->toggleable(isToggledHiddenByDefault: true)->wrap(),
                TextColumn::make('Notes')->label('Παρατηρήσεις')->wrap()->limit(100)->toggleable(),
                TextColumn::make('SalePrice_C')->label('Τιμή')->money('EUR', locale: 'el'),
                TextColumn::make('Createdby')->label('Created by')->toggleable(),
            ])
            ->filters([SelectFilter::make('cID_Customer')->label('Customer')->options(fn (): array => Lookups::customers())->searchable()])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                // PHPRunner "Μεταφορά σε δρομολόγια" (New_Button44).
                BulkAction::make('moveToTransfers')
                    ->label('Μεταφορά σε δρομολόγια')
                    ->icon(Heroicon::OutlinedArrowRightCircle)
                    ->requiresConfirmation()
                    ->modalDescription('The ticked bookings become transfers, with their products, notes and files.')
                    ->visible(fn (): bool => (bool) auth()->user()?->may('transfers', 'add'))
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records): void {
                        foreach ($records as $load) {
                            DB::statement('CALL MoveToTransfers(?, ?)', [$load->LoadID, auth()->user()->username]);
                        }
                        Notification::make()->success()->title($records->count().' booking(s) moved to transfers')->send();
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [LoadPriceLinesRelationManager::class, LoadNotesRelationManager::class, LoadFilesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTransferToSorts::route('/'),
            'edit' => EditTransferToSort::route('/{record}/edit'),
        ];
    }
}
