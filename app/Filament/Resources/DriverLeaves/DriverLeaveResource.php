<?php

namespace App\Filament\Resources\DriverLeaves;

use App\Filament\Concerns\UsesPhpRunnerPermissions;
use App\Filament\Resources\DriverLeaves\Pages\ListDriverLeaves;
use App\Models\DriverLeaveEntry;
use App\Support\Lookups;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * PHPRunner "Άδειες Οδηγών" (read-only there too): leave per driver, with day totals per
 * type. Grouping by driver and month gives what PHPRunner's two leave reports showed.
 */
class DriverLeaveResource extends Resource
{
    use UsesPhpRunnerPermissions;

    protected static string $permissionTable = 'drivers_holidays_master';

    protected static ?string $model = DriverLeaveEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSun;

    protected static string|UnitEnum|null $navigationGroup = 'Fleet & drivers';

    protected static ?int $navigationSort = 11;

    protected static ?string $navigationLabel = 'Άδειες Οδηγών';

    /** List page title and breadcrumb: the same as the menu item. */
    protected static ?string $pluralModelLabel = 'Άδειες Οδηγών';

    protected static ?string $modelLabel = 'leave';

    protected static ?string $slug = 'driver-leave';

    public const TYPES = ['ΡΕΠΟ' => 'ΡΕΠΟ', 'ΑΔΕΙΑ' => 'ΑΔΕΙΑ', 'ΑΝΑΡΡΩΤΙΚΗ' => 'ΑΝΑΡΡΩΤΙΚΗ', 'ΣΥΝΤΗΡΗΣΗ' => 'ΣΥΝΤΗΡΗΣΗ'];

    public static function getEloquentQuery(): Builder
    {
        return DriverLeaveEntry::source();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->defaultSort('HolidayStartDate', 'desc')
            ->groups([
                Group::make('DriverID')->label('Οδηγός')->getTitleFromRecordUsing(fn (DriverLeaveEntry $r): string => Lookups::drivers()[$r->DriverID] ?? (string) $r->DriverID),
                Group::make('HolidayPeriod')->label('Μήνας')->getTitleFromRecordUsing(fn (DriverLeaveEntry $r): string => (string) $r->HolidayPeriod?->translatedFormat('F Y')),
            ])
            ->columns([
                TextColumn::make('DriverID')->label('Οδηγός')->formatStateUsing(fn ($state): ?string => Lookups::drivers()[$state] ?? null)->sortable(),
                TextColumn::make('HolidayStartDate')->label('Έναρξη')->date('d/m/Y')->sortable(),
                TextColumn::make('HolidayEndDate')->label('Λήξη')->date('d/m/Y')->sortable(),
                TextColumn::make('HolidayType')->label('Τύπος')->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'ΑΔΕΙΑ' => 'success', 'ΑΝΑΡΡΩΤΙΚΗ' => 'danger', 'ΣΥΝΤΗΡΗΣΗ' => 'gray', default => 'info'
                    }),
                TextColumn::make('HolidayPeriod')->label('Περίοδος')->date('m/Y')->sortable(),
                TextColumn::make('ActiveHoliday')->label('Ενεργή')->badge()->color(fn (?string $state): string => $state === 'ΝΑΙ' ? 'warning' : 'gray'),
                TextColumn::make('HolidayDays')->label('Ημέρες')->alignEnd()->summarize(Sum::make()->label('')),
            ])
            ->filters([
                SelectFilter::make('DriverID')->label('Οδηγός')->options(fn (): array => Lookups::drivers())->multiple(),
                SelectFilter::make('HolidayType')->label('Τύπος')->options(self::TYPES)->multiple(),
                Filter::make('year')->schema([TextInput::make('year')->label('Έτος')->integer()->default((int) now()->year)])
                    ->query(fn (Builder $q, array $data): Builder => $q->when($data['year'] ?? null, fn ($q, $y) => $q->whereYear('HolidayStartDate', $y))),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListDriverLeaves::route('/')];
    }
}
