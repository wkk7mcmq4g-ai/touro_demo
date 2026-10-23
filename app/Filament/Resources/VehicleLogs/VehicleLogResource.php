<?php

namespace App\Filament\Resources\VehicleLogs;

use App\Filament\Concerns\HandlesLegacyFiles;
use App\Filament\Concerns\UsesPhpRunnerPermissions;
use App\Filament\Resources\VehicleLogs\Pages\CreateVehicleLog;
use App\Filament\Resources\VehicleLogs\Pages\EditVehicleLog;
use App\Filament\Resources\VehicleLogs\Pages\ListVehicleLogs;
use App\Models\VehiclesLog;
use App\Support\LegacyFiles;
use App\Support\Lookups;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** PHPRunner "Ιστορικό" (vehicles_log): services and repairs per vehicle. */
class VehicleLogResource extends Resource
{
    use HandlesLegacyFiles;
    use UsesPhpRunnerPermissions;

    protected static string $permissionTable = 'vehicles_log';

    protected static ?string $model = VehiclesLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|UnitEnum|null $navigationGroup = 'Fleet & drivers';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Ιστορικό';

    protected static ?string $modelLabel = 'log entry';

    protected static ?string $slug = 'vehicle-log';

    public const TYPES = ['Φανοποιεια', 'Διαφορες Εργασιες', 'Σερβις', 'Ενδιαμεσο', 'Αλλαγη Ελαστικων'];

    public static function form(Schema $schema, bool $withVehicle = true): Schema
    {
        return $schema->components([
            Section::make('Στοιχεία')->columns(2)->columnSpanFull()->schema(array_filter([
                $withVehicle ? Select::make('VehicleID')->label('Όχημα')->options(fn (): array => Lookups::vehicles())->searchable()->required() : null,
                DatePicker::make('LogDate')->label('Ημερομηνία')->default(now())->required(),
                Select::make('LogType')->label('Είδος εργασίας')->options(array_combine(self::TYPES, self::TYPES))->required(),
                TextInput::make('Kilometers')->label('Χιλιόμετρα')->numeric()->default(0),
                Textarea::make('Description')->label('Ανταλλακτικά / περιγραφή')->rows(3)->maxLength(2000)->columnSpanFull(),
            ])),
            Section::make('Αρχεία')->columnSpanFull()->schema(self::legacyFilesComponents())->visible($withVehicle),
        ]);
    }

    public static function table(Table $table, bool $withVehicle = true): Table
    {
        return $table
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->defaultSort('LogDate', 'desc')
            ->columns(array_filter([
                $withVehicle ? TextColumn::make('vehicle.VehicleName')->label('Όχημα')->sortable() : null,
                TextColumn::make('LogDate')->label('Ημερομηνία')->date('d/m/Y')->sortable(),
                TextColumn::make('LogType')->label('Είδος εργασίας')->badge(),
                TextColumn::make('Description')->label('Ανταλλακτικά')->wrap()->limit(150)->searchable(),
                TextColumn::make('Kilometers')->label('Χιλιόμετρα')->numeric(0, ',', '.')->sortable(),
                TextColumn::make('Files')->formatStateUsing(fn (?string $state) => LegacyFiles::links($state))->html()->toggleable(),
            ]))
            ->filters($withVehicle ? [
                SelectFilter::make('VehicleID')->label('Όχημα')->options(fn (): array => Lookups::vehicles())->multiple(),
                SelectFilter::make('LogType')->label('Είδος εργασίας')->options(array_combine(self::TYPES, self::TYPES)),
            ] : [])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVehicleLogs::route('/'),
            'create' => CreateVehicleLog::route('/create'),
            'edit' => EditVehicleLog::route('/{record}/edit'),
        ];
    }
}
