<?php

namespace App\Filament\Portal\Pages;

use App\Filament\Reports\ListReport;
use App\Support\Lookups;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/** PHPRunner "Ιστορικό" for drivers (vehicles_log list1, view only): what was done on each vehicle. */
class VehicleLogBook extends ListReport
{
    protected static string $permissionTable = 'vehicles_log';

    protected static string|UnitEnum|null $navigationGroup = 'Οχήματα';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Ιστορικό';

    protected static ?string $title = 'Ιστορικό';

    protected static ?string $slug = 'vehicle-log';

    protected function source(): Builder
    {
        return DB::table('vehicles_log')->select(['LogID', 'VehicleID', 'LogDate', 'LogType', 'Description', 'Kilometers']);
    }

    protected function defaultSort(): ?array
    {
        return ['LogDate', 'desc'];
    }

    protected function columns(): array
    {
        return [
            TextColumn::make('LogDate')->label('Ημερομηνία')->date('d/m/Y')->sortable(),
            TextColumn::make('VehicleID')->label('Όχημα')->formatStateUsing(fn ($state): ?string => Lookups::vehicles()[$state] ?? null),
            TextColumn::make('LogType')->label('Τύπος')->badge()->color('gray'),
            TextColumn::make('Description')->label('Περιγραφή')->wrap(),
            TextColumn::make('Kilometers')->label('Χιλιόμετρα')->numeric(locale: 'el')->alignEnd(),
        ];
    }

    protected function filters(): array
    {
        return [
            SelectFilter::make('VehicleID')->label('Όχημα')->options(fn (): array => Lookups::vehicles(exclude: ['Sold'])),
            SelectFilter::make('LogType')->label('Τύπος')->options(fn (): array => DB::table('vehicles_log')->distinct()->orderBy('LogType')->pluck('LogType', 'LogType')->filter()->all()),
        ];
    }
}
