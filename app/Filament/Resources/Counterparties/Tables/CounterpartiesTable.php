<?php

namespace App\Filament\Resources\Counterparties\Tables;

use App\Filament\Resources\Counterparties\Actions\AddToElorus;
use App\Models\Counterparty;
use App\Support\FilterValues;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** PHPRunner's counterparties list: the same columns; the less used ones start hidden. */
class CounterpartiesTable
{
    public static function configure(Table $table): Table
    {
        $text = fn (string $name, string $label, bool $hidden = false): TextColumn => TextColumn::make($name)
            ->label($label)->searchable()->sortable()->toggleable(isToggledHiddenByDefault: $hidden)
            ->formatStateUsing(fn (?string $state): ?string => $state === 'NotRegistered' ? null : $state)
            ->placeholder('—');
        $flag = fn (string $name, string $label): IconColumn => IconColumn::make($name)->label($label)->boolean()
            ->getStateUsing(fn (Counterparty $record): bool => (bool) $record->{$name})->toggleable();

        return $table
            ->defaultSort('CounterShortName')
            ->persistFiltersInSession()
            ->persistColumnsInSession()
            ->columns([
                $text('CounterShortName', 'Επωνυμία')->weight('bold')->wrap(),
                $text('SupplierName', 'Supplier name'),
                $text('CoachName', 'Coach name'),
                $flag('IsSupplier', 'Προμηθευτής'),
                $flag('IsCoach', 'Συν. Οδηγός'),
                $flag('IsAssistant', 'Ξεναγός'),
                IconColumn::make('Active')->boolean()->toggleable()
                    ->getStateUsing(fn (Counterparty $record): bool => Counterparty::isActiveValue($record->Active)),
                $text('Phones', 'Τηλέφωνα')->wrap(),
                $text('Email', 'Email'),
                $text('VAT', 'ΑΦΜ'),
                $text('DOY', 'ΔΟΥ', true),
                $text('Address', 'Διεύθυνση', true)->wrap(),
                $text('LesseName', 'Lessee name', true),
                $text('InvoiceType', 'Invoice type', true),
                $text('PaymentType', 'Payment type', true),
                $text('ElorusID', 'Elorus ID')->visible(fn (): bool => (bool) config('elorus.ui')),
                $text('DocumentType', 'Document type', true)->visible(fn (): bool => (bool) config('elorus.ui')),
                $text('MydataDocumentType', 'myDATA doc. type', true)->visible(fn (): bool => (bool) config('elorus.ui')),
                $text('MydataClassCategory', 'myDATA class category', true)->visible(fn (): bool => (bool) config('elorus.ui')),
                $text('MydataClassType', 'myDATA class type', true)->visible(fn (): bool => (bool) config('elorus.ui')),
                $text('BankName', 'Τράπεζα', true),
                $text('IBAN', 'IBAN', true),
                $text('BankAccount', 'Λογαριασμός', true),
                $text('AssistantName', 'Assistant name', true),
                $text('AssistantNum', 'Assistant no.', true),
                $text('AssistantAMKA', 'Assistant AMKA', true),
                $text('WCR', 'WCR', true),
            ])
            ->filters([
                TernaryFilter::make('active')->label('Active')
                    ->queries(
                        true: fn (Builder $q) => $q->where(fn (Builder $q) => $q->whereNull('Active')->orWhereNotIn('Active', ['', '0', 'No', 'no', 'off'])),
                        false: fn (Builder $q) => $q->whereIn('Active', ['', '0', 'No', 'no', 'off']),
                    ),
                SelectFilter::make('elorus')->label('In Elorus')->visible(fn (): bool => (bool) config('elorus.ui'))
                    ->options(['yes' => 'Yes', 'no' => 'No'])
                    ->query(fn (Builder $q, array $data) => FilterValues::any($q, $data, [
                        'yes' => fn (Builder $q) => $q->whereNotIn('ElorusID', ['', 'NotRegistered']),
                        'no' => fn (Builder $q) => $q->whereIn('ElorusID', ['', 'NotRegistered']),
                    ])),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([AddToElorus::make()]);
    }
}
