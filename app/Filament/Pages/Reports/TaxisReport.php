<?php

namespace App\Filament\Pages\Reports;

use App\Filament\Reports\ListReport;
use App\Filament\Resources\Transfers\TransferResource;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** PHPRunner "Taxis Report" (taxis_report SQL datasource): taxi receipts of the own taxis since 2025. */
class TaxisReport extends ListReport
{
    protected static string $permissionTable = 'taxis_report';

    protected static ?int $navigationSort = 11;

    protected static ?string $navigationLabel = 'Taxis Report';

    protected static ?string $title = 'Taxis Report';

    protected static ?string $slug = 'reports/taxis';

    /** The vehicles PHPRunner's query names. */
    private const TAXIS = ['VITO 2022', 'E CLASS 2015'];

    protected function source(): Builder
    {
        return DB::table('transfers as t')
            ->whereIn('t.CoachCompanyID', self::TAXIS)
            ->whereYear('t.TransferDate', '>=', 2025)
            ->select(['t.TransferID', 't.TransferDate', 't.TaxiReceipt', 't.TaxiReceiptDate', 't.TaxiReceiptAmount', 't.ElorusTaxiNumber', 't.SalesInvoiceNum', 't.TaxiReceipt_Issued', 't.SalePrice'])
            ->selectRaw("IF(Get_Taxi_flnm(t.TransferID) > 0, 'Yes', 'No') AS TaxiReceiptUploaded");
    }

    protected function defaultSort(): ?array
    {
        return ['TransferDate', 'desc'];
    }

    protected function columns(): array
    {
        return [
            TextColumn::make('TransferID')->label('Transfer ID')->searchable()->sortable()
                ->url(fn ($record): string => TransferResource::getUrl('edit', ['record' => $record->TransferID])),
            TextColumn::make('TransferDate')->label('Transfer date')->date('d/m/Y')->sortable()->searchable(query: fn ($query, string $search) => $query->whereRaw("DATE_FORMAT(TransferDate, '%d/%m/%Y') LIKE ?", ['%'.$search.'%'])),
            TextColumn::make('TaxiReceipt')->label('Taxi receipt')->searchable()->placeholder('—'),
            TextColumn::make('TaxiReceiptDate')->label('Taxi receipt date')->date('d/m/Y')->searchable(query: fn ($query, string $search) => $query->whereRaw("DATE_FORMAT(TaxiReceiptDate, '%d/%m/%Y') LIKE ?", ['%'.$search.'%']))->placeholder('—'),
            TextColumn::make('TaxiReceiptAmount')->label('Taxi receipt amount')->searchable()->money('EUR', locale: 'el')->alignEnd()->placeholder('—'),
            TextColumn::make('ElorusTaxiNumber')->label('Elorus taxi number')->searchable()->placeholder('—')->visible(fn (): bool => (bool) config('elorus.ui')),
            TextColumn::make('SalesInvoiceNum')->label('Sales invoice num')->searchable()->placeholder('—'),
            TextColumn::make('TaxiReceipt_Issued')->label('Taxi receipt issued')->searchable()->placeholder('—'),
            TextColumn::make('SalePrice')->label('Sale price')->searchable()->money('EUR', locale: 'el')->alignEnd(),
            TextColumn::make('TaxiReceiptUploaded')->label('Receipt uploaded')->searchable()->badge()->color(fn (string $state): string => $state === 'Yes' ? 'success' : 'warning'),
        ];
    }

    protected function filters(): array
    {
        return [
            SelectFilter::make('TaxiReceiptUploaded')->label('Receipt uploaded')->options(['Yes' => 'Yes', 'No' => 'No']),
            Filter::make('TransferDate')->columns(2)->schema([DatePicker::make('from')->label('Από'), DatePicker::make('until')->label('Έως')])
                ->query(fn (EloquentBuilder $query, array $data) => $query
                    ->when($data['from'] ?? null, fn ($q, $d) => $q->where('TransferDate', '>=', $d))
                    ->when($data['until'] ?? null, fn ($q, $d) => $q->where('TransferDate', '<=', $d))),
        ];
    }
}
