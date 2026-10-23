<?php

namespace App\Filament\Portal\Pages;

use App\Filament\Reports\ListReport;
use App\Models\DriverLeaveEntry;
use App\Support\Lookups;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Query\Builder;
use UnitEnum;

/** PHPRunner "Άδειες Οδηγών" for drivers (drivers_holidays_master, view only): everyone's leave. */
class DriverLeave extends ListReport
{
    protected static string $permissionTable = 'drivers_holidays_master';

    protected static string|UnitEnum|null $navigationGroup = 'Οδηγοί';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSun;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Άδειες Οδηγών';

    protected static ?string $title = 'Άδειες Οδηγών';

    protected static ?string $slug = 'leave';

    protected function source(): Builder
    {
        return DriverLeaveEntry::source()->toBase()->select(['DriverID', 'HolidayStartDate', 'HolidayEndDate', 'HolidayType', 'HolidayDays', 'HolidayPeriod']);
    }

    protected function defaultSort(): ?array
    {
        return ['HolidayStartDate', 'desc'];
    }

    protected function columns(): array
    {
        return [
            TextColumn::make('DriverID')->label('Οδηγός')->formatStateUsing(fn ($state): ?string => Lookups::drivers()[$state] ?? null),
            TextColumn::make('HolidayType')->label('Τύπος')->badge()->color('gray'),
            TextColumn::make('HolidayStartDate')->label('Από')->date('D d/m/Y')->sortable(),
            TextColumn::make('HolidayEndDate')->label('Έως')->date('D d/m/Y'),
            TextColumn::make('HolidayDays')->label('Ημέρες')->alignEnd(),
        ];
    }

    protected function filters(): array
    {
        return [
            SelectFilter::make('DriverID')->label('Οδηγός')->options(fn (): array => Lookups::drivers(activeOnly: true)),
            SelectFilter::make('HolidayType')->label('Τύπος')->options(['ΑΔΕΙΑ' => 'ΑΔΕΙΑ', 'ΡΕΠΟ' => 'ΡΕΠΟ', 'ΑΝΑΡΡΩΤΙΚΗ' => 'ΑΝΑΡΡΩΤΙΚΗ', 'ΣΥΝΤΗΡΗΣΗ' => 'ΣΥΝΤΗΡΗΣΗ']),
        ];
    }
}
