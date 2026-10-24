<?php

namespace App\Filament\Reports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The four PHPRunner reports over v_assets (Receive, Due, Issue, Require): the same view,
 * each adding up a different amount by period and supplier or coach.
 */
abstract class AssetsReport extends PivotReport
{
    protected function source(): Builder
    {
        return DB::table('v_assets');
    }

    protected function dateColumn(): ?string
    {
        return 'ReferencePeriod';
    }

    public function hasNonZeroFilter(): bool
    {
        return true;
    }

    protected function fixedCols(): ?string
    {
        return 'month';
    }

    protected function dimensions(): array
    {
        return [
            // The months are always across (fixedCols), grouped under their year.
            'month' => ['label' => 'Μήνας', 'expr' => 'LAST_DAY(ReferencePeriod)', 'axis' => 'cols',
                'format' => fn ($v): string => Carbon::parse($v)->format('d/m/Y'),
                'group' => fn (string $key): array => [substr($key, 0, 4), substr($key, 0, 4)]],
            'Supplier' => ['label' => 'Supplier', 'expr' => 'Supplier', 'filter' => true],
            'CoachCompanyID' => ['label' => 'Coach', 'expr' => 'CoachCompanyID', 'filter' => true],
            'Status' => ['label' => 'Status', 'expr' => 'Status', 'filter' => true],
            'TobeInvoiced' => ['label' => 'Τύπος παραστ.', 'expr' => 'TobeInvoiced', 'filter' => true],
            'InvoicedIssued' => ['label' => 'Εκδόθηκε Τιμ', 'expr' => 'InvoicedIssued', 'filter' => true],
            'InvoicedRcvd' => ['label' => 'Έλαβα Τιμ', 'expr' => 'InvoicedRcvd', 'filter' => true],
        ];
    }

    protected function measures(): array
    {
        return [
            'PurchasePrice' => ['label' => 'Τιμή Αγοράς', 'expr' => 'PurchasePrice', 'money' => true],
            'Liabilities' => ['label' => 'Υποχρεώσεις', 'expr' => 'Liabilities', 'money' => true],
            'SalePrice' => ['label' => 'Τιμή Πώλησης', 'expr' => 'SalePrice', 'money' => true],
            'Assets' => ['label' => 'Απαιτήσεις', 'expr' => 'Assets', 'money' => true],
        ];
    }
}
