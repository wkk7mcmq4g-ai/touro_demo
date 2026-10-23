<?php

namespace App\Filament\Resources\TransfersToSort\RelationManagers;

use App\Filament\Resources\Transfers\RelationManagers\PriceLinesRelationManager;

/** PHPRunner detail `price_list_load`: the products of a booking to sort. */
class LoadPriceLinesRelationManager extends PriceLinesRelationManager
{
    protected static ?string $title = 'Products';

    protected const TABLE = 'price_list_load';
}
