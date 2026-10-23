<?php

namespace App\Filament\Portal\Resources\BookingRequests\Pages;

use App\Filament\Portal\Resources\BookingRequests\BookingRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBookingRequest extends EditRecord
{
    protected static string $resource = BookingRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
