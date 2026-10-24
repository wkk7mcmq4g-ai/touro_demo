<?php

namespace App\Filament\Portal\Resources\MyTransfers\Pages;

use App\Filament\Portal\Resources\MyTransfers\MyTransferResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;

class ListMyTransfers extends ListRecords
{
    protected static string $resource = MyTransferResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    public function getTabs(): array
    {
        return [
            'upcoming' => Tab::make('Upcoming')->modifyQueryUsing(fn (Builder $query): Builder => $query->where('TransferDate', '>=', today())),
            'past' => Tab::make('Past')->modifyQueryUsing(fn (Builder $query): Builder => $query->where('TransferDate', '<', today())->reorder('TransferDate', 'desc')),
            'all' => Tab::make('All'),
        ];
    }
}
