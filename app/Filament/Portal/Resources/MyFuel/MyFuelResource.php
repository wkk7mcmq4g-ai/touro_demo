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
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
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

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Στοιχεία')->columns(3)->columnSpanFull()->schema([
                Select::make('VehicleID')->label('Όχημα')->options(fn (): array => Lookups::vehicles(['IKE', 'Taxi', 'DXF']))->searchable()->required(),
                DatePicker::make('FuelDate')->label('Ημερομηνία')->default(now())->required()->rule(fn (): Closure => self::fuelDateRule()),
                TextInput::make('FuelAmount')->label('Αξία')->numeric()->minValue(0)->prefix('€')->required(),
            ]),
            Section::make('Απόδειξη')->columnSpanFull()->schema(self::legacyFilesComponents('Files', 'receipt')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->stackedOnMobile()
            ->defaultSort('FuelDate', 'desc')
            ->columns([
                TextColumn::make('FuelDate')->label('Ημερομηνία')->date('d/m/Y')->sortable(),
                TextColumn::make('VehicleID')->label('Όχημα')->formatStateUsing(fn ($state): ?string => Lookups::vehicles()[$state] ?? null),
                TextColumn::make('FuelAmount')->label('Αξία')->money('EUR', locale: 'el')->alignEnd(),
                TextColumn::make('Files')->formatStateUsing(fn (?string $state) => LegacyFiles::links($state))->html()->placeholder('—'),
                TextColumn::make('ApprovedStatus')->label('Έλεγχος')->badge()->placeholder('Pending')
                    ->color(fn (?string $state): string => match ($state) {
                        'Rejected' => 'danger', null, '' => 'warning', default => 'success'
                    }),
            ])
            ->recordActions([EditAction::make()]);
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
