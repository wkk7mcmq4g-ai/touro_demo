<?php

namespace App\Filament\Resources\Transfers\Tables;

use App\Filament\Resources\Transfers\Actions\InvoicingActions;
use App\Filament\Resources\Transfers\Actions\TransferActions;
use App\Models\Transfer;
use App\Support\Lookups;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Count;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * The PHPRunner transfers list: the same 57 columns in the same order with the same
 * labels, the same totals and the same filter panel. Every column can be hidden per user
 * (column button); choices are remembered for the session.
 */
class TransfersTable
{
    public static function configure(Table $table): Table
    {
        // "Edit in list" (PHPRunner inline edit): the columns in config/transfers.php become editable.
        $editing = (bool) ($table->getLivewire()->inlineEditing ?? false)
            && (bool) auth()->user()?->may('transfers', 'edit');

        $columns = $editing
            ? InlineEditColumns::apply(self::columns(), config('transfers.inline_edit', []))
            : self::columns();

        return $table
            // PHPRunner: upcoming first (date, time), then the past.
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw('`TransferDate` >= CURRENT_DATE DESC')
                ->orderBy('TransferDate')
                ->orderBy('TransferTime_sort'))
            ->striped()
            // Editable cells are heavy (a few KB each), so editing pages are kept short.
            ->paginated($editing ? [25, 50] : [25, 50, 100, 200])
            ->defaultPaginationPageOption($editing ? 25 : 50)
            ->persistColumnsInSession()
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->columns(array_map(
                // PHPRunner's list events paint cells (TransferCellColors).
                fn (Column $column): Column => $column->extraCellAttributes(fn (Transfer $record): array => TransferCellColors::attributes($record, $column->getName()), merge: true),
                $columns,
            ))
            ->filters(self::filters())
            ->recordActions([
                EditAction::make(),
                TransferActions::copy()->iconButton(),
            ])
            // PHPRunner's "Τιμολόγηση" menu: buttons that work on the ticked transfers.
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkActionGroup::make(InvoicingActions::forTransfers())->dropdown(false),
                    BulkActionGroup::make([
                        TransferActions::closeSales(),
                        TransferActions::closePurchases(),
                    ])->dropdown(false),
                ])->label('Τιμολόγηση')->button()->dropdownWidth(Width::Medium),
            ]);
    }

    /**
     * @return list<Column>
     */
    private static function columns(): array
    {
        $money = fn (string $name, string $label, bool $total = false): TextColumn => TextColumn::make($name)
            ->label($label)
            ->money('EUR', locale: 'el')
            ->alignEnd()
            ->sortable()
            ->toggleable()
            ->when($total, fn (TextColumn $column) => $column->summarize(Sum::make()->money('EUR', locale: 'el')->label('')));

        $text = fn (string $name, string $label, int $limit = 0): TextColumn => TextColumn::make($name)
            ->label($label)
            ->toggleable()
            ->when($limit > 0, fn (TextColumn $column) => $column
                ->limit($limit)
                ->tooltip(fn ($state): ?string => mb_strlen((string) $state) > $limit ? (string) $state : null));

        // PHPRunner painted these cells in the option's colour (TransferCellColors), so plain text here.
        $dot = fn (string $name, string $label, string $option): TextColumn => TextColumn::make($name)
            ->label($label)
            ->sortable()
            ->toggleable();

        // Issue counters PHPRunner shows only when there is something to see.
        $counter = fn (string $name, string $label, string $color): TextColumn => TextColumn::make($name)
            ->label($label)
            ->badge()
            ->color($color)
            ->formatStateUsing(fn ($state): ?string => (int) $state > 0 ? (string) (int) $state : null)
            ->placeholder('')
            ->alignCenter()
            ->toggleable();

        return [
            IconColumn::make('OpenExpensesFlag')
                ->label('Exp.')
                ->getStateUsing(fn (Transfer $record): bool => (int) $record->OpenExpensesFlag === 1)
                ->icon(fn (bool $state) => $state ? Heroicon::ExclamationCircle : null)
                ->color('danger')
                ->toggleable(),
            $counter('OpenCharges', 'Chg.', 'warning'),
            $counter('OperationalIssues', 'Ops', 'danger'),
            $counter('FinancialIssues', 'Fin.', 'warning'),
            TextColumn::make('TransferID')
                ->label('ID')
                ->searchable()
                ->sortable()
                ->weight('semibold')
                ->icon(fn (Transfer $record) => $record->placeholderFields() ? Heroicon::ExclamationTriangle : null)
                ->iconColor('warning')
                ->tooltip(fn (Transfer $record): ?string => ($fields = $record->placeholderFields())
                    ? 'Still to be confirmed: '.implode(', ', $fields)
                    : null)
                ->summarize(Count::make()->label('')),
            TextColumn::make('customer.SupplierName')
                ->label('Supplier')
                ->toggleable()
                ->searchable(query: fn (Builder $query, string $search): Builder => $query
                    ->orWhereHas('customer', fn (Builder $customer) => $customer->where('SupplierName', 'like', "%{$search}%"))),
            $text('SupplierCode', 'Sup Code')->searchable(),
            $text('Pax', 'Pax'),
            $dot('TransferType', 'Type', 'TransferType'),
            TextColumn::make('TransferDate')->label('Transfer Date')->date('D d/m/Y')->sortable()->toggleable(),
            $text('TransferTime', 'Transfer Time')->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('TransferTime_sort', $direction)),
            $text('From', 'From', 40)->searchable()->sortable(),
            $text('To', 'To', 40)->searchable()->sortable(),
            $text('Flight_Ship_Num_From', 'Flight / Ship', 30)->searchable(),
            IconColumn::make('OLP')->label('ΟΛΠ')->boolean()
                ->getStateUsing(fn (Transfer $record): bool => (int) $record->OLP === 1)
                ->toggleable(),
            $dot('TicketsRequested', 'Tickets', 'TicketsRequested'),
            $dot('AssistantStatus', 'Guide status', 'AssistantStatus'),
            $money('Assistant_Price', 'Guide Rate'),
            $dot('CarTypeID', 'Car Type', 'CarType'),
            $text('CoachCompanyID', 'Coach')->sortable(),
            $text('Notes', 'Transfer Notes', 50),
            $text('LesseeName', 'Lessee Name', 30),
            $text('LeadName', 'Lead Name', 30)->searchable(),
            $text('LeadPhone', 'Lead Phone')->searchable(),
            $text('PassengerName', 'Passenger List', 40)->searchable(),
            $dot('Status', 'Status', 'Status'),
            $money('PlatformPrice', 'Τιμή Πλατφ.', total: true),
            $money('Assets', 'Απαιτ.', total: true),
            $money('Liabilities', 'Υποχρ.', total: true),
            $money('CashRegister', 'Ταμείο'),
            $money('Income', 'Έσοδο'),
            $money('PurchasePrice', 'Τιμή Αγορ.', total: true),
            $money('SalePrice', 'Τιμή Πώλ.', total: true),
            $money('PricePaid', 'Πληρωθ.', total: true),
            $money('PriceRcvd', 'Εισπραχθ.', total: true),
            $money('Expense', 'Έξοδα'),
            TextColumn::make('TobeInvoiced')
                ->label('Τύπος παραστ.')
                ->badge()
                ->getStateUsing(fn (Transfer $record): string => $record->TobeInvoiced ?: 'Missing')
                ->color(fn (string $state): string => $state === 'Missing' ? 'danger' : 'gray')
                ->toggleable(),
            $text('InvoicedIssued', 'Εκδόθηκε παραστ.'),
            $text('InvoicedRcvd', 'Έλαβα Τιμ.'),
            $text('VPOS', 'Kωδ. πληρ.'),
            $text('SalesInvoiceNum', 'ΑΑ Εσόδων')->searchable(),
            $text('PurchaseInvoiceNum', 'ΑΑ Εξόδων')->searchable(),
            $text('DriverNotes', 'Driver Notes', 40),
            $text('PaymentOnHoldFlag', 'Αναμ. Έκδ.'),
            $text('Assistant', 'Assistant', 30),
            $text('Platform', 'Πλατφ.'),
            $text('PaymentType', 'Τρόπος πληρ.'),
            TextColumn::make('Voucher_StartDt')->label('Ημερομηνία έναρξης')->getStateUsing(fn (Transfer $record) => TransferCellColors::voucherValue($record, 'Voucher_StartDt'))->date('d/m/Y')->toggleable(),
            TextColumn::make('Voucher_StartTime')->label('Ώρα έναρξης')->getStateUsing(fn (Transfer $record) => TransferCellColors::voucherValue($record, 'Voucher_StartTime'))->time('H:i')->toggleable(),
            TextColumn::make('Voucher_EndDt')->label('Ημερομηνία λήξης')->getStateUsing(fn (Transfer $record) => TransferCellColors::voucherValue($record, 'Voucher_EndDt'))->date('d/m/Y')->toggleable(),
            TextColumn::make('Voucher_EndTime')->label('Ώρα λήξης')->getStateUsing(fn (Transfer $record) => TransferCellColors::voucherValue($record, 'Voucher_EndTime'))->time('H:i')->toggleable(),
            TextColumn::make('ImportDate')->label('Date in')->date('d/m/Y')->sortable()->toggleable(),
            $money('ExtraIncome', 'Add charges'),
            $text('DriverReceiptType', 'Driver Receipt Type'),
            $money('TicketsValue', 'Tickets Value', total: true),
            $text('FLNM', 'FLNM', 30),
            $text('TaxiReceipt_Issued', 'TaxiReceipt Issued'),
        ];
    }

    /**
     * PHPRunner's filter panel and search panel.
     *
     * @return list<BaseFilter>
     */
    private static function filters(): array
    {
        $values = fn (string $name, string $label, ?callable $options = null): SelectFilter => SelectFilter::make($name)
            ->label($label)
            ->multiple()
            ->options($options ?? fn (): array => Lookups::distinctValues('transfers', $name));

        $yesNo = fn (string $name, string $label, string $yes = 'Yes', string $no = 'No'): SelectFilter => SelectFilter::make($name)
            ->label($label)
            ->options([$yes => $yes, $no => $no]);

        // PHPRunner's *_Flag fields show an empty value as "Όχι".
        $emptyMeansNo = fn (string $name, string $column, string $label): SelectFilter => SelectFilter::make($name)
            ->label($label)
            ->multiple()
            ->options(fn (): array => ['Όχι' => 'Όχι'] + array_diff_key(Lookups::distinctValues('transfers', $column), ['' => true]))
            ->query(fn (Builder $query, array $data): Builder => blank($data['values'] ?? null) ? $query : $query->where(
                fn (Builder $query) => $query
                    ->whereIn($column, $data['values'])
                    ->when(in_array('Όχι', $data['values'], true), fn (Builder $query) => $query->orWhere($column, '')->orWhereNull($column)),
            ));

        $dateRange = fn (string $name, string $column, string $label): Filter => Filter::make($name)
            ->schema([
                DatePicker::make('from')->label("{$label} from"),
                DatePicker::make('until')->label("{$label} until"),
            ])
            ->columns(2)
            ->query(fn (Builder $query, array $data): Builder => $query
                ->when($data['from'] ?? null, fn (Builder $query, $date) => $query->whereDate($column, '>=', $date))
                ->when($data['until'] ?? null, fn (Builder $query, $date) => $query->whereDate($column, '<=', $date)))
            ->indicateUsing(fn (array $data): ?string => match (true) {
                filled($data['from'] ?? null) && filled($data['until'] ?? null) => "{$label}: ".Carbon::parse($data['from'])->format('d/m/Y').' – '.Carbon::parse($data['until'])->format('d/m/Y'),
                filled($data['from'] ?? null) => "{$label} from ".Carbon::parse($data['from'])->format('d/m/Y'),
                filled($data['until'] ?? null) => "{$label} until ".Carbon::parse($data['until'])->format('d/m/Y'),
                default => null,
            })
            ->columnSpanFull();

        return [
            // Search panel
            $dateRange('transfer_dates', 'TransferDate', 'Transfer date'),
            // PHPRunner's "Φίλτρα" menu buttons (Yesterday, This month, ...).
            SelectFilter::make('period')
                ->label('Period')
                ->options([
                    'yesterday' => 'Yesterday', 'this_month' => 'This month', 'last_month' => 'Last month',
                    'this_year' => 'This year', 'last_year' => 'Last year',
                ])
                ->query(function (Builder $query, array $data): Builder {
                    $today = Carbon::today();
                    $range = match ($data['value'] ?? null) {
                        'yesterday' => [$today->copy()->subDay(), $today->copy()->subDay()],
                        'this_month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()],
                        'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
                        'this_year' => [$today->copy()->startOfYear(), $today->copy()->endOfYear()],
                        'last_year' => [$today->copy()->subYear()->startOfYear(), $today->copy()->subYear()->endOfYear()],
                        default => null,
                    };

                    return $range ? $query->whereDate('TransferDate', '>=', $range[0])->whereDate('TransferDate', '<=', $range[1]) : $query;
                }),
            $dateRange('import_dates', 'ImportDate', 'Date in'),
            SelectFilter::make('cID_Customer')->label('Supplier')->multiple()->searchable()
                ->options(fn (): array => Lookups::customers()),
            $values('CoachCompanyID', 'Coach', fn (): array => Lookups::coaches())->searchable(),

            // Filter panel
            $values('Status', 'Status'),
            $values('TransferType', 'Type'),
            SelectFilter::make('ReferencePeriod')
                ->label('Month')
                ->multiple()
                ->options(fn (): array => collect(Lookups::distinctValues('transfers', 'ReferencePeriod'))
                    ->filter()
                    ->sortKeysDesc()
                    ->mapWithKeys(fn ($value, $key): array => [$key => Carbon::parse($key)->translatedFormat('F Y')])
                    ->all()),
            $values('CarTypeID', 'Car Type'),
            $yesNo('OperationalIssuesFlag', 'Operational issues'),
            $yesNo('FinancialIssuesFlag', 'Financial issues'),
            $yesNo('OpenChargesFlag', 'Open charges'),
            $values('TobeInvoiced', 'Τύπος παραστ.'),
            $emptyMeansNo('InvoicedRcvd_Flag', 'InvoicedRcvd', 'Έλαβα Τιμ.'),
            $emptyMeansNo('InvoicedIssued_Flag', 'InvoicedIssued', 'Εκδόθηκε παραστ.'),
            $values('Platform', 'Πλατφ.'),
            $values('PaymentOnHoldFlag', 'Αναμ. Έκδ.'),
            $values('ExpensePaid', 'Απ. Εξ.'),
            $values('ExpenseRcvd', 'Παρ. εξ.'),
            $yesNo('AssetsFlag', 'Απαιτ.', 'Ναί', 'Όχι'),
            $yesNo('LiabilitiesFlag', 'Υποχρ.', 'Ναί', 'Όχι'),
            $values('PaymentType', 'Τρόπος πληρ.'),
            SelectFilter::make('PastDate')
                ->label('Past date')
                ->options(['Ναι' => 'Ναι', 'Όχι' => 'Όχι'])
                ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                    'Ναι' => $query->whereDate('TransferDate', '<=', Carbon::today()),
                    'Όχι' => $query->whereDate('TransferDate', '>', Carbon::today()),
                    default => $query,
                }),
            SelectFilter::make('SupplierModified')
                ->label('Supplier modified')
                ->options(['Yes' => 'Yes', 'No' => 'No'])
                ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                    'Yes' => $query->where('SupplierModified', 1),
                    'No' => $query->where(fn (Builder $query) => $query->where('SupplierModified', '<>', 1)->orWhereNull('SupplierModified')),
                    default => $query,
                }),
            $values('pricecheck', 'Price check'),
            SelectFilter::make('TaxiReceipt_flag')
                ->label('Taxi receipt')
                ->options(['Yes' => 'Yes', 'No' => 'No'])
                ->query(function (Builder $query, array $data): Builder {
                    $hasReceipt = "if(`CoachCompanyID` in ('VITO 2022','E CLASS 2015'), Get_Taxi_flnm(`TransferID`), 0) > 0";

                    return match ($data['value'] ?? null) {
                        'Yes' => $query->whereRaw($hasReceipt),
                        'No' => $query->whereRaw("not ({$hasReceipt})"),
                        default => $query,
                    };
                }),
            $values('CreatedBy', 'Created by'),
            $values('ModifiedBy', 'Modified by'),
        ];
    }
}
