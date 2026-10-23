<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Reports\PivotReport;
use App\Support\Lookups;
use Illuminate\Database\Query\Builder;
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
        return DB::table('expenses')->select(['ExpenseDate', 'ExpenseAmountRcvd', 'ExpenseAmountPaid', 'VehicleID', 'DriverID', 'ExpenseCategoryID', 'Company', 'Flag']);
    }

    protected function dateColumn(): ?string
    {
        return 'ExpenseDate';
    }

    protected function dimensions(): array
    {
        return [
            'month' => ['label' => 'Expense period', 'expr' => "DATE_FORMAT(ExpenseDate, '%Y-%m')", 'axis' => 'rows'],
            'year' => ['label' => 'Expense year', 'expr' => 'YEAR(ExpenseDate)', 'axis' => 'rows'],
            'ExpenseCategoryID' => ['label' => 'Κατηγορία', 'expr' => 'ExpenseCategoryID', 'filter' => true,
                'format' => fn ($v): ?string => Lookups::optionIds('ExpenseCategory')[$v] ?? null],
            'VehicleID' => ['label' => 'Όχημα', 'expr' => 'VehicleID', 'filter' => true,
                'format' => fn ($v): ?string => Lookups::vehicles()[$v] ?? null],
            'DriverID' => ['label' => 'Οδηγός', 'expr' => 'DriverID', 'filter' => true,
                'format' => fn ($v): ?string => Lookups::drivers()[$v] ?? null],
            'Company' => ['label' => 'Εταιρεία', 'expr' => 'Company', 'filter' => true],
            'Flag' => ['label' => 'Flag', 'expr' => 'Flag', 'filter' => true],
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
