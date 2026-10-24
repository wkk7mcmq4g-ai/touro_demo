<?php

namespace App\Filament\Resources\VehicleKms;

use App\Filament\Concerns\UsesPhpRunnerPermissions;
use App\Filament\Resources\VehicleKms\Pages\CreateVehicleKm;
use App\Filament\Resources\VehicleKms\Pages\EditVehicleKm;
use App\Filament\Resources\VehicleKms\Pages\ListVehicleKms;
use App\Models\VehiclesKlm;
use App\Support\Lookups;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** PHPRunner "Ιστορικό ΧΛΜ" (vehicles_klm): km readings. */
class VehicleKmResource extends Resource
{
    use UsesPhpRunnerPermissions;

    protected static string $permissionTable = 'vehicles_klm';

    protected static ?string $model = VehiclesKlm::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTrendingUp;

    protected static string|UnitEnum|null $navigationGroup = 'Fleet & drivers';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Ιστορικό ΧΛΜ';

    /** List page title and breadcrumb: the same as the menu item. */
    protected static ?string $pluralModelLabel = 'Ιστορικό ΧΛΜ';

    protected static ?string $modelLabel = 'km reading';

    protected static ?string $slug = 'vehicle-km';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Select::make('VehicleID')->label('Όχημα')->options(fn (): array => Lookups::vehicles())->searchable()->required(),
            TextInput::make('KLM')->label('Χιλιόμετρα')->numeric()->required(),
            DatePicker::make('RecordDate')->label('Ημ/νία καταγραφής')->default(now())->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->defaultSort('RecordDate', 'desc')
            ->columns([
                TextColumn::make('VehicleID')->label('Όχημα')->formatStateUsing(fn ($state): ?string => Lookups::vehicles()[$state] ?? null)->sortable(),
                TextColumn::make('RecordDate')->label('Ημ/νία καταγραφής')->date('d/m/Y')->sortable(),
                TextColumn::make('KLM')->label('Χιλιόμετρα')->numeric(0, ',', '.')->sortable(),
            ])
            ->filters([SelectFilter::make('VehicleID')->label('Όχημα')->options(fn (): array => Lookups::vehicles())->multiple()])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVehicleKms::route('/'),
            'create' => CreateVehicleKm::route('/create'),
            'edit' => EditVehicleKm::route('/{record}/edit'),
        ];
    }
}
