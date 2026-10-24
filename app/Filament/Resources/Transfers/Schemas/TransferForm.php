<?php

namespace App\Filament\Resources\Transfers\Schemas;

use App\Filament\Resources\Transfers\Pages\EditTransfer;
use App\Filament\Resources\Transfers\RelationManagers\ChargesRelationManager;
use App\Filament\Resources\Transfers\RelationManagers\CommsRelationManager;
use App\Filament\Resources\Transfers\RelationManagers\ExpensesRelationManager;
use App\Filament\Resources\Transfers\RelationManagers\FilesRelationManager;
use App\Filament\Resources\Transfers\RelationManagers\NotesRelationManager;
use App\Filament\Resources\Transfers\RelationManagers\PriceLinesRelationManager;
use App\Filament\Resources\Transfers\RelationManagers\TaxiFilesRelationManager;
use App\Models\Counterparty;
use App\Models\Transfer;
use App\Support\Lookups;
use App\Support\TransferRules;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TransferForm
{
    public static function configure(Schema $schema): Schema
    {
        // The tabs of PHPRunner's transfer page, in its order. The lists (notes, charges, products,
        // files, driver comments) are inside their tab and load the first time it is opened; they
        // exist once the transfer is saved, so the new-transfer page shows only the field tabs.
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('transfer')
                    ->contained()
                    ->persistTabInQueryString('tab')
                    ->tabs([
                        self::tab('Transfer Details', Heroicon::OutlinedMapPin, self::detailsTab(), [
                            'ImportDate', 'Status', 'cID_Customer', 'SupplierCode', 'PassengerName', 'LesseeName', 'TransferType', 'Pax',
                            'LeadName', 'LeadPhone', 'TransferDate', 'TransferTime', 'CarTypeID', 'CoachCompanyID', 'From', 'To',
                            'Flight_Ship_Num_From', 'OLP', 'Assistant', 'cID_Assistant', 'Assistant_Price', 'AssistantStatus',
                            'TicketsRequested', 'TicketsValue', 'Notes', 'DriverNotes',
                        ]),
                        self::listTab('Notes', Heroicon::OutlinedChatBubbleLeftRight, NotesRelationManager::class),
                        self::tab('Financials', Heroicon::OutlinedBanknotes, self::financialsTab(), [
                            'SalePrice', 'PriceRcvd', 'ExtraIncome', 'PaymentOnHoldFlag', 'TobeInvoiced', 'InvoicedIssued', 'SalesInvoiceNum',
                            'PaymentType', 'VPOS', 'DriverReceiptType', 'ElorusID', 'PurchasePrice', 'PricePaid', 'InvoicedRcvd',
                            'PurchaseInvoiceNum', 'ExpenseRcvd', 'ExpensePaid', 'Platform', 'PlatformPrice',
                        ]),
                        self::listTab('Products', Heroicon::OutlinedListBullet, PriceLinesRelationManager::class),
                        self::tab('Voucher Date & Time', Heroicon::OutlinedTicket, self::voucherTab(), ['Voucher_StartDt', 'Voucher_StartTime', 'Voucher_EndDt', 'Voucher_EndTime']),
                        self::listTab('Αρχεία', Heroicon::OutlinedPaperClip, FilesRelationManager::class),
                        self::tab('Taxi', Heroicon::OutlinedReceiptPercent, self::taxiTab(), ['TaxiReceipt', 'TaxiReceiptAmount', 'TaxiReceiptDate', 'TaxiReceipt_Issued', 'ElorusTaxiNumber']),
                        self::listTab('Σχόλια Οδηγού!', Heroicon::OutlinedChatBubbleOvalLeftEllipsis, CommsRelationManager::class),
                        Tab::make('Audit')->icon(Heroicon::OutlinedClock)->visibleOn('edit')->schema([self::auditText()]),
                    ]),
            ]);
    }

    /**
     * A tab of fields. A validation error on one of its fields shows as a red "!" on the tab, so it is
     * found even when another tab is open.
     *
     * @param  list<string>  $fields
     */
    private static function tab(string $label, Heroicon $icon, array $components, array $fields): Tab
    {
        $hasError = fn ($livewire): bool => collect($fields)->contains(fn (string $field): bool => $livewire->getErrorBag()->has("data.{$field}"));

        return Tab::make($label)
            ->icon($icon)
            ->badge(fn ($livewire): ?string => $hasError($livewire) ? '!' : null)
            ->badgeColor('danger')
            ->schema($components);
    }

    /** A tab holding one of the transfer's lists (edit page only), loaded when the tab is first opened. */
    private static function listTab(string $label, Heroicon $icon, string $manager): Tab
    {
        return Tab::make($label)
            ->icon($icon)
            ->visible(fn (string $operation, ?Transfer $record): bool => $operation === 'edit' && $record && $manager::canViewForRecord($record, EditTransfer::class))
            ->schema([self::list($manager)]);
    }

    private static function list(string $manager, bool $lazy = true): Livewire
    {
        // Lists in a nested tab load with the page: Livewire's own "lazy" waits for the list to scroll
        // into view, which never happens inside a tab that was hidden at first.
        return Livewire::make($manager, fn (?Transfer $record): array => ['ownerRecord' => $record, 'pageClass' => EditTransfer::class, ...($lazy ? $manager::getDefaultProperties() : ['lazy' => false])])
            ->key($manager)
            ->lazy($lazy)
            ->visible(fn (string $operation, ?Transfer $record): bool => $operation === 'edit' && $record && $manager::canViewForRecord($record, EditTransfer::class));
    }

    /** PHPRunner's red section header. */
    private static function section(string $title, int|array $columns): Section
    {
        return Section::make($title)->compact()->columns($columns)->extraAttributes(['class' => 'myat-form-section']);
    }

    private static function detailsTab(): array
    {
        return [
            self::section('Transfer Details', ['default' => 2, 'lg' => 4])->schema([
                DatePicker::make('ImportDate')->label('Date in')->default(now())->required(),
                Select::make('Status')
                    ->options(fn (): array => Lookups::statusesFor(auth()->user()?->groupid))
                    ->default('Pending')
                    ->required()
                    ->columnSpan(['default' => 1, 'lg' => 2]),
                // Calculated by the database from the open notes; shown, never saved.
                Group::make([
                    TextInput::make('OperationalIssuesFlag')->label('Operational issue')->disabled()->dehydrated(false)->placeholder('No'),
                    TextInput::make('FinancialIssuesFlag')->label('Financial issue')->disabled()->dehydrated(false)->placeholder('No'),
                ])->columns(['default' => 2])->visibleOn('edit')->columnSpan(['default' => 2, 'lg' => 1]),
                Select::make('cID_Customer')
                    ->label('Supplier')
                    ->options(fn (): array => Lookups::customers())
                    ->searchable()
                    ->required()
                    ->columnSpan(['default' => 2, 'lg' => 1])
                    // PHPRunner's "Add new" on the customer lookup.
                    ->createOptionForm([
                        TextInput::make('CounterShortName')->label('Επωνυμία')->required()->maxLength(500),
                        TextInput::make('SupplierName')->label('Name in customer lists')->required()->maxLength(150),
                        TextInput::make('LesseName')->label('Lessee name')->maxLength(150),
                        TextInput::make('Email')->email()->maxLength(500),
                        TextInput::make('Phones')->label('Τηλέφωνα')->maxLength(500),
                        TextInput::make('VAT')->label('ΑΦΜ')->maxLength(150),
                    ])
                    ->createOptionUsing(fn (array $data): int => Counterparty::query()->create($data + ['IsSupplier' => 1, 'Active' => '1'])->getKey())
                    ->createOptionAction(fn ($action) => $action->modalHeading('New customer')->visible(fn (): bool => (bool) auth()->user()?->may('counterparties', 'add')))
                    // PHPRunner autofill (add page only): picking the customer fills in its lessee.
                    ->live()
                    ->afterStateUpdated(function (string $operation, $state, Set $set): void {
                        if ($operation === 'create' && filled($state)) {
                            $set('LesseeName', (string) DB::table('counterparties')->where('CID', $state)->value('LesseName'));
                        }
                    }),
                TextInput::make('SupplierCode')->label('Sup Code')->maxLength(50),
                Textarea::make('PassengerName')->label('Passenger List')->rows(2)->autosize(),
                Textarea::make('LesseeName')
                    ->label('Lessee Name')
                    ->rows(2)
                    ->autosize()
                    ->hintIcon(Heroicon::OutlinedInformationCircle, 'Filled from the customer when you pick it; if left empty, it is filled when saved.'),
                Select::make('TransferType')->label('Type')->options(fn (): array => Lookups::optionValues('TransferType'))->required(),
                TextInput::make('Pax')->maxLength(50),
                Textarea::make('LeadName')
                    ->label('Lead Name')
                    ->rows(2)
                    ->autosize()
                    // PHPRunner (add page): for some customers the lessee is the lead passenger.
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, ?string $state, Get $get, Set $set): void {
                        // The customer's "Lessee = lead passenger" flag (counterparties.LeadName), as the
                        // database trigger uses it; PHPRunner's page had customers 11, 37, 117 hard-coded.
                        if ($operation === 'create' && filled($get('cID_Customer'))
                            && (int) DB::table('counterparties')->where('CID', $get('cID_Customer'))->value('LeadName') === 1) {
                            $set('LesseeName', $state);
                        }
                    }),
                Textarea::make('LeadPhone')->label('Lead Phone')->rows(2)->autosize(),
                DatePicker::make('TransferDate')
                    ->label('Transfer Date')
                    ->required()
                    ->rule(fn (string $operation, ?Transfer $record, Get $get): Closure => self::transferDateRule($operation, $record, $get)),
                TextInput::make('TransferTime')->label('Transfer Time')->placeholder('14:30 or TBD')->maxLength(50)->required(),
                Select::make('CarTypeID')->label('Car Type')->options(fn (): array => Lookups::optionValues('CarType'))->required(),
                Select::make('CoachCompanyID')
                    ->label('Coach')
                    ->options(fn (): array => Lookups::coaches())
                    ->searchable()
                    ->columnSpan(['default' => 2, 'lg' => 1])
                    ->hintIcon(Heroicon::OutlinedInformationCircle, 'Own vehicles set purchase price to 0 when saved.'),
            ]),
            Grid::make(['default' => 1, 'lg' => 2])->schema([
                self::section('From', ['default' => 2, 'lg' => 3])->schema([
                    // PHPRunner allows free input here: suggestions from the list, any text accepted.
                    TextInput::make('From')->datalist(fn (): array => array_keys(Lookups::pickupPlaces()))->autocomplete(false)->required(),
                    TextInput::make('To')->datalist(fn (): array => array_keys(Lookups::destinations()))->autocomplete(false)->columnSpan(['default' => 1, 'lg' => 2]),
                    Textarea::make('Flight_Ship_Num_From')->label('Flight / Ship')->rows(2)->columnSpan(['default' => 2, 'lg' => 2]),
                    Checkbox::make('OLP')->label('ΟΛΠ')->inline(false),
                ]),
                self::section('Licensed Tour Guide', 2)->schema([
                    TextInput::make('Assistant')->datalist(fn (): array => array_keys(Lookups::assistantNames()))->autocomplete(false),
                    TextInput::make('Assistant_Price')->label('Guide Rate')->numeric()->prefix('€'),
                    Select::make('AssistantStatus')
                        ->label('Guide status')
                        ->options(array_combine($values = ['Request', 'Client Rejected', 'Payment Pending', 'Payment Received'], $values)),
                    Select::make('TicketsRequested')
                        ->label('Tickets')
                        ->options(array_combine($values = ['Contacted', 'Paid in Advance', 'Cash', "Purchased by\u{a0}client", 'No tickets needed'], $values)),
                    Select::make('cID_Assistant')->label('Assistant company')->options(fn (): array => Lookups::assistantCompanies())->searchable(),
                    TextInput::make('TicketsValue')->label('Tickets Value')->numeric()->prefix('€'),
                ]),
            ]),
            Grid::make(['default' => 1, 'lg' => 2])->schema([
                Textarea::make('Notes')->label('Transfer Notes')->rows(3)->autosize(),
                Textarea::make('DriverNotes')
                    ->label('Driver Notes')
                    ->rows(3)
                    ->autosize()
                    // PHPRunner's ">>" button between the two.
                    ->hintAction(Action::make('copyNotes')
                        ->label('>> Copy from Transfer Notes')
                        ->icon(Heroicon::OutlinedArrowRight)
                        ->action(fn (Get $get, Set $set) => $set('DriverNotes', $get('Notes')))),
            ]),
        ];
    }

    private static function financialsTab(): array
    {
        $money = fn (string $name, string $label): TextInput => TextInput::make($name)->label($label)->numeric()->prefix('€');
        $calculated = fn (string $name, string $label): TextInput => $money($name, $label)->disabled()->dehydrated(false);

        return [
            self::section('Τιμολόγηση', 1)->schema([
                Grid::make(['default' => 1, 'lg' => 2])->schema([
                    Fieldset::make('Έσοδα')->columns(['default' => 2, 'lg' => 3])->schema([
                        $money('SalePrice', 'Τιμή Πώλ.')
                            ->hint(fn (?Transfer $record): ?string => $record?->priceLines()->where('ProductID', '<>', '')->exists() ? 'From the products' : null),
                        $money('PriceRcvd', 'Εισπραχθ.'),
                        $money('ExtraIncome', 'Add charges'),
                        Select::make('PaymentOnHoldFlag')->label('Αναμ. Έκδ.')->options(['Ναι' => 'Ναι']),
                        ToggleButtons::make('TobeInvoiced')
                            ->label('Τύπος παραστ.')
                            ->options(['Όχι' => 'Όχι', 'Τιμολόγιο' => 'Τιμολόγιο', 'Απόδειξη' => 'Απόδειξη'])
                            ->inline()
                            ->hintIcon(Heroicon::OutlinedInformationCircle, 'Left empty, it is filled from the customer when saved.')
                            ->columnSpan(2),
                        Select::make('InvoicedIssued')->label('Εκδόθηκε παραστ.')->options(['Ναι' => 'Ναι']),
                        TextInput::make('SalesInvoiceNum')->label('ΑΑ Εσόδων')->columnSpan(['default' => 1, 'lg' => 2]),
                        Select::make('PaymentType')
                            ->label('Τρόπος πληρ.')
                            ->options(array_combine($values = ['Viator', 'Cash', 'PayPal', 'Eurobank', 'Viva', 'Why Athens', 'GYG'], $values))
                            ->hintIcon(Heroicon::OutlinedInformationCircle, 'Filled from the customer when you pick it; if left empty, it is filled when saved.'),
                        TextInput::make('VPOS')->label('Kωδ. πληρ.')->columnSpan(['default' => 1, 'lg' => 2]),
                        Select::make('DriverReceiptType')->label('Driver Receipt Type')->options(['Cash' => 'Cash', 'Card' => 'Card']),
                        TextInput::make('ElorusID')->label('Elorus ID'),
                    ]),
                    Fieldset::make('Έξοδα')->columns(['default' => 2, 'lg' => 3])->schema([
                        $money('PurchasePrice', 'Τιμή Αγορ.'),
                        $money('PricePaid', 'Πληρωθ.'),
                        // As in PHPRunner: not typed on the form (it comes from the expenses below).
                        $calculated('Expense', 'Έξοδα'),
                        Select::make('InvoicedRcvd')->label('Έλαβα Τιμ.')->options(['Δε θα λάβω' => 'Δε θα λάβω', 'Έχω λάβει' => 'Έχω λάβει']),
                        Select::make('ExpenseRcvd')->label('Παρ. εξ.')->options(['Ναί' => 'Ναί', 'Όχι' => 'Όχι']),
                        Select::make('ExpensePaid')->label('Απ. Εξ.')->options(['Ναί' => 'Ναί', 'Όχι' => 'Όχι']),
                        TextInput::make('PurchaseInvoiceNum')->label('ΑΑ Εξόδων')->columnSpanFull(),
                    ]),
                ]),
            ]),
            Grid::make(['default' => 1, 'lg' => 2])->schema([
                self::section('Στοιχεία Πλατφόρμας', 2)->schema([
                    // The database triggers write both "Ναι" and "Ναί"; the form shows (and saves) "Ναι".
                    Select::make('Platform')->label('Πλατφ.')->options(['Όχι' => 'Όχι', 'Ναι' => 'Ναι'])->live()
                        ->afterStateHydrated(fn (Select $component, $state) => $state === 'Ναί' ? $component->state('Ναι') : null),
                    // Only when the booking came through a platform; a stored price is kept as it is.
                    $money('PlatformPrice', 'Τιμή Πλατφ.')->disabled(fn (Get $get): bool => $get('Platform') !== 'Ναι')->dehydrated(),
                ]),
                // Calculated by the database (GENERATED columns): shown, never saved.
                self::section('Σύνολα', 2)->visibleOn('edit')->schema([
                    $calculated('Assets', 'Απαιτ.'),
                    $calculated('CashRegister', 'Ταμείο'),
                    $calculated('Liabilities', 'Υποχρ.'),
                    $calculated('Income', 'Έσοδο'),
                ]),
            ]),
            self::section('Πρόσθετες Χρεώσεις', 1)
                ->visible(fn (string $operation): bool => $operation === 'edit')
                ->schema([
                    Tabs::make('charges')->tabs([
                        Tab::make('Έσοδα')->schema([self::list(ChargesRelationManager::class, lazy: false)]),
                        Tab::make('Έξοδα')->schema([self::list(ExpensesRelationManager::class, lazy: false)]),
                    ]),
                ]),
        ];
    }

    private static function voucherTab(): array
    {
        $calc = fn (?Transfer $record, string $column, string $format): ?string => $record?->{$column}
            ? Carbon::parse($record->{$column})->format($format)
            : null;
        $shown = fn (string $label, string $column, string $format): TextInput => TextInput::make("{$column}_shown")
            ->label($label)
            ->disabled()
            ->dehydrated(false)
            ->placeholder('—')
            ->formatStateUsing(fn (?Transfer $record): ?string => $calc($record, $column, $format));

        return [
            Grid::make(['default' => 1, 'lg' => 2])->schema([
                self::section('Start', 2)->schema([
                    $shown('Ημερομηνία έναρξης', 'Voucher_Start_Date_Calc', 'd/m/Y'),
                    $shown('Ώρα έναρξης', 'Voucher_Start_StartTime_Calc', 'H:i'),
                    DatePicker::make('Voucher_StartDt')->label('Ημερομηνία έναρξης New'),
                    TimePicker::make('Voucher_StartTime')->label('Ώρα έναρξης New')->seconds(false),
                ]),
                self::section('End', 2)->schema([
                    $shown('Ημερομηνία λήξης', 'Voucher_End_Date_Calc', 'd/m/Y'),
                    $shown('Ώρα λήξης', 'Voucher_End_Time_Calc', 'H:i'),
                    DatePicker::make('Voucher_EndDt')->label('Ημερομηνία λήξης New'),
                    TimePicker::make('Voucher_EndTime')->label('Ώρα λήξης New')->seconds(false),
                ]),
            ]),
        ];
    }

    private static function taxiTab(): array
    {
        return [
            self::section('Taxi', ['default' => 2, 'lg' => 5])->schema([
                TextInput::make('TaxiReceipt')->label('Taxi Receipt')->maxLength(50),
                TextInput::make('TaxiReceiptAmount')->label('Taxi Receipt Amount')->numeric()->prefix('€'),
                DatePicker::make('TaxiReceiptDate')->label('Taxi Receipt Date'),
                TextInput::make('TaxiReceipt_Issued')->label('TaxiReceipt Issued')->maxLength(50),
                TextInput::make('ElorusTaxiNumber')->label('Elorus Taxi Number')->maxLength(150),
            ]),
            self::list(TaxiFilesRelationManager::class),
        ];
    }

    private static function auditText(): Text
    {
        return Text::make(function (?Transfer $record): ?string {
            if (! $record || (! $record->CreatedBy && ! $record->CreatedTimestamp && ! $record->ModifiedTimestamp)) {
                return null;
            }

            $created = 'Created'.($record->CreatedBy ? " by {$record->CreatedBy}" : '')
                .($record->CreatedTimestamp ? ' on '.$record->CreatedTimestamp->format('d/m/Y H:i') : '');
            $modified = $record->ModifiedTimestamp
                ? ' · changed'.($record->ModifiedBy ? " by {$record->ModifiedBy}" : '').' on '.$record->ModifiedTimestamp->format('d/m/Y H:i')
                : '';

            return $created.$modified;
        })->color('gray')->size('sm');
    }

    private static function transferDateRule(string $operation, ?Transfer $record, Get $get): Closure
    {
        return function (string $attribute, $value, Closure $fail) use ($operation, $record, $get): void {
            if ($error = TransferRules::transferDateError($value, $record, $get('ImportDate'), $operation === 'create')) {
                $fail($error);
            }
        };
    }
}
