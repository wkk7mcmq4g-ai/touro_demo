<?php

namespace App\Filament\Resources\Transfers\RelationManagers;

use App\Models\PriceListTransfer;
use App\Models\Transfer;
use App\Support\Permissions;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * PHPRunner detail table `price_list_transfers`: products from the customer's price
 * list. When a transfer has price lines, a trigger sets its Sale price to their total.
 */
class PriceLinesRelationManager extends RelationManager
{
    protected static string $relationship = 'priceLines';

    protected static ?string $title = 'Price lines';

    private const TABLE = 'price_list_transfers';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Permissions::allows(auth()->user(), self::TABLE, 'view');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('Category')
                    ->options(fn (): array => $this->priceList()->distinct()->orderBy('Category')->pluck('Category', 'Category')->all())
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('ProductID', null)),
                Select::make('ProductCarType')
                    ->label('Car type')
                    ->options(fn (Get $get): array => $this->priceList()
                        ->when($get('Category'), fn ($query, $category) => $query->where('Category', $category))
                        ->distinct()->orderBy('CarType')->pluck('CarType', 'CarType')->all())
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('ProductID', null)),
                Select::make('ProductID')
                    ->label('Product')
                    ->options(fn (Get $get): array => $this->priceList()
                        ->when($get('Category'), fn ($query, $category) => $query->where('Category', $category))
                        ->when($get('ProductCarType'), fn ($query, $carType) => $query->where('CarType', $carType))
                        ->orderBy('Product2')
                        ->pluck('Product2', 'ID')
                        ->all())
                    ->searchable()
                    ->required()
                    ->live()
                    // Fill in the product's price from the customer's price list (editable).
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        if ($state) {
                            $set('PriceValue', DB::table('price_list')->where('ID', $state)->value('PriceValue'));
                        }
                    })
                    ->columnSpanFull(),
                TextInput::make('PriceValue')
                    ->label('Price')
                    ->numeric()
                    ->prefix('€')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('ProductID', '<>', '')->with('product'))
            ->recordTitleAttribute('ProductID')
            ->columns([
                TextColumn::make('product.Product2')->label('Product')->placeholder(fn (PriceListTransfer $record): string => (string) $record->ProductID),
                TextColumn::make('Category'),
                TextColumn::make('ProductCarType')->label('Car type'),
                TextColumn::make('PriceValue')
                    ->label('Price')
                    ->money('EUR', locale: 'el')
                    ->alignEnd()
                    ->summarize(Sum::make()->money('EUR', locale: 'el')->label('Sale price')),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add product')
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

    /** The customer's price list (PHPRunner: `cid_Customer = ':master.cID_Customer'`). */
    private function priceList(): \Illuminate\Database\Query\Builder
    {
        /** @var Transfer $transfer */
        $transfer = $this->getOwnerRecord();

        return DB::table('price_list')->where('cid_Customer', $transfer->cID_Customer);
    }
}
