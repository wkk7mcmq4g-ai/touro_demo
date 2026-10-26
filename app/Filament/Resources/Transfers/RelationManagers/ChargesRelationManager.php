<?php

namespace App\Filament\Resources\Transfers\RelationManagers;

use App\Filament\Resources\Transfers\Actions\InvoicingActions;
use App\Models\TransferCharge;
use App\Support\Permissions;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * PHPRunner detail table `transfer_charges` (extra charges on a transfer).
 */
class ChargesRelationManager extends RelationManager
{
    protected static string $relationship = 'charges';

    protected static ?string $title = 'Charges';

    private const TABLE = 'transfer_charges';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Permissions::allows(auth()->user(), self::TABLE, 'view');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Textarea::make('TransferCharge')
                    ->label('Charge')
                    ->required()
                    ->rows(2)
                    ->maxLength(500)
                    ->columnSpanFull(),
                DatePicker::make('ChargeDate')->label('Date')->default(now()),
                TextInput::make('ChargeValue')->label('Amount')->numeric()->prefix('€')->required(),
                Select::make('PaymentVia')
                    ->label('Paid via')
                    ->options(TransferCharge::PAYMENT_VIA)
                    ->live()
                    // PHPRunner: Viva and ERB payments are invoiced, cash is not.
                    ->afterStateUpdated(fn (?string $state, Set $set) => $set('TobeInvoiced', TransferCharge::isInvoicedPayment($state))),
                Select::make('PaymentStatus')->label('Payment')->options(['Paid' => 'Paid'])->placeholder('Not paid'),
                DatePicker::make('ReceiptDate')->label('Receipt date'),
                TextInput::make('PaymentCode')->label('Payment code'),
                Toggle::make('TobeInvoiced')->label('To be invoiced')->inline(false),
                Toggle::make('Invoiced')->inline(false),
                TextInput::make('InvoiceNum')->label('Invoice no.'),
                TextInput::make('ElorusID')->label('Elorus ID')->visible(fn (): bool => (bool) config('elorus.ui')),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            // PHPRunner's charges tab: income lines only; expenses have their own tab.
            ->modifyQueryUsing(fn (Builder $query) => $query->where('ChargeValue', '<>', 0))
            ->recordTitleAttribute('TransferCharge')
            ->defaultSort('ID', 'desc')
            ->columns([
                TextColumn::make('ChargeDate')->label('Date')->date('d/m/Y')->sortable(),
                TextColumn::make('TransferCharge')->label('Charge')->wrap(),
                TextColumn::make('ChargeValue')->label('Amount')->money('EUR', locale: 'el')->alignEnd()
                    ->summarize(Sum::make()->money('EUR', locale: 'el')->label('Total')),
                TextColumn::make('PaymentVia')->label('Paid via')->placeholder('—'),
                TextColumn::make('PaymentStatus')
                    ->label('Payment')
                    ->badge()
                    ->getStateUsing(fn (TransferCharge $record): string => $record->PaymentStatus ?: 'Not paid')
                    ->color(fn (string $state): string => $state === 'Paid' ? 'success' : 'warning'),
                IconColumn::make('TobeInvoiced')->label('To invoice')->boolean()
                    ->getStateUsing(fn (TransferCharge $record): bool => (bool) $record->TobeInvoiced),
                IconColumn::make('Invoiced')->boolean()
                    ->getStateUsing(fn (TransferCharge $record): bool => (bool) $record->Invoiced),
                TextColumn::make('InvoiceNum')->label('Invoice no.')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New charge')
                    ->visible(fn (): bool => Permissions::allows(auth()->user(), self::TABLE, 'create'))
                    ->after(fn () => $this->dispatch('transfer-prices-changed')),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (): bool => Permissions::allows(auth()->user(), self::TABLE, 'update'))
                    ->after(fn () => $this->dispatch('transfer-prices-changed')),
                DeleteAction::make()
                    ->visible(fn (): bool => Permissions::allows(auth()->user(), self::TABLE, 'delete'))
                    ->after(fn () => $this->dispatch('transfer-prices-changed')),
            ])
            ->toolbarActions([
                InvoicingActions::chargeReceipts(),
            ]);
    }
}
