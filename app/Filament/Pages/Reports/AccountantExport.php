<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Reports\ListReport;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** PHPRunner "Εξαγωγή για λογιστή" (v_receipts): invoice numbers of transfers and charges per month. */
class AccountantExport extends ListReport
{
    protected static string $permissionTable = 'v_receipts';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Εξαγωγή για λογιστή';

    protected static ?string $title = 'Εξαγωγή για λογιστή';

    protected static ?string $slug = 'reports/accountant';

    protected function source(): Builder
    {
        return DB::table('v_receipts');
    }

    protected function defaultSort(): ?array
    {
        return ['ReferencePeriod', 'desc'];
    }

    protected function thenSortBy(): array
    {
        return [['TransferDate', 'desc']];
    }

    protected function columns(): array
    {
        return [
            TextColumn::make('SalesInvoiceNum')->label('Sales invoice num')->searchable()->placeholder('—'),
            TextColumn::make('TransferID')->label('Transfer ID')->searchable()->sortable(),
            TextColumn::make('PaymentType')->label('Payment type')->placeholder('—'),
            TextColumn::make('TransferDate')->label('Transfer date')->date('d/m/Y')->sortable(),
            TextColumn::make('ReferencePeriod')->label('Reference period')->date('m/Y')->sortable(),
        ];
    }

    protected function filters(): array
    {
        return [
            SelectFilter::make('ReferencePeriod')->label('Reference period')->multiple()
                ->options(fn (): array => DB::table('v_receipts')->whereNotNull('ReferencePeriod')->distinct()->orderByDesc('ReferencePeriod')
                    ->pluck('ReferencePeriod')->mapWithKeys(fn ($d): array => [$d => date('m/Y', strtotime($d))])->all()),
            SelectFilter::make('PaymentType')->label('Payment type')->multiple()
                ->options(fn (): array => DB::table('v_receipts')->distinct()->orderBy('PaymentType')->pluck('PaymentType', 'PaymentType')->filter()->all()),
        ];
    }
}
