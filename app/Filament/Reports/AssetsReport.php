<?php

namespace App\Filament\Reports;

use Illuminate\Database\Query\Builder;
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

    protected function dimensions(): array
    {
        return [
            'month' => ['label' => 'Reference period', 'expr' => "DATE_FORMAT(ReferencePeriod, '%Y-%m')", 'axis' => 'rows'],
            'year' => ['label' => 'Έτος', 'expr' => 'YEAR(ReferencePeriod)', 'axis' => 'rows'],
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
