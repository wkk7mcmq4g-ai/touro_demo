<?php

namespace App\Filament\Resources\DriverLeaves\Pages;

use App\Filament\Resources\DriverLeaves\DriverLeaveResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListDriverLeaves extends ListRecords
{
    protected static string $resource = DriverLeaveResource::class;

    /** Wide like the other lists: tables use the whole width of the screen. */
    protected Width|string|null $maxContentWidth = Width::Full;
}
