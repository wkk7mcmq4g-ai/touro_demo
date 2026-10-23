<?php

namespace App\Filament\Portal\Pages;

use App\Filament\Reports\ListReport;
use App\Support\Lookups;
use App\Support\Portal;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * PHPRunner "Έξοδα" for hotels (transfer_charges1 list_supplier): the expenses (category 1314)
 * on the hotel's transfers that were not cancelled.
 */
class HotelCharges extends ListReport
{
    protected static string $permissionTable = 'transfer_charges1';

    protected static string|UnitEnum|null $navigationGroup = 'Δρομολόγια';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Έξοδα';

    protected static ?string $title = 'Έξοδα';

    protected static ?string $slug = 'charges';

    /** optionvalues ID of the hotel expense category (PHPRunner Init event). */
    private const CATEGORY = 1314;

    protected function source(): Builder
    {
        return DB::table('transfer_charges as c')
            ->join('transfers as t', 't.TransferID', '=', 'c.TransferID')
            ->where('c.Expense', '<>', 0)
            ->where('c.ExpenseChargeCategory', self::CATEGORY)
            ->where('t.cID_Customer', Portal::scopeId())
            ->where('t.Status', '<>', 'Cancelled')
            ->select(['c.ID', 'c.TransferID', 't.TransferDate', 't.TransferTime', 't.From', 't.To', 'c.ExpenseReason', 'c.ExpenseChargeCategory', 'c.Expense']);
    }

    protected function defaultSort(): ?array
    {
        return ['TransferDate', 'desc'];
    }

    protected function columns(): array
    {
        return [
            TextColumn::make('TransferID')->label('Transfer')->searchable(),
            TextColumn::make('TransferDate')->label('Date')->date('D d/m/Y')->sortable(),
            TextColumn::make('TransferTime')->label('Time'),
            TextColumn::make('From')->wrap(),
            TextColumn::make('To')->wrap(),
            TextColumn::make('ExpenseReason')->label('Αιτία')->wrap(),
            TextColumn::make('ExpenseChargeCategory')->label('Category')->formatStateUsing(fn ($state): ?string => Lookups::optionIds('ExpenseChargeCategory')[$state] ?? null)->placeholder('—'),
            TextColumn::make('Expense')->label('Ποσό')->money('EUR', locale: 'el')->alignEnd()
                ->summarize(Sum::make()->money('EUR', locale: 'el')->label('')),
        ];
    }

    protected function filters(): array
    {
        return [
            Filter::make('TransferDate')->columns(2)->schema([DatePicker::make('from')->label('Από'), DatePicker::make('until')->label('Έως')])
                ->query(fn (EloquentBuilder $query, array $data) => $query
                    ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('TransferDate', '>=', $d))
                    ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('TransferDate', '<=', $d))),
        ];
    }
}
