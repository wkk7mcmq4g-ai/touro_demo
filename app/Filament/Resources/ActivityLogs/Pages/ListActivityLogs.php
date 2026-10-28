<?php

namespace App\Filament\Resources\ActivityLogs\Pages;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListActivityLogs extends ListRecords
{
    protected static string $resource = ActivityLogResource::class;

    /** Wide like the other lists: tables use the whole width of the screen. */
    protected Width|string|null $maxContentWidth = Width::Full;
}
