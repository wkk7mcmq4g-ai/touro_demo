<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Reports\ListReport;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;

/** PHPRunner "P&L" (v_pl): income, expenses, receivables and payables per month. */
class ProfitLoss extends ListReport
{
    protected static string $permissionTable = 'v_pl';

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'P&L';

    protected static ?string $title = 'P&L';

    protected static ?string $slug = 'reports/pl';

    protected function source(): Builder
    {
        return DB::table('v_pl');
    }

    protected function defaultSort(): ?array
    {
        return ['EndDate', 'asc'];
    }

    protected function columns(): array
    {
        $money = fn (string $column, string $label) => TextColumn::make($column)->label($label)->money('EUR', locale: 'el')->alignEnd()
            ->color(fn ($state): ?string => (float) $state < 0 ? 'danger' : null)
            ->summarize(Sum::make()->money('EUR', locale: 'el')->label(''));

        return [
            TextColumn::make('EndDate')->label('Περίοδος')->date('m/Y')->sortable(),
            $money('Touro_Cash', 'Έσοδα'),
            $money('ExpensesValue', 'Έξοδα'),
            $money('Required', 'Απαιτήσεις'),
            $money('Due', 'Υποχρεώσεις'),
            $money('NetProfit', 'Κέρδος')->weight('bold'),
            TextColumn::make('NetProfit_perc')->label('Κέρδος %')->alignEnd()
                ->formatStateUsing(fn ($state): string => $state === null ? '—' : Number::percentage((float) $state * 100, 1, locale: 'el')),
        ];
    }
}
