<?php

namespace App\Filament\Resources\Transfers\Schemas;

use App\Models\Transfer;
use App\Support\Lookups;
use App\Support\TransferRules;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

class TransferForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make()
                    ->columnSpan(['lg' => 2])
                    ->components([
                        self::tripSection(),
                        self::passengerSection(),
                        self::assistantSection(),
                    ]),
                Group::make()
                    ->columnSpan(['lg' => 1])
                    ->components([
                        self::statusSection(),
                        self::pricingSection(),
                        self::voucherSection(),
                        self::auditText(),
                    ]),
            ]);
    }

    private static function tripSection(): Section
    {
        return Section::make('Trip')
            ->columns(['md' => 2, 'xl' => 3])
            ->components([
                Select::make('cID_Customer')
                    ->label('Customer')
                    ->options(fn (): array => Lookups::customers())
                    ->searchable()
                    ->required(),
                Select::make('TransferType')
                    ->label('Type')
                    ->options(fn (): array => Lookups::optionValues('TransferType'))
                    ->required(),
                TextInput::make('SupplierCode')
                    ->label('Supplier code')
                    ->maxLength(50),
                DatePicker::make('ImportDate')
                    ->label('Booked on')
                    ->default(now())
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
                Select::make('From')
                    ->options(fn (): array => Lookups::pickupPlaces())
                    ->searchable()
                    ->required(),
                Select::make('To')
                    ->options(fn (): array => Lookups::destinations())
                    ->searchable(),
                TextInput::make('Pax')
                    ->maxLength(50),
                Textarea::make('Flight_Ship_Num_From')
                    ->label('Flight / ship')
                    ->rows(2)
                    ->columnSpan(['md' => 2, 'xl' => 1]),
                Select::make('CarTypeID')
                    ->label('Car type')
                    ->options(fn (): array => Lookups::optionValues('CarType'))
                    ->required(),
                Select::make('CoachCompanyID')
                    ->label('Vehicle / coach company')
                    ->options(fn (): array => Lookups::coaches())
                    ->searchable()
                    ->helperText('Own vehicles set purchase price to 0 when saved.'),
            ]);
    }

    private static function passengerSection(): Section
    {
        return Section::make('Passenger')
            ->columns(2)
            ->components([
                Textarea::make('PassengerName')->label('Passenger name(s)')->rows(2),
                Textarea::make('LeadName')
                    ->label('Lead name')
                    ->rows(2)
                    // PHPRunner (add page): for some customers the lessee is the lead passenger.
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, ?string $state, Get $get, Set $set): void {
                        if ($operation === 'create' && in_array((int) $get('cID_Customer'), config('transfers.lessee_is_lead_for_customers', []), true)) {
                            $set('LesseeName', $state);
                        }
                    }),
                Textarea::make('LeadPhone')->label('Lead phone')->rows(1),
                Textarea::make('LesseeName')
                    ->label('Lessee')
                    ->rows(1)
                    ->helperText('Left empty, it is filled from the customer when saved.'),
                Textarea::make('Notes')->rows(3)->columnSpanFull(),
                Textarea::make('DriverNotes')->label('Driver notes')->rows(2)->columnSpanFull(),
            ]);
    }

    private static function assistantSection(): Section
    {
        return Section::make('Assistant')
            ->columns(['md' => 2, 'xl' => 4])
            ->collapsible()
            ->collapsed(fn (?Transfer $record): bool => blank($record?->Assistant))
            ->components([
                Select::make('Assistant')
                    ->options(fn (): array => Lookups::assistantNames())
                    ->searchable(),
                Select::make('cID_Assistant')
                    ->label('Assistant company')
                    ->options(fn (): array => Lookups::assistantCompanies())
                    ->searchable(),
                TextInput::make('Assistant_Price')
                    ->label('Assistant price')
                    ->numeric()
                    ->prefix('€'),
                Select::make('AssistantStatus')
                    ->label('Assistant status')
                    ->options(array_combine($values = ['Request', 'Client Rejected', 'Payment Pending', 'Payment Received'], $values)),
            ]);
    }

    private static function statusSection(): Section
    {
        return Section::make('Status')
            ->components([
                Select::make('Status')
                    ->options(fn (): array => Lookups::statusesFor(auth()->user()?->groupid))
                    ->default('Pending')
                    ->required(),
                Select::make('PaymentType')
                    ->label('Payment type')
                    ->options(array_combine($values = ['Viator', 'Cash', 'PayPal', 'Eurobank', 'Viva', 'Why Athens', 'GYG'], $values))
                    ->helperText('Left empty, it is filled from the customer when saved.'),
                Select::make('TicketsRequested')
                    ->label('Tickets')
                    ->options(array_combine($values = ['Contacted', 'Paid in Advance', 'Cash', "Purchased by\u{a0}client", 'No tickets needed'], $values)),
                Grid::make(2)->components([
                    // Calculated by the database from the open notes; shown, never saved.
                    TextInput::make('OperationalIssuesFlag')
                        ->label('Operational issue')
                        ->disabled()
                        ->dehydrated(false)
                        ->placeholder('No'),
                    TextInput::make('FinancialIssuesFlag')
                        ->label('Financial issue')
                        ->disabled()
                        ->dehydrated(false)
                        ->placeholder('No'),
                ]),
            ]);
    }

    private static function pricingSection(): Section
    {
        $money = fn (string $name, string $label): TextInput => TextInput::make($name)
            ->label($label)
            ->numeric()
            ->prefix('€');

        $yesNo = ['Ναι' => 'Ναι', 'Όχι' => 'Όχι'];

        return Section::make('Pricing & invoicing')
            ->columns(2)
            ->components([
                $money('PurchasePrice', 'Purchase'),
                $money('SalePrice', 'Sale')
                    ->helperText(fn (?Transfer $record): ?string => $record?->priceLines()->where('ProductID', '<>', '')->exists()
                        ? 'Set from the price lines below.'
                        : null),
                $money('PricePaid', 'Paid'),
                $money('PriceRcvd', 'Received'),
                $money('ExtraIncome', 'Extra income'),
                // As in PHPRunner: not typed on the form (it is edited in the list / comes from charges).
                $money('Expense', 'Expenses')->disabled()->dehydrated(false),
                Select::make('Platform')->options(['Όχι' => 'Όχι', 'Ναι' => 'Ναι'])->live(),
                // Only when the booking came through a platform; a stored price is kept as it is.
                $money('PlatformPrice', 'Platform price')
                    ->disabled(fn (Get $get): bool => $get('Platform') !== 'Ναι')
                    ->dehydrated(),
                ToggleButtons::make('TobeInvoiced')
                    ->label('To be invoiced')
                    ->options(['Όχι' => 'Όχι', 'Τιμολόγιο' => 'Τιμολόγιο', 'Απόδειξη' => 'Απόδειξη'])
                    ->inline()
                    ->helperText('Left empty, it is filled from the customer when saved.')
                    ->columnSpanFull(),
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
            ]);
    }

    private static function voucherSection(): Section
    {
        $calc = fn (?Transfer $record, string $column, string $format): ?string => $record?->{$column}
            ? 'Calculated: '.Carbon::parse($record->{$column})->format($format)
            : null;

        return Section::make('Voucher')
            ->columns(2)
            ->collapsible()
            ->components([
                DatePicker::make('Voucher_StartDt')->label('Start date')
                    ->helperText(fn (?Transfer $record) => $calc($record, 'Voucher_Start_Date_Calc', 'd/m/Y')),
                TimePicker::make('Voucher_StartTime')->label('Start time')->seconds(false)
                    ->helperText(fn (?Transfer $record) => $calc($record, 'Voucher_Start_StartTime_Calc', 'H:i')),
                DatePicker::make('Voucher_EndDt')->label('End date')
                    ->helperText(fn (?Transfer $record) => $calc($record, 'Voucher_End_Date_Calc', 'd/m/Y')),
                TimePicker::make('Voucher_EndTime')->label('End time')->seconds(false)
                    ->helperText(fn (?Transfer $record) => $calc($record, 'Voucher_End_Time_Calc', 'H:i')),
            ]);
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
