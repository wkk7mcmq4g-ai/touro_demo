<?php

namespace App\Filament\Resources\Payments\RelationManagers;

use App\Filament\Resources\Transfers\TransferResource;
use App\Models\PaymentLink;
use App\Support\Permissions;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** PHPRunner detail `paymentlinks`: which transfers a payment paid, and how much. */
class LinksRelationManager extends RelationManager
{
    protected static string $relationship = 'links';

    protected static ?string $title = 'Allocated to transfers';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Permissions::allows(auth()->user(), 'paymentlinks', 'view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('ID')
            ->columns([
                TextColumn::make('TransferID')->label('Transfer')->url(fn (PaymentLink $r): string => TransferResource::getUrl('edit', ['record' => $r->TransferID])),
                TextColumn::make('PaymentAmount')->label('Amount')->money('EUR', locale: 'el')->alignEnd()->summarize(Sum::make()->money('EUR', locale: 'el')->label('')),
            ]);
        // Read-only: removing a link would not undo what the allocation wrote on the transfer.
    }
}
