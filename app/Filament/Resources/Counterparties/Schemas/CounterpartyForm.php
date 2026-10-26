<?php

namespace App\Filament\Resources\Counterparties\Schemas;

use App\Models\Counterparty;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

/** PHPRunner's counterparties edit page: the same tabs and fields. */
class CounterpartyForm
{
    public static function configure(Schema $schema): Schema
    {
        $notRegistered = fn (TextInput $input): TextInput => $input
            ->formatStateUsing(fn (?string $state): ?string => $state === 'NotRegistered' ? null : $state)
            ->placeholder('Not registered');

        return $schema->columns(1)->components([
            Tabs::make()->persistTabInQueryString()->tabs([
                Tab::make('Στοιχεία Συνεργάτη')->columns(2)->schema([
                    TextInput::make('CounterShortName')->label('Επωνυμία')->required()->maxLength(500),
                    $notRegistered(TextInput::make('SupplierName')->label('Supplier name')->maxLength(150))
                        ->helperText('Name in the customer lists of transfers.'),
                    $notRegistered(TextInput::make('CoachName')->label('Coach name')->maxLength(150))
                        ->helperText('Name used as "vehicle / coach company" on transfers.'),
                    TextInput::make('AssistantName')->label('Assistant name')->maxLength(150),
                    Textarea::make('Address')->label('Διεύθυνση')->rows(2)->maxLength(500),
                    Textarea::make('Phones')->label('Τηλέφωνα')->rows(2)->maxLength(500),
                    TextInput::make('Email')->email()->maxLength(500),
                    TextInput::make('LesseName')->label('Lessee name')->maxLength(150)
                        ->helperText('Filled in on new transfers of this customer.'),
                    Select::make('InvoiceType')->label('Invoice type')->options(['Τιμολόγιο' => 'Τιμολόγιο', 'Απόδειξη' => 'Απόδειξη', 'Όχι' => 'Όχι']),
                    Select::make('PaymentType')->label('Payment type')->options(array_combine($p = ['Viator', 'Cash', 'PayPal', 'Eurobank', 'Viva', 'Why Athens', 'GYG'], $p)),
                    TextInput::make('AssistantNum')->label('Assistant no.')->maxLength(150),
                    TextInput::make('AssistantAMKA')->label('Assistant AMKA')->maxLength(150),
                    Select::make('UserID')->label('Χρήστης (Touro)')
                        ->options(fn (): array => User::query()->orderBy('fullname')->pluck('fullname', 'ID')->filter()->all())
                        ->searchable()
                        ->helperText('The login of this partner in the partner/driver app.'),
                    TextInput::make('WCR')->label('WCR')->maxLength(50),
                    Section::make('Role')->columns(3)->columnSpanFull()->schema([
                        Toggle::make('IsSupplier')->label('Προμηθευτής (customer)'),
                        Toggle::make('IsCoach')->label('Συνεργάτης Οδηγός'),
                        Toggle::make('IsAssistant')->label('Ξεναγός'),
                        Toggle::make('Active')
                            ->formatStateUsing(fn ($state, ?Counterparty $record): bool => $record === null || Counterparty::isActiveValue($state))
                            ->dehydrateStateUsing(fn (bool $state): string => $state ? '1' : '0'),
                        Toggle::make('GetReview')->label('Get review (review link on vouchers)'),
                        Toggle::make('LeadName')->label('Lessee = lead passenger')
                            ->helperText('New transfers take the lead passenger as lessee.'),
                    ]),
                    Textarea::make('Notes')->label('Σημειώσεις')->rows(3)->maxLength(1500)->columnSpanFull(),
                ]),
                Tab::make('Οικονομικά Στοιχεία')->columns(2)->schema([
                    TextInput::make('BankName')->label('Τράπεζα')->maxLength(150),
                    TextInput::make('IBAN')->maxLength(150),
                    TextInput::make('BankAccount')->label('Λογαριασμός')->maxLength(150),
                    TextInput::make('VAT')->label('ΑΦΜ')->maxLength(150),
                    TextInput::make('DOY')->label('ΔΟΥ')->maxLength(150),
                ]),
                Tab::make('Elorus')->visible(fn (): bool => (bool) config('elorus.ui'))->columns(2)->schema([
                    $notRegistered(TextInput::make('ElorusID')->label('Elorus ID')->maxLength(150)),
                    $notRegistered(TextInput::make('DocumentType')->label('Document type')->maxLength(150)),
                    $notRegistered(TextInput::make('MydataDocumentType')->label('myDATA document type')->maxLength(150)),
                    $notRegistered(TextInput::make('MydataClassCategory')->label('myDATA class category')->maxLength(150)),
                    $notRegistered(TextInput::make('MydataClassType')->label('myDATA class type')->maxLength(150)),
                ]),
            ]),
        ]);
    }
}
