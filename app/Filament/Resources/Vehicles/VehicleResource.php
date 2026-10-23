<?php

namespace App\Filament\Resources\Vehicles;

use App\Filament\Concerns\UsesPhpRunnerPermissions;
use App\Filament\Resources\Vehicles\Pages\CreateVehicle;
use App\Filament\Resources\Vehicles\Pages\EditVehicle;
use App\Filament\Resources\Vehicles\Pages\ListVehicles;
use App\Filament\Resources\Vehicles\RelationManagers\KilometresRelationManager;
use App\Filament\Resources\Vehicles\RelationManagers\LogsRelationManager;
use App\Models\Vehicle;
use App\Support\LegacyColumns;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** PHPRunner "Οχήματα": the fleet, with the log book and km history as tabs. */
class VehicleResource extends Resource
{
    use UsesPhpRunnerPermissions;

    protected static string $permissionTable = 'vehicles';

    protected static ?string $model = Vehicle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Fleet & drivers';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Οχήματα';

    protected static ?string $modelLabel = 'vehicle';

    protected static ?string $recordTitleAttribute = 'VehicleName';

    public const LICENCES = ['IKE' => 'IKE', 'DXF' => 'DXF', 'Taxi' => 'Taxi', 'PVT' => 'PVT', 'Rental' => 'Rental', 'Sold' => 'Sold'];

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->select(LegacyColumns::except('vehicles', ['Picture']));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Στοιχεία οχήματος')->columns(3)->columnSpanFull()->schema([
                TextInput::make('VehicleName')->label('Name')->required()->maxLength(150)
                    ->helperText('As used for "vehicle / coach company" on transfers.'),
                TextInput::make('VoucherName')->label('Voucher name')->maxLength(150),
                Select::make('VehicleLicence')->label('Licence')->options(self::LICENCES),
                TextInput::make('VehicleBrand')->label('Brand')->maxLength(150),
                TextInput::make('VehicleYear')->label('Year')->integer(),
                TextInput::make('LicencePlate')->label('Licence plate')->maxLength(50),
                DatePicker::make('PurchaseDate')->label('Purchase date'),
                DatePicker::make('SoldDate')->label('Sold date'),
                TextInput::make('Passengers')->integer(),
                TextInput::make('VehicleType')->label('Type')->maxLength(150),
                TextInput::make('Tolls_Group')->label('Tolls group')->maxLength(50),
                TextInput::make('Tolls_Attiki')->label('Tolls Attiki')->maxLength(50),
                TextInput::make('Tolls_NeaOdos')->label('Tolls Nea Odos')->maxLength(50),
                TextInput::make('Tyres')->maxLength(50),
                TextInput::make('AIA')->label('AIA')->maxLength(50),
                DatePicker::make('AIA_Expiry')->label('AIA expiry'),
                TextInput::make('FreeText')->label('Free text')->maxLength(500)->columnSpan(2),
                Toggle::make('VehicleTaxi')->label('Taxi (driver taxi receipts)'),
                Toggle::make('Active')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $text = fn (string $name, string $label, bool $hidden = false): TextColumn => TextColumn::make($name)->label($label)
            ->searchable()->sortable()->toggleable(isToggledHiddenByDefault: $hidden)->placeholder('—');

        return $table
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->defaultSort('VehicleName')
            ->columns([
                $text('VehicleName', 'Name')->weight('bold'),
                $text('VehicleBrand', 'Brand'),
                $text('VehicleYear', 'Year'),
                $text('LicencePlate', 'Licence plate'),
                $text('VehicleLicence', 'Licence')->badge(),
                $text('Passengers', 'Passengers'),
                $text('VehicleType', 'Type'),
                TextColumn::make('PurchaseDate')->label('Purchase date')->date('d/m/Y')->sortable()->toggleable(),
                TextColumn::make('SoldDate')->label('Sold date')->date('d/m/Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
                $text('VoucherName', 'Voucher name', true),
                $text('Tolls_Group', 'Tolls group', true),
                $text('Tolls_Attiki', 'Tolls Attiki', true),
                $text('Tolls_NeaOdos', 'Tolls Nea Odos', true),
                $text('Tyres', 'Tyres', true),
                $text('AIA', 'AIA', true),
                TextColumn::make('AIA_Expiry')->label('AIA expiry')->date('d/m/Y')->sortable()->toggleable()
                    ->color(fn ($state): ?string => $state && $state->isBefore(now()->addMonth()) ? 'danger' : null),
                $text('FreeText', 'Free text', true),
                IconColumn::make('Active')->boolean()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('VehicleLicence')->label('Licence')->options(self::LICENCES)->multiple(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [LogsRelationManager::class, KilometresRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVehicles::route('/'),
            'create' => CreateVehicle::route('/create'),
            'edit' => EditVehicle::route('/{record}/edit'),
        ];
    }
}
