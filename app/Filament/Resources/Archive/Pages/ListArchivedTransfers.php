<?php

namespace App\Filament\Resources\Archive\Pages;

use App\Filament\Resources\Archive\ArchiveResource;
use Filament\Resources\Pages\ListRecords;

class ListArchivedTransfers extends ListRecords
{
    protected static string $resource = ArchiveResource::class;
}
