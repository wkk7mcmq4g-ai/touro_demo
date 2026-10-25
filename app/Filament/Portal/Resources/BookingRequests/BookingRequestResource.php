<?php

namespace App\Filament\Portal\Resources\BookingRequests;

use App\Enums\UserGroup;
use App\Filament\Concerns\UsesPhpRunnerPermissions;
use App\Filament\Portal\Resources\BookingRequests\Pages\CreateBookingRequest;
use App\Filament\Portal\Resources\BookingRequests\Pages\EditBookingRequest;
use App\Filament\Portal\Resources\BookingRequests\Pages\ListBookingRequests;
use App\Filament\Resources\TransfersToSort\RelationManagers\LoadFilesRelationManager;
use App\Filament\Resources\TransfersToSort\RelationManagers\LoadNotesRelationManager;
use App\Filament\Resources\TransfersToSort\RelationManagers\LoadPriceLinesRelationManager;
use App\Filament\Resources\TransfersToSort\Schemas\LoadFormTabs;
use App\Models\TransfersLoad;
use App\Support\Lookups;
use App\Support\Portal;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * PHPRunner "Αναμονή για αποδοχή" (transfers_load): booking requests a partner or supplier
 * sends; the office moves them into transfers from "Προς Διαλογή".
 */
class BookingRequestResource extends Resource
{
    use UsesPhpRunnerPermissions;

    protected static string $permissionTable = 'transfers_load';

    protected static ?string $model = TransfersLoad::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Δρομολόγια';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Αναμονή για αποδοχή';

    protected static ?string $modelLabel = 'booking request';

    protected static ?string $pluralModelLabel = 'Αναμονή για αποδοχή';

    /** Greek labels keep their case ("Τα δρομολόγια μου", not "Τα Δρομολόγια Μου"). */
    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'booking-requests';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('cID_Customer', Portal::scopeId())->where('Loaded', 0);
    }

    public static function canEdit($record): bool
    {
        // Suppliers may add and delete, but PHPRunner hides their edit page.
        return static::allows('update') && auth()->user()?->group() !== UserGroup::Supplier;
    }

    /** PHPRunner BeforeAdd: not in the past, and not for tomorrow (that needs an e-mail to the office). */
    public static function transferDateRule(): Closure
    {
        return function (string $attribute, $value, Closure $fail): void {
            if (blank($value)) {
                return;
            }
            $date = Carbon::parse($value)->startOfDay();
            if ($date->lte(today())) {
                $fail('Transfer date cannot be in the past');
            } elseif ($date->isSameDay(today()->addDay())) {
                $fail('Transfer Bookings are not allowed for the following day. Please contact us by email at '.config('business.reservations_email'));
            }
        };
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            LoadFormTabs::make(
                left: [
                    TextInput::make('SupplierCode')->label('Supplier code')->maxLength(50),
                    TextInput::make('Pax')->maxLength(50)->required(),
                    Select::make('TransferType')->label('Transfer type')->options(fn (): array => Lookups::optionValues('TransferType'))->required(),
                    DatePicker::make('TransferDate')->label('Transfer date')->required()->rule(fn (): Closure => self::transferDateRule()),
                    TextInput::make('TransferTime')->label('Transfer time')->placeholder('14:30')->maxLength(50)->required(),
                    Select::make('CarType')->label('Car type')->options(fn (): array => Lookups::optionValues('CarType')),
                    Textarea::make('Notes')->label('Παρατηρήσεις')->rows(4)->maxLength(2500)->columnSpanFull(),
                ],
                right: [
                    TextInput::make('From')->datalist(fn (): array => array_keys(Lookups::pickupPlaces()))->maxLength(150)->required()->columnSpan(['default' => 2]),
                    TextInput::make('To')->datalist(fn (): array => array_keys(Lookups::destinations()))->maxLength(150)->required()->columnSpan(['default' => 2]),
                    TextInput::make('Flight_Ship_Num_From')->label('Πτήση / Πλοίο')->maxLength(150)->columnSpan(['default' => 2]),
                    TextInput::make('Assistant')->label('Συνοδός')->maxLength(500)->columnSpan(['default' => 2]),
                    TextInput::make('Sale_Price')->label('Sale price')->numeric()->prefix('€')->columnSpan(['default' => 2]),
                    Textarea::make('PassengerList')->label('Passenger list')->rows(2)->maxLength(500)->columnSpan(['default' => 2]),
                    TextInput::make('LeadName')->label('Lead name')->maxLength(150),
                    TextInput::make('LeadPhone')->label('Lead phone')->tel()->maxLength(150),
                ],
                pageClass: EditBookingRequest::class,
                customerId: fn (): ?int => Portal::customerId(),
            ),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->defaultSort('TransferDate')
            ->emptyStateHeading('No requests waiting')
            ->columns([
                TextColumn::make('LoadID')->label('ID')->sortable(),
                TextColumn::make('ImportDate')->label('Sent')->date('d/m/Y')->sortable(),
                TextColumn::make('SupplierCode')->label('Sup code')->searchable()->placeholder('—'),
                TextColumn::make('TransferType')->label('Type'),
                TextColumn::make('TransferDate')->label('Transfer date')->date('D d/m/Y')->sortable(),
                TextColumn::make('TransferTime')->label('Time'),
                TextColumn::make('Pax'),
                TextColumn::make('From')->wrap()->searchable(),
                TextColumn::make('To')->wrap()->searchable(),
                TextColumn::make('Flight_Ship_Num_From')->label('Πτήση / Πλοίο')->wrap()->toggleable(),
                TextColumn::make('CarType')->label('Car type')->toggleable(),
                TextColumn::make('LeadName')->label('Lead name')->searchable(),
                TextColumn::make('Notes')->label('Παρατηρήσεις')->wrap()->limit(80)->toggleable(),
                TextColumn::make('SalePrice_C')->label('Τιμή')->money('EUR', locale: 'el'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getRelations(): array
    {
        return [LoadPriceLinesRelationManager::class, LoadNotesRelationManager::class, LoadFilesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBookingRequests::route('/'),
            'create' => CreateBookingRequest::route('/create'),
            'edit' => EditBookingRequest::route('/{record}/edit'),
        ];
    }
}
