<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Reports\ListReport;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** PHPRunner "Capacity" (v_stats_2): transfers and tours per day, type and car type. */
class CapacityReport extends ListReport
{
    protected static string $permissionTable = 'v_stats_2';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Capacity';

    protected static ?string $title = 'Capacity';

    protected static ?string $slug = 'reports/capacity';

    protected function source(): Builder
    {
        return DB::table('v_stats_2');
    }

    protected function defaultSort(): ?array
    {
        return ['TransferDate', 'asc'];
    }

    protected function columns(): array
    {
        return [
            TextColumn::make('TransferDate')->label('Transfer date')->date('D d/m/Y')->sortable(),
            TextColumn::make('TransferType')->label('Transfer type')->sortable(),
            TextColumn::make('CarTypeID')->label('Car type')->sortable(),
            TextColumn::make('Viator Tours')->label('Viator Tours')->numeric()->alignEnd()->summarize(Sum::make()->label('')),
            TextColumn::make('ATT Tours')->label('ATT Tours')->numeric()->alignEnd()->summarize(Sum::make()->label('')),
            TextColumn::make('Counter')->numeric()->alignEnd()->sortable()->summarize(Sum::make()->label('')),
        ];
    }

    protected function filters(): array
    {
        return [
            Filter::make('TransferDate')->columns(2)->schema([DatePicker::make('from')->label('Από'), DatePicker::make('until')->label('Έως')])
                ->query(fn (EloquentBuilder $query, array $data) => $query
                    ->when($data['from'] ?? null, fn ($q, $d) => $q->where('TransferDate', '>=', $d))
                    ->when($data['until'] ?? null, fn ($q, $d) => $q->where('TransferDate', '<=', $d))),
            SelectFilter::make('TransferType')->label('Transfer type')->options(fn (): array => DB::table('v_stats_2')->distinct()->orderBy('TransferType')->pluck('TransferType', 'TransferType')->filter()->all()),
            SelectFilter::make('CarTypeID')->label('Car type')->options(fn (): array => DB::table('v_stats_2')->distinct()->orderBy('CarTypeID')->pluck('CarTypeID', 'CarTypeID')->filter()->all()),
        ];
    }
}
