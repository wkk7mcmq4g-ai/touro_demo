<?php

namespace App\Filament\Resources\Transfers\RelationManagers;

use App\Models\TransferCharge;
use App\Support\Lookups;
use App\Support\Permissions;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * PHPRunner detail `transfer_charges1`: the expense lines of a transfer (rows of
 * transfer_charges with an Expense). The triggers add them up on the transfer.
 */
class ExpensesRelationManager extends RelationManager
{
    protected static string $relationship = 'charges';

    protected static ?string $title = 'Expenses';

    private const TABLE = 'transfer_charges1';

    private const YES_NO = ['Ναι' => 'Ναι', 'Όχι' => 'Όχι'];

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Permissions::allows(auth()->user(), self::TABLE, 'view');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('Expense')->label('Amount')->numeric()->prefix('€')->required()->notIn(['0', 0]),
                Select::make('ExpenseChargeCategory')->label('Category')
                    ->options(fn (): array => Lookups::optionIds('ExpenseChargeCategory')),
                Textarea::make('ExpenseReason')->label('Reason')->rows(2)->maxLength(50)->columnSpanFull(),
                Select::make('ExpenseReceived')->label('Received')->options(self::YES_NO),
                Select::make('ExpensePaid')->label('Paid')->options(self::YES_NO),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('Expense', '<>', 0))
            ->recordTitleAttribute('ExpenseReason')
            ->defaultSort('ID', 'desc')
            ->columns([
                TextColumn::make('ExpenseChargeCategory')->label('Category')
                    ->formatStateUsing(fn ($state): ?string => Lookups::optionIds('ExpenseChargeCategory')[$state] ?? null)
                    ->placeholder('—'),
                TextColumn::make('ExpenseReason')->label('Reason')->wrap()->placeholder('—'),
                TextColumn::make('Expense')->label('Amount')->money('EUR', locale: 'el')->alignEnd()
                    ->summarize(Sum::make()->money('EUR', locale: 'el')->label('Total')),
                // PHPRunner shows an empty value as Όχι.
                TextColumn::make('ExpenseReceived')->label('Received')->badge()
                    ->getStateUsing(fn (TransferCharge $record): string => $record->ExpenseReceived ?: 'Όχι')
                    ->color(fn (string $state): string => $state === 'Ναι' ? 'success' : 'gray'),
                TextColumn::make('ExpensePaid')->label('Paid')->badge()
                    ->getStateUsing(fn (TransferCharge $record): string => $record->ExpensePaid ?: 'Όχι')
                    ->color(fn (string $state): string => $state === 'Ναι' ? 'success' : 'gray'),
                TextColumn::make('CreatedBy')->label('By')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New expense')
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
            ]);
    }
}
