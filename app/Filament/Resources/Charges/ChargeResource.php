<?php

namespace App\Filament\Resources\Charges;

use App\Filament\Concerns\UsesPhpRunnerPermissions;
use App\Filament\Resources\Charges\Pages\EditCharge;
use App\Filament\Resources\Charges\Pages\ListCharges;
use App\Filament\Resources\Transfers\Actions\InvoicingActions;
use App\Filament\Resources\Transfers\TransferResource;
use App\Models\TransferCharge;
use App\Support\ColorDot;
use App\Support\FilterValues;
use App\Support\Lookups;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * PHPRunner "Χρεώσεις": all charge lines of all transfers. Two tabs as PHPRunner's two
 * lists: Έσοδα (transfer_charges, ChargeValue) and Έξοδα (transfer_charges1, Expense).
 */
class ChargeResource extends Resource
{
    use UsesPhpRunnerPermissions;

    protected static string $permissionTable = 'transfer_charges';

    protected static ?string $model = TransferCharge::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Χρεώσεις';

    /** List page title and breadcrumb: the same as the menu item. */
    protected static ?string $pluralModelLabel = 'Χρεώσεις';

    protected static ?string $modelLabel = 'charge';

    protected static ?string $slug = 'charges';

    private const YES_NO = ['Ναι' => 'Ναι', 'Όχι' => 'Όχι'];

    public static function form(Schema $schema): Schema
    {
        $income = fn (?TransferCharge $record): bool => (float) $record?->ChargeValue !== 0.0;

        return $schema->components([
            Section::make('Έσοδο')->columns(3)->columnSpanFull()->visible($income)->schema([
                Textarea::make('TransferCharge')->label('Αιτία')->rows(2)->columnSpanFull(),
                DatePicker::make('ChargeDate')->label('Ημερομηνία'),
                TextInput::make('ChargeValue')->label('Χρέωση')->numeric()->prefix('€')->required(),
                Select::make('PaymentVia')->label('Τρόπος πληρ.')->options(TransferCharge::PAYMENT_VIA),
                Select::make('PaymentStatus')->label('Κατ. πληρωμής')->options(['Paid' => 'Paid'])->placeholder('Not paid'),
                TextInput::make('PaymentCode')->label('Κωδ. πληρωμής'),
                TextInput::make('InvoiceNum')->label('Invoice no.'),
                Toggle::make('TobeInvoiced')->label('Θα τιμολογηθεί'),
                Toggle::make('Invoiced')->label('Τιμολογήθηκε'),
            ]),
            Section::make('Έξοδο')->columns(3)->columnSpanFull()->visible(fn (?TransferCharge $record): bool => ! $income($record))->schema([
                Select::make('ExpenseChargeCategory')->label('Κατηγορία')->options(fn (): array => Lookups::optionIds('ExpenseChargeCategory')),
                TextInput::make('Expense')->label('Έξοδο')->numeric()->prefix('€')->required(),
                Textarea::make('ExpenseReason')->label('Αιτία')->rows(1)->maxLength(50),
                Select::make('ExpenseReceived')->label('Παρ. έξοδο')->options(self::YES_NO),
                Select::make('ExpensePaid')->label('Απ. έξοδο')->options(self::YES_NO),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $income = fn ($livewire): bool => ($livewire->activeTab ?? 'income') === 'income';
        $expense = fn ($livewire): bool => ! $income($livewire);
        $transfer = fn (string $column, string $label) => TextColumn::make("transfer.{$column}")->label($label)->toggleable();
        $yesNo = fn (string $name, string $label) => TextColumn::make($name)->label($label)->badge()->visible($expense)
            ->getStateUsing(fn (TransferCharge $r): string => $r->{$name} ?: 'Όχι')->color(fn (string $state): string => $state === 'Ναι' ? 'success' : 'gray');

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withTransfer())
            ->defaultSort('ID', 'desc')
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->columns([
                TextColumn::make('TransferCharge')->label('Αιτία')->wrap()->searchable()->visible($income),
                TextColumn::make('ExpenseChargeCategory')->label('Κατηγορία')->visible($expense)
                    ->formatStateUsing(fn ($state) => ColorDot::render(Lookups::optionIds('ExpenseChargeCategory')[$state] ?? null, Lookups::color('ExpenseChargeCategory', Lookups::optionIds('ExpenseChargeCategory')[$state] ?? ''))),
                TextColumn::make('ExpenseReason')->label('Αιτία')->wrap()->searchable()->visible($expense),
                TextColumn::make('ChargeValue')->label('Χρέωση')->money('EUR', locale: 'el')->alignEnd()->sortable()->visible($income)
                    ->summarize(Sum::make()->money('EUR', locale: 'el')->label('')),
                TextColumn::make('Expense')->label('Έξοδο')->money('EUR', locale: 'el')->alignEnd()->sortable()->visible($expense)
                    ->summarize(Sum::make()->money('EUR', locale: 'el')->label('')),
                TextColumn::make('PaymentVia')->label('Τρόπος πληρ.')->visible($income)->placeholder('—'),
                IconColumn::make('TobeInvoiced')->label('Θα τιμολ.')->boolean()->visible($income)->getStateUsing(fn (TransferCharge $r): bool => (bool) $r->TobeInvoiced),
                IconColumn::make('Invoiced')->label('Τιμολ.')->boolean()->visible($income)->getStateUsing(fn (TransferCharge $r): bool => (bool) $r->Invoiced),
                TextColumn::make('PaymentStatus')->label('Πληρωμή')->badge()->visible($income)
                    ->getStateUsing(fn (TransferCharge $r): string => $r->PaymentStatus ?: 'Not paid')->color(fn (string $state): string => $state === 'Paid' ? 'success' : 'warning'),
                TextColumn::make('InvoiceNum')->label('Invoice no.')->visible($income)->toggleable(),
                TextColumn::make('PaymentCode')->label('Κωδ. πληρωμής')->visible($income)->toggleable(isToggledHiddenByDefault: true),
                $yesNo('ExpenseReceived', 'Παρ. έξοδο'),
                $yesNo('ExpensePaid', 'Απ. έξοδο'),
                TextColumn::make('TransferID')->label('Transfer')->sortable()
                    ->url(fn (TransferCharge $r): string => TransferResource::getUrl('edit', ['record' => $r->TransferID])),
                $transfer('TransferDate', 'Transfer date')->date('d/m/Y'),
                $transfer('TransferTime', 'Time'),
                $transfer('From', 'From'),
                $transfer('To', 'To'),
                $transfer('CoachCompanyID', 'Coach'),
                TextColumn::make('transfer.Status')->label('Transfer status')->toggleable()
                    ->formatStateUsing(fn ($state) => ColorDot::render($state, Lookups::color('Status', (string) $state))),
            ])
            ->filters([
                SelectFilter::make('PaymentVia')->label('Τρόπος πληρ.')->options(TransferCharge::PAYMENT_VIA),
                SelectFilter::make('paid')->label('Πληρωμή')->options(['paid' => 'Paid', 'unpaid' => 'Not paid'])
                    ->query(fn (Builder $q, array $data) => FilterValues::any($q, $data, [
                        'paid' => fn (Builder $q) => $q->where('PaymentStatus', 'Paid'),
                        'unpaid' => fn (Builder $q) => $q->whereNull('PaymentStatus')->orWhere('PaymentStatus', '<>', 'Paid'),
                    ])),
                SelectFilter::make('ExpenseChargeCategory')->label('Κατηγορία')->options(fn (): array => Lookups::optionIds('ExpenseChargeCategory')),
                Filter::make('dates')->columnSpanFull()->columns(2)->schema([
                    DatePicker::make('from')->label('Transfer date from'),
                    DatePicker::make('until')->label('until'),
                ])->query(fn (Builder $q, array $data): Builder => $q
                    ->when($data['from'] ?? null, fn ($q, $d) => $q->whereHas('transfer', fn ($t) => $t->whereDate('TransferDate', '>=', $d)))
                    ->when($data['until'] ?? null, fn ($q, $d) => $q->whereHas('transfer', fn ($t) => $t->whereDate('TransferDate', '<=', $d)))),
            ])
            ->recordActions([
                Action::make('transfer')->label('Transfer')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                    ->url(fn (TransferCharge $r): string => TransferResource::getUrl('edit', ['record' => $r->TransferID])),
                EditAction::make(),
            ])
            ->toolbarActions([InvoicingActions::chargeReceipts()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCharges::route('/'),
            'edit' => EditCharge::route('/{record}/edit'),
        ];
    }
}
