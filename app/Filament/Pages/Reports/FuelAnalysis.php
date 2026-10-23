<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Reports\ListReport;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** PHPRunner "Ανάλυση Καυσίμων" (v_fuel_analysis): fuel paid at the gas station vs. fuel the drivers entered, per month. */
class FuelAnalysis extends ListReport
{
    protected static string $permissionTable = 'v_fuel_analysis';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Ανάλυση Καυσίμων';

    protected static ?string $title = 'Ανάλυση Καυσίμων';

    protected static ?string $slug = 'reports/fuel';

    protected function source(): Builder
    {
        return DB::table('v_fuel_analysis');
    }

    protected function defaultSort(): ?array
    {
        return ['EndDate', 'asc'];
    }

    protected function columns(): array
    {
        $money = fn (string $column, string $label) => TextColumn::make($column)->label($label)->money('EUR', locale: 'el')->alignEnd()
            ->summarize(Sum::make()->money('EUR', locale: 'el')->label(''));

        return [
            TextColumn::make('EndDate')->label('Περίοδος')->date('m/Y')->sortable(),
            $money('ExpenseAmount', 'GAS Station'),
            $money('FuelAmount', 'Έξοδα οδηγών'),
            $money('Remaining', 'Υπόλοιπο')->color(fn ($state): ?string => (float) $state < 0 ? 'danger' : null)->weight('bold'),
        ];
    }
}
