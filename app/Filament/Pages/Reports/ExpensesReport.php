<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Reports\PivotReport;
use App\Support\Lookups;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** PHPRunner "Expenses Report" (expenses crosstab): money in and out by period and category, vehicle or driver. */
class ExpensesReport extends PivotReport
{
    protected static string $permissionTable = 'expenses Report';

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'Expenses Report';

    protected static ?string $title = 'Expenses Report';

    protected static ?string $slug = 'reports/expenses';

    protected function source(): Builder
    {
        return DB::table('expenses')->select(['ExpenseDate', 'ExpenseAmountRcvd', 'ExpenseAmountPaid', 'VehicleID', 'DriverID', 'ExpenseCategoryID']);
    }

    protected function dateColumn(): ?string
    {
        return 'ExpenseDate';
    }

    /** As PHPRunner's crosstab: expense period (month end) or year down; category, driver or vehicle across. */
    protected function dimensions(): array
    {
        return [
            'month' => ['label' => 'Expense period', 'expr' => 'LAST_DAY(ExpenseDate)', 'axis' => 'rows',
                'format' => fn ($v): string => Carbon::parse($v)->format('d/m/Y')],
            'year' => ['label' => 'Expense year', 'expr' => 'YEAR(ExpenseDate)', 'axis' => 'rows'],
            'ExpenseCategoryID' => ['label' => 'Κατηγορία', 'expr' => 'ExpenseCategoryID', 'axis' => 'cols', 'filter' => true,
                'format' => fn ($v): ?string => Lookups::optionIds('ExpenseCategory')[$v] ?? null],
            // PHPRunner: ifnull(DriverID, 'No Driver'), ifnull(VehicleID, 'No Vehicle').
            'DriverID' => ['label' => 'Οδηγός', 'expr' => "COALESCE(NULLIF(DriverID, ''), 'No Driver')", 'axis' => 'cols', 'filter' => true,
                'format' => fn ($v): ?string => Lookups::drivers()[$v] ?? null],
            'VehicleID' => ['label' => 'Όχημα', 'expr' => "COALESCE(NULLIF(VehicleID, ''), 'No Vehicle')", 'axis' => 'cols', 'filter' => true,
                'format' => fn ($v): ?string => Lookups::vehicles()[$v] ?? null],
        ];
    }

    protected function measures(): array
    {
        return [
            'paid' => ['label' => 'Έξοδα', 'expr' => 'ExpenseAmountPaid', 'money' => true],
            'rcvd' => ['label' => 'Έσοδα', 'expr' => 'ExpenseAmountRcvd', 'money' => true],
        ];
    }

    protected function defaults(): array
    {
        return ['month', 'ExpenseCategoryID', 'paid'];
    }
}
