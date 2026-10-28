<?php

namespace App\Filament\Resources\Transfers\Tables;

use App\Filament\Resources\Transfers\Actions\InvoicingActions;
use App\Filament\Resources\Transfers\Actions\TransferActions;
use App\Filament\Resources\Transfers\TransferResource;
use App\Models\Transfer;
use App\Support\FilterValues;
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
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

/**
 * The PHPRunner transfers list: the same 57 columns in the same order with the same
 * labels, the same totals and the same filter panel. Every column can be hidden per user
 * (column button); choices are remembered for the session.
 */
class TransfersTable
{
    /** PHPRunner's "Φίλτρα" date shortcuts, in its order (period filter and the toolbar menu). */
    public const PERIODS = [
        'issues7' => 'Εκκρεμότητες 7 ημερών', 'last_year' => 'Last Year', 'last_month' => 'Last Month',
        'yesterday' => 'Yesterday', 'today' => 'Today', 'tomorrow' => 'Tomorrow', 'day_after' => 'Day After Tomorrow',
        'next5' => 'Next Five Days', 'this_month' => 'This Month', 'this_year' => 'This Year',
    ];

    /** One date shortcut on a transfers query. */
    public static function period(Builder $query, string $key): Builder
    {
        $today = Carbon::today();
        $between = fn (Carbon $from, Carbon $until): Builder => $query->where('TransferDate', '>=', $from->toDateString())->where('TransferDate', '<=', $until->toDateString());

        return match ($key) {
            'issues7' => $between($today, $today->copy()->addDays(7))->withOpenIssues(),
            'last_year' => $between($today->copy()->subYear()->startOfYear(), $today->copy()->subYear()->endOfYear()),
            'last_month' => $between($today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()),
            'yesterday' => $between($today->copy()->subDay(), $today->copy()->subDay()),
            'today' => $between($today, $today),
            'tomorrow' => $between($today->copy()->addDay(), $today->copy()->addDay()),
            'day_after' => $between($today->copy()->addDays(2), $today->copy()->addDays(2)),
            'next5' => $between($today, $today->copy()->addDays(4)),
            'this_month' => $between($today->copy()->startOfMonth(), $today->copy()->endOfMonth()),
            'this_year' => $between($today->copy()->startOfYear(), $today->copy()->endOfYear()),
            default => $query,
        };
    }

    /** The day's driver of the transfer's vehicle, or the outside coach's partner (PHPRunner's DriverName). */
    public const DRIVER_NAME_SQL = 'Get_Driver_name(`transfers`.`CoachCompanyID`, `transfers`.`TransferDate`)';

    /** The open-items column (Exp. / Chg. / Ops / Fin. badges); kept first by PinsFlagsColumn. */
    public const FLAGS = 'flags';

    /**
     * @return list<array{key: string, short: string, count: int, tip: string}> the transfer's open items
     */
    public static function flags(Transfer $record): array
    {
        $items = [
            ['key' => 'exp', 'short' => 'Exp.', 'count' => (int) $record->OpenExpensesFlag === 1 ? 1 : 0,
                'tip' => 'Exp.: έξοδα που δεν έχουν παραληφθεί (Πρόσθετες χρεώσεις → Έξοδα, «Expense received» = Όχι)'],
            ['key' => 'chg', 'short' => 'Chg.', 'count' => (int) $record->OpenCharges,
                'tip' => 'Chg.: %d ανοιχτές πρόσθετες χρεώσεις χωρίς κατάσταση πληρωμής'],
            ['key' => 'ops', 'short' => 'Ops', 'count' => (int) $record->OperationalIssues,
                'tip' => 'Ops: %d ανοιχτές λειτουργικές σημειώσεις (Notes, κατηγορία Operational, χωρίς κατάσταση)'],
            ['key' => 'fin', 'short' => 'Fin.', 'count' => (int) $record->FinancialIssues,
                'tip' => 'Fin.: %d ανοιχτές οικονομικές σημειώσεις (Notes, κατηγορία Financial, χωρίς κατάσταση)'],
        ];

        return array_values(array_map(
            fn (array $f): array => ['tip' => sprintf($f['tip'], $f['count'])] + $f,
            array_filter($items, fn (array $f): bool => $f['count'] > 0),
        ));
    }

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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->addSelect(DB::raw(self::DRIVER_NAME_SQL.' AS `DriverName`')))
            // Only the ID opens the transfer (and the pencil); a click elsewhere on the row does nothing.
            ->recordUrl(null)
            ->recordAction(null)
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
            // Edit, copy and SMS first on each row, as in PHPRunner.
            ->recordActions([
                EditAction::make()->iconButton()->tooltip('Επεξεργασία'),
                TransferActions::copy()->iconButton()->tooltip('Αντιγραφή'),
                TransferActions::sms()->iconButton(),
            ], position: RecordActionsPosition::BeforeColumns)
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
     * Coach: double-click the cell to change it in a small window (users who may edit transfers).
     * The window lives in the browser (filament/transfers/coach-editor), so opening and Cancel
     * cost nothing; Αποθήκευση calls ListTransfers::setCoach.
     */
    private static function coachColumn(TextColumn $column): TextColumn
    {
        return $column->extraCellAttributes(fn (Transfer $record): array => auth()->user()?->may('transfers', 'edit') ? [
            'class' => 'myat-dblclick-edit',
            'title' => 'Διπλό κλικ για αλλαγή',
            'x-on:dblclick.prevent' => "\$dispatch('myat-coach', { id: ".(int) $record->getKey().", coach: \$el.innerText.trim() })",
        ] : [], merge: true);
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

        return [
            // Open items on the transfer, as small coloured badges right after the row buttons, no
            // header: expenses not received (Exp.), open charges (Chg.), open operational and
            // financial notes (Ops / Fin.). Counted by the database's transfer_charges and
            // transfer_notes triggers; hover a badge for what it means.
            TextColumn::make(self::FLAGS)
                ->label('Ενδείξεις')
                ->state(fn (Transfer $record): string => collect(self::flags($record))->map(fn (array $f): string => $f['short'].($f['count'] > 1 || $f['key'] !== 'exp' ? ' '.$f['count'] : ''))->implode(' · '))
                ->formatStateUsing(fn (Transfer $record): HtmlString => new HtmlString(collect(self::flags($record))
                    ->map(fn (array $f): string => '<span class="myat-flag myat-flag-'.$f['key'].'" x-tooltip.raw="'.e($f['tip']).'" aria-label="'.e($f['tip']).'" tabindex="0">'.($f['key'] === 'exp' ? '!' : $f['count']).'</span>')
                    ->implode('')))
                ->placeholder('')
                ->extraHeaderAttributes(['class' => 'myat-flags-head', 'data-myat-nofilter' => '1'], merge: true)
                ->extraCellAttributes(['class' => 'myat-flags-cell'])
                ->toggleable(),
            TextColumn::make('TransferID')
                ->label('ID')
                ->searchable()
                ->sortable()
                ->weight('semibold')
                ->url(fn (Transfer $record): string => TransferResource::getUrl('edit', ['record' => $record]))
                ->extraCellAttributes(['class' => 'myat-id-link'], merge: true)
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
            TextColumn::make('TransferDate')->label('Transfer Date')->date('d/m/Y')->sortable()->toggleable(),
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
            self::coachColumn($text('CoachCompanyID', 'Coach')->sortable()),
            // PHPRunner's DriverName (datasource; there it was searchable only): the vehicle's driver
            // that day from the assignments, or the partner for an outside coach (Get_Driver_name).
            TextColumn::make('DriverName')->label('Οδηγός')->toggleable()->placeholder('')
                ->searchable(query: fn (Builder $query, string $search): Builder => $query
                    ->orWhereRaw(self::DRIVER_NAME_SQL.' LIKE ?', ['%'.$search.'%'])),
            $text('Notes', 'Transfer Notes', 50)->searchable(),
            $text('LesseeName', 'Lessee Name', 30)->searchable(),
            $text('LeadName', 'Lead Name', 30)->searchable(),
            // PHPRunner searched PhoneClean too: the digits only, so "6977123456" finds "+30 697 712 3456".
            $text('LeadPhone', 'Lead Phone')->searchable(query: fn (Builder $query, string $search): Builder => $query
                ->orWhere('LeadPhone', 'like', '%'.$search.'%')
                ->when(strlen($digits = preg_replace('/\D/', '', $search)) >= 4, fn (Builder $q) => $q
                    ->orWhereRaw("REGEXP_REPLACE(`transfers`.`LeadPhone`, '[^0-9]', '') LIKE ?", ['%'.$digits.'%']))),
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
            $text('DriverNotes', 'Driver Notes', 40)->searchable(),
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
            // PHPRunner's "Φίλτρα" menu (date shortcuts); the toolbar's Ημερομηνίες menu sets it.
            SelectFilter::make('period')
                ->label('Period')
                ->options(self::PERIODS)
                ->query(fn (Builder $query, array $data): Builder => FilterValues::any($query, $data, collect(self::PERIODS)
                    ->mapWithKeys(fn (string $label, string $key): array => [$key => fn (Builder $q) => self::period($q, $key)])->all())),
            $dateRange('import_dates', 'ImportDate', 'Date in'),
            SelectFilter::make('cID_Customer')->label('Supplier')->multiple()->searchable()
                ->options(fn (): array => Lookups::customers()),
            $values('CoachCompanyID', 'Coach', fn (): array => Lookups::coaches())->searchable(),
            // Our own vehicle (the coach is one of our vehicles), a partner's coach, or none yet.
            SelectFilter::make('CoachKind')
                ->label('Ιδιόκτητο όχημα')
                ->options(['own' => 'Δικό μας όχημα', 'partner' => 'Συνεργάτης', 'none' => 'Χωρίς όχημα'])
                ->query(fn (Builder $query, array $data): Builder => FilterValues::any($query, $data, [
                    'own' => fn (Builder $q) => $q->whereIn('CoachCompanyID', DB::table('vehicles')->whereNotNull('VehicleName')->select('VehicleName')),
                    'partner' => fn (Builder $q) => $q->whereRaw("IFNULL(CoachCompanyID, '') <> ''")
                        ->whereNotIn('CoachCompanyID', DB::table('vehicles')->whereNotNull('VehicleName')->select('VehicleName')),
                    'none' => fn (Builder $q) => $q->whereRaw("IFNULL(CoachCompanyID, '') = ''"),
                ])),

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
                ->query(fn (Builder $query, array $data): Builder => FilterValues::any($query, $data, [
                    'Ναι' => fn (Builder $q) => $q->where('TransferDate', '<=', Carbon::today()),
                    'Όχι' => fn (Builder $q) => $q->where('TransferDate', '>', Carbon::today()),
                ])),
            SelectFilter::make('SupplierModified')
                ->label('Supplier modified')
                ->options(['Yes' => 'Yes', 'No' => 'No'])
                ->query(fn (Builder $query, array $data): Builder => FilterValues::any($query, $data, [
                    'Yes' => fn (Builder $q) => $q->where('SupplierModified', 1),
                    'No' => fn (Builder $q) => $q->where('SupplierModified', '<>', 1)->orWhereNull('SupplierModified'),
                ])),
            $values('pricecheck', 'Price check'),
            SelectFilter::make('TaxiReceipt_flag')
                ->label('Taxi receipt')
                ->options(['Yes' => 'Yes', 'No' => 'No'])
                ->query(function (Builder $query, array $data): Builder {
                    $hasReceipt = "if(`CoachCompanyID` in ('VITO 2022','E CLASS 2015'), Get_Taxi_flnm(`TransferID`), 0) > 0";

                    return FilterValues::any($query, $data, [
                        'Yes' => fn (Builder $q) => $q->whereRaw($hasReceipt),
                        'No' => fn (Builder $q) => $q->whereRaw("not ({$hasReceipt})"),
                    ]);
                }),
            $values('CreatedBy', 'Created by'),
            $values('ModifiedBy', 'Modified by'),
        ];
    }
}
