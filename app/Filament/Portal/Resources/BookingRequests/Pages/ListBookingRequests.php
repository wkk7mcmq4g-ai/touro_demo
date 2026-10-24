<?php

namespace App\Filament\Portal\Resources\BookingRequests\Pages;

use App\Filament\Portal\Resources\BookingRequests\BookingRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListBookingRequests extends ListRecords
{
    protected static string $resource = BookingRequestResource::class;

    /** Wide like the other lists: tables use the whole width of the screen. */
    protected Width|string|null $maxContentWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New booking')];
    }
}
