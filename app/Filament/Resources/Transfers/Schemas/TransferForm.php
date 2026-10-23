<?php

namespace App\Filament\Resources\Transfers\Schemas;

use App\Models\Counterparty;
use App\Models\Transfer;
use App\Support\Lookups;
use App\Support\TransferRules;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Group;
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
        return $schema
            ->columns(1)
            ->components([
                // What every transfer needs, always in view (all the required fields are here, so a
                // missing one is never hidden behind a tab).
                self::essentials(),
                // Everything else, one tab per group.
                Tabs::make('details')
                    ->contained()
                    ->persistTabInQueryString('tab')
                    ->tabs([
                        self::tab('Trip', Heroicon::OutlinedMapPin, self::tripTab(), ['SupplierCode', 'ImportDate', 'Flight_Ship_Num_From', 'TicketsRequested', 'PaymentType']),
                        self::tab('Passenger', Heroicon::OutlinedUser, self::passengerTab(), ['PassengerName', 'LeadName', 'LeadPhone', 'LesseeName', 'Notes', 'DriverNotes']),
                        self::tab('Pricing', Heroicon::OutlinedBanknotes, self::pricingTab(), ['PurchasePrice', 'SalePrice', 'PricePaid', 'PriceRcvd', 'ExtraIncome', 'Platform', 'PlatformPrice', 'TobeInvoiced', 'InvoicedIssued', 'InvoicedRcvd', 'SalesInvoiceNum', 'PurchaseInvoiceNum', 'ExpenseRcvd', 'ExpensePaid', 'ElorusID', 'VPOS', 'PaymentOnHoldFlag']),
                        self::tab('Assistant', Heroicon::OutlinedUserGroup, self::assistantTab(), ['Assistant', 'cID_Assistant', 'Assistant_Price', 'AssistantStatus']),
                        self::tab('Voucher', Heroicon::OutlinedTicket, self::voucherTab(), ['Voucher_StartDt', 'Voucher_StartTime', 'Voucher_EndDt', 'Voucher_EndTime']),
                    ]),
                self::auditText(),
            ]);
    }

    /**
     * A tab of the form. A validation error on one of its fields shows as a red "!" on the tab, so it is
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
            ->columns(['default' => 2, 'lg' => 4])
            ->schema($components);
    }

    private static function essentials(): Section
    {
        return Section::make()
            ->compact()
            ->columns(['default' => 2, 'lg' => 4, 'xl' => 6])
            ->extraAttributes(['class' => 'myat-form-essentials'])
            ->components([
                Select::make('cID_Customer')
                    ->label('Customer')
                    ->options(fn (): array => Lookups::customers())
                    ->searchable()
                    ->required()
                    ->columnSpan(['default' => 2, 'lg' => 2])
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
                Select::make('TransferType')
                    ->label('Type')
                    ->options(fn (): array => Lookups::optionValues('TransferType'))
                    ->required(),
                Select::make('Status')
                    ->options(fn (): array => Lookups::statusesFor(auth()->user()?->groupid))
                    ->default('Pending')
                    ->required(),
                DatePicker::make('TransferDate')
                    ->label('Date')
                    ->required()
                    ->rule(fn (string $operation, ?Transfer $record, Get $get): Closure => self::transferDateRule($operation, $record, $get)),
                TextInput::make('TransferTime')
                    ->label('Time')
                    ->placeholder('14:30 or TBD')
                    ->maxLength(50)
                    ->required(),
                // PHPRunner allows free input here: suggestions from the list, any text accepted.
                TextInput::make('From')
                    ->datalist(fn (): array => array_keys(Lookups::pickupPlaces()))
                    ->autocomplete(false)
                    ->required()
                    ->columnSpan(['default' => 2, 'lg' => 2]),
                TextInput::make('To')
                    ->datalist(fn (): array => array_keys(Lookups::destinations()))
                    ->autocomplete(false)
                    ->columnSpan(['default' => 2, 'lg' => 2]),
                TextInput::make('Pax')
                    ->maxLength(50),
                Select::make('CarTypeID')
                    ->label('Car type')
                    ->options(fn (): array => Lookups::optionValues('CarType'))
                    ->required(),
                Select::make('CoachCompanyID')
                    ->label('Vehicle / coach')
                    ->options(fn (): array => Lookups::coaches())
                    ->searchable()
                    ->hintIcon(Heroicon::OutlinedInformationCircle, 'Own vehicles set purchase price to 0 when saved.')
                    ->columnSpan(['default' => 2, 'lg' => 1, 'xl' => 2]),
                // Calculated by the database from the open notes; shown, never saved.
                TextInput::make('OperationalIssuesFlag')
                    ->label('Operational issue')
                    ->disabled()
                    ->dehydrated(false)
                    ->placeholder('No')
                    ->visibleOn('edit'),
                TextInput::make('FinancialIssuesFlag')
                    ->label('Financial issue')
                    ->disabled()
                    ->dehydrated(false)
                    ->placeholder('No')
                    ->visibleOn('edit'),
            ]);
    }

    private static function tripTab(): array
    {
        return [
            TextInput::make('SupplierCode')
                ->label('Supplier code')
                ->maxLength(50),
            DatePicker::make('ImportDate')
                ->label('Booked on')
                ->default(now())
                ->required(),
            Select::make('TicketsRequested')
                ->label('Tickets')
                ->options(array_combine($values = ['Contacted', 'Paid in Advance', 'Cash', "Purchased by\u{a0}client", 'No tickets needed'], $values)),
            Select::make('PaymentType')
                ->label('Payment type')
                ->options(array_combine($values = ['Viator', 'Cash', 'PayPal', 'Eurobank', 'Viva', 'Why Athens', 'GYG'], $values))
                ->hintIcon(Heroicon::OutlinedInformationCircle, 'Filled from the customer when you pick it; if left empty, it is filled when saved.'),
            Textarea::make('Flight_Ship_Num_From')
                ->label('Flight / ship')
                ->rows(2)
                ->columnSpanFull(),
        ];
    }

    private static function passengerTab(): array
    {
        return [
            Textarea::make('LeadName')
                ->label('Lead name')
                ->rows(1)
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
                })
                ->columnSpan(['default' => 2, 'lg' => 2]),
            Textarea::make('LeadPhone')->label('Lead phone')->rows(1)->autosize(),
            Textarea::make('LesseeName')
                ->label('Lessee')
                ->rows(1)
                ->autosize()
                ->hintIcon(Heroicon::OutlinedInformationCircle, 'Filled from the customer when you pick it; if left empty, it is filled when saved.'),
            Textarea::make('PassengerName')->label('Passenger name(s)')->rows(2)->autosize()->columnSpanFull(),
            Textarea::make('Notes')->rows(2)->autosize()->columnSpan(['default' => 2, 'lg' => 2]),
            Textarea::make('DriverNotes')
                ->label('Driver notes')
                ->rows(2)
                ->autosize()
                ->columnSpan(['default' => 2, 'lg' => 2])
                // PHPRunner's ">>" button next to Notes.
                ->hintAction(Action::make('copyNotes')
                    ->label('Copy from Notes')
                    ->icon(Heroicon::OutlinedArrowDown)
                    ->action(fn (Get $get, Set $set) => $set('DriverNotes', $get('Notes')))),
        ];
    }

    private static function assistantTab(): array
    {
        return [
            TextInput::make('Assistant')
                ->datalist(fn (): array => array_keys(Lookups::assistantNames()))
                ->autocomplete(false)
                ->columnSpan(['default' => 2, 'lg' => 1]),
            Select::make('cID_Assistant')
                ->label('Assistant company')
                ->options(fn (): array => Lookups::assistantCompanies())
                ->searchable()
                ->columnSpan(['default' => 2, 'lg' => 1]),
            TextInput::make('Assistant_Price')
                ->label('Assistant price')
                ->numeric()
                ->prefix('€'),
            Select::make('AssistantStatus')
                ->label('Assistant status')
                ->options(array_combine($values = ['Request', 'Client Rejected', 'Payment Pending', 'Payment Received'], $values)),
        ];
    }

    private static function pricingTab(): array
    {
        $money = fn (string $name, string $label): TextInput => TextInput::make($name)
            ->label($label)
            ->numeric()
            ->prefix('€');

        return [
            $money('PurchasePrice', 'Purchase'),
            $money('SalePrice', 'Sale')
                ->hint(fn (?Transfer $record): ?string => $record?->priceLines()->where('ProductID', '<>', '')->exists()
                    ? 'From the price lines'
                    : null),
            $money('PricePaid', 'Paid'),
            $money('PriceRcvd', 'Received'),
            $money('ExtraIncome', 'Extra income'),
            // As in PHPRunner: not typed on the form (it is edited in the list / comes from charges).
            $money('Expense', 'Expenses')->disabled()->dehydrated(false),
            // The database triggers write both "Ναι" and "Ναί"; the form shows (and saves) "Ναι".
            Select::make('Platform')->options(['Όχι' => 'Όχι', 'Ναι' => 'Ναι'])->live()
                ->afterStateHydrated(fn (Select $component, $state) => $state === 'Ναί' ? $component->state('Ναι') : null),
            // Only when the booking came through a platform; a stored price is kept as it is.
            $money('PlatformPrice', 'Platform price')
                ->disabled(fn (Get $get): bool => $get('Platform') !== 'Ναι')
                ->dehydrated(),
            ToggleButtons::make('TobeInvoiced')
                ->label('To be invoiced')
                ->options(['Όχι' => 'Όχι', 'Τιμολόγιο' => 'Τιμολόγιο', 'Απόδειξη' => 'Απόδειξη'])
                ->inline()
                ->hintIcon(Heroicon::OutlinedInformationCircle, 'Left empty, it is filled from the customer when saved.')
                ->columnSpan(['default' => 2, 'lg' => 2]),
            Select::make('InvoicedIssued')->label('Invoice issued')->options(['Ναι' => 'Ναι']),
            Select::make('InvoicedRcvd')
                ->label('Purchase invoice')
                ->options(['Δε θα λάβω' => 'Δε θα λάβω', 'Έχω λάβει' => 'Έχω λάβει']),
            TextInput::make('SalesInvoiceNum')->label('Sales invoice no.'),
            TextInput::make('PurchaseInvoiceNum')->label('Purchase invoice no.'),
            Select::make('ExpenseRcvd')->label('Expenses received')->options(['Ναί' => 'Ναί', 'Όχι' => 'Όχι']),
            Select::make('ExpensePaid')->label('Expenses paid')->options(['Ναί' => 'Ναί', 'Όχι' => 'Όχι']),
            TextInput::make('ElorusID')->label('Elorus ID'),
            TextInput::make('VPOS')->label('VPOS'),
            Select::make('PaymentOnHoldFlag')->label('Payment on hold')->options(['Ναι' => 'Ναι']),
        ];
    }

    private static function voucherTab(): array
    {
        $calc = fn (?Transfer $record, string $column, string $format): ?string => $record?->{$column}
            ? 'Calculated: '.Carbon::parse($record->{$column})->format($format)
            : null;

        return [
            DatePicker::make('Voucher_StartDt')->label('Start date')
                ->helperText(fn (?Transfer $record) => $calc($record, 'Voucher_Start_Date_Calc', 'd/m/Y')),
            TimePicker::make('Voucher_StartTime')->label('Start time')->seconds(false)
                ->helperText(fn (?Transfer $record) => $calc($record, 'Voucher_Start_StartTime_Calc', 'H:i')),
            DatePicker::make('Voucher_EndDt')->label('End date')
                ->helperText(fn (?Transfer $record) => $calc($record, 'Voucher_End_Date_Calc', 'd/m/Y')),
            TimePicker::make('Voucher_EndTime')->label('End time')->seconds(false)
                ->helperText(fn (?Transfer $record) => $calc($record, 'Voucher_End_Time_Calc', 'H:i')),
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
