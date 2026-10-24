<?php

namespace App\Filament\Resources\AccessLogs;

use App\Filament\Concerns\UsesPhpRunnerPermissions;
use App\Filament\Resources\AccessLogs\Pages\ListAccessLogs;
use App\Models\AccessLog;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/** PHPRunner "Access Log": logins of both apps (the new one writes here too). Read-only. */
class AccessLogResource extends Resource
{
    use UsesPhpRunnerPermissions;

    protected static string $permissionTable = 'access_log';

    protected static ?string $model = AccessLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 91;

    protected static ?string $navigationLabel = 'Access Log';

    /** List page title and breadcrumb: the same as the menu item. */
    protected static ?string $pluralModelLabel = 'Access Log';

    protected static ?string $slug = 'access-log';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('LogDate', 'desc')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->columns([
                TextColumn::make('LogDate')->label('Log date')->dateTime('d/m/Y H:i:s')->sortable(),
                TextColumn::make('userid')->label('User')->searchable()->sortable(),
                TextColumn::make('AccessStatus')->label('Access status')->badge()
                    ->color(fn (?string $state): string => match (strtolower((string) $state)) {
                        'in' => 'success', 'out' => 'gray', default => 'warning',
                    }),
                TextColumn::make('ID')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('userid')->label('User')->multiple()->searchable()
                    ->options(fn (): array => DB::table('access_log')->distinct()->orderBy('userid')->pluck('userid', 'userid')->all()),
                Filter::make('LogDate')->columns(2)->schema([DatePicker::make('from')->label('Από'), DatePicker::make('until')->label('Έως')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('LogDate', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('LogDate', '<=', $d))),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAccessLogs::route('/')];
    }
}
