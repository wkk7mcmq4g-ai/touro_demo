<?php

namespace App\Filament\Resources\VehicleAssignments;

use App\Filament\Concerns\UsesPhpRunnerPermissions;
use App\Filament\Resources\VehicleAssignments\Pages\CreateVehicleAssignment;
use App\Filament\Resources\VehicleAssignments\Pages\EditVehicleAssignment;
use App\Filament\Resources\VehicleAssignments\Pages\ListVehicleAssignments;
use App\Models\VehicleAssignment;
use App\Support\Lookups;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * PHPRunner "Ανάθεση" (vehicle_assignments): which driver drives which vehicle on a day.
 * The daily dispatch reads it to know each job's driver.
 */
class VehicleAssignmentResource extends Resource
{
    use UsesPhpRunnerPermissions;

    protected static string $permissionTable = 'vehicle_assignments';

    protected static ?string $model = VehicleAssignment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Fleet & drivers';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Ανάθεση';

    /** List page title and breadcrumb: the same as the menu item. */
    protected static ?string $pluralModelLabel = 'Ανάθεση';

    protected static ?string $modelLabel = 'assignment';

    private const AWAY = ['ΑΔΕΙΑ' => 'ΑΔΕΙΑ', 'ΡΕΠΟ' => 'ΡΕΠΟ', 'ΑΡΡΩΣΤΟΣ' => 'ΑΡΡΩΣΤΟΣ'];

    /** Vehicles that can be assigned (PHPRunner: not sold, rental or private). */
    private static function vehicles(): array
    {
        return Lookups::vehicles(exclude: ['Sold', 'Rental', 'PVT']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            DatePicker::make('AssignmentDate')->label('Ημερομηνία')->default(now())->required(),
            Select::make('DriverID')->label('Οδηγός')->options(fn (): array => Lookups::drivers(activeOnly: true))->searchable()->required()
                ->rules([fn (Get $get, ?VehicleAssignment $record): Closure => self::once('DriverID', 'VehicleID', $get, $record, 'Ο οδηγός έχει ανατεθεί σε άλλο όχημα')]),
            Select::make('VehicleID')->label('Όχημα')->options(fn (): array => self::vehicles())->searchable()
                ->rules([fn (Get $get, ?VehicleAssignment $record): Closure => self::once('VehicleID', 'DriverID', $get, $record, 'Το όχημα έχει ανατεθεί σε άλλον οδηγό')]),
            Select::make('Away')->label('Απουσία')->options(self::AWAY)->placeholder('—'),
        ]);
    }

    /** PHPRunner's add rule: one vehicle per driver and one driver per vehicle per day. */
    private static function once(string $column, string $other, Get $get, ?VehicleAssignment $record, string $message): Closure
    {
        return function (string $attribute, $value, Closure $fail) use ($column, $other, $get, $record, $message): void {
            $date = $get('AssignmentDate');
            if (blank($value) || blank($date)) {
                return;
            }
            $taken = DB::table('vehicle_assignments')
                ->where($column, $value)
                ->whereNotNull($other)
                ->whereDate('AssignmentDate', Carbon::parse($date))
                ->when($record, fn ($q) => $q->where('ID', '<>', $record->getKey()))
                ->exists();
            if ($taken) {
                $fail($message);
            }
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->defaultSort('AssignmentDate', 'desc')
            ->columns([
                TextColumn::make('AssignmentDate')->label('Ημερομηνία')->date('D d/m/Y')->sortable(),
                TextColumn::make('DriverID')->label('Οδηγός')->formatStateUsing(fn ($state): ?string => Lookups::drivers()[$state] ?? null)->sortable(),
                TextColumn::make('VehicleID')->label('Όχημα')->formatStateUsing(fn ($state): ?string => Lookups::vehicles()[$state] ?? null)->sortable(),
                TextColumn::make('Away')->label('Απουσία')->badge()->color('warning')->placeholder('—'),
            ])
            ->filters([
                Filter::make('day')->schema([DatePicker::make('date')->label('Ημερομηνία')])
                    ->query(fn (Builder $q, array $data): Builder => $q->when($data['date'] ?? null, fn ($q, $d) => $q->whereDate('AssignmentDate', $d))),
                SelectFilter::make('DriverID')->label('Οδηγός')->options(fn (): array => Lookups::drivers())->multiple(),
                SelectFilter::make('VehicleID')->label('Όχημα')->options(fn (): array => Lookups::vehicles())->multiple(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVehicleAssignments::route('/'),
            'create' => CreateVehicleAssignment::route('/create'),
            'edit' => EditVehicleAssignment::route('/{record}/edit'),
        ];
    }
}
