<?php

namespace App\Filament\Portal\Resources\MyFuel;

use App\Filament\Concerns\HandlesLegacyFiles;
use App\Filament\Concerns\UsesPhpRunnerPermissions;
use App\Filament\Portal\Resources\MyFuel\Pages\CreateMyFuel;
use App\Filament\Portal\Resources\MyFuel\Pages\EditMyFuel;
use App\Filament\Portal\Resources\MyFuel\Pages\ListMyFuel;
use App\Models\FuelRegister;
use App\Support\LegacyFiles;
use App\Support\Lookups;
use BackedEnum;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\View;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * PHPRunner "Καύσιμα" for drivers (fuel_register list1 / add / edit): the fuel receipts they
 * entered. The office checks them in the back office.
 */
class MyFuelResource extends Resource
{
    use HandlesLegacyFiles;
    use UsesPhpRunnerPermissions;

    protected static string $permissionTable = 'fuel_register';

    protected static ?string $model = FuelRegister::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|UnitEnum|null $navigationGroup = 'Οχήματα';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Καύσιμα';

    protected static ?string $modelLabel = 'fuel receipt';

    protected static ?string $pluralModelLabel = 'Καύσιμα';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'fuel';

    /** PHPRunner's list1 shows every receipt; here a driver sees the ones they entered. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('CreatedBy', (string) auth()->user()?->username);
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    /** Checked receipts are the office's now. */
    public static function canEdit($record): bool
    {
        return static::allows('update') && blank($record->ApprovedStatus);
    }

    /** PHPRunner BeforeAdd (not Admin): not in the future, not older than 7 days. */
    public static function fuelDateRule(): Closure
    {
        return function (string $attribute, $value, Closure $fail): void {
            $date = Carbon::parse($value)->startOfDay();
            if ($date->gt(today())) {
                $fail('Η ημερομηνία δεν μπορεί να είναι στο μέλλον');
            } elseif ($date->lte(today()->subDays(7))) {
                $fail('Η ημερομηνία δεν μπορεί να είναι παλαιότερη των 7 ημερών');
            }
        };
    }

    /** The vehicle assigned to this driver most recently (today's first), to start the form with. */
    public static function myVehicleId(): ?int
    {
        $driver = DB::table('drivers')->where('UserID', auth()->id())->value('DriverID');

        return $driver ? DB::table('vehicle_assignments')->where('DriverID', $driver)
            ->where('AssignmentDate', '<=', today())->orderByDesc('AssignmentDate')->value('VehicleID') : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            // Phone first: one column, the amount first and big, the vehicle filled in.
            Section::make()->columns(['default' => 1, 'sm' => 3])->columnSpanFull()->schema([
                TextInput::make('FuelAmount')->label('Αξία')->numeric()->minValue(0)->step(0.01)->prefix('€')->required()
                    ->inputMode('decimal')->autofocus()->extraInputAttributes(['class' => 'myat-fuel-input']),
                DatePicker::make('FuelDate')->label('Ημερομηνία')->default(now())->required()->native()->maxDate(today())
                    ->rule(fn (): Closure => self::fuelDateRule()),
                Select::make('VehicleID')->label('Όχημα')->options(fn (): array => Lookups::vehicles(['IKE', 'Taxi', 'DXF']))
                    ->default(fn (): ?int => self::myVehicleId())->searchable()->required(),
            ]),
            Section::make('Φωτογραφία απόδειξης')->columnSpanFull()
                ->description('Τραβήξτε φωτογραφία ή διαλέξτε αρχείο.')
                ->schema(self::legacyFilesComponents('Files', 'receipt', 'Φωτογραφία ή αρχείο', ['image/*', 'application/pdf']))
                ->visible(fn (?FuelRegister $record): bool => LegacyFiles::canUpload() || filled($record?->Files)),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columnManager(false)
            ->defaultSort('FuelDate', 'desc')
            ->paginated([20, 50])
            ->emptyStateHeading('Δεν υπάρχουν αποδείξεις')
            ->emptyStateDescription('Πατήστε «Νέα απόδειξη» για να καταχωρήσετε καύσιμα.')
            ->emptyStateIcon(Heroicon::OutlinedBeaker)
            // One card per receipt: amount, date, status, vehicle, photo. Tap a card to edit it.
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->recordUrl(fn (FuelRegister $record): ?string => static::canEdit($record) ? static::getUrl('edit', ['record' => $record]) : null)
            ->columns([
                View::make('filament.portal.fuel-card'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMyFuel::route('/'),
            'create' => CreateMyFuel::route('/create'),
            'edit' => EditMyFuel::route('/{record}/edit'),
        ];
    }
}
