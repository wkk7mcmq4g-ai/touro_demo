<?php

namespace App\Filament\Resources\Archive\Pages;

use App\Filament\Resources\Archive\ArchiveResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListArchivedTransfers extends ListRecords
{
    protected static string $resource = ArchiveResource::class;

    /** Wide like the other lists: tables use the whole width of the screen. */
    protected Width|string|null $maxContentWidth = Width::Full;
}
