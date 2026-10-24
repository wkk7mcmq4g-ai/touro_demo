<?php

namespace App\Filament\Resources\TransfersToSort\Pages;

use App\Filament\Resources\TransfersToSort\TransferToSortResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListTransferToSorts extends ListRecords
{
    protected static string $resource = TransferToSortResource::class;

    /** Wide like the other lists: tables use the whole width of the screen. */
    protected Width|string|null $maxContentWidth = Width::Full;
}
