<?php

namespace App\Filament\Portal\Resources\BookingRequests\Pages;

use App\Filament\Portal\Resources\BookingRequests\BookingRequestResource;
use App\Support\Portal;
use Filament\Resources\Pages\CreateRecord;

class CreateBookingRequest extends CreateRecord
{
    protected static string $resource = BookingRequestResource::class;

    /** PHPRunner BeforeAdd: the request is the signed-in customer's. */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        abort_if(Portal::customerId() === null, 403, 'This login is not linked to a customer.');

        return $data + [
            'cID_Customer' => Portal::customerId(),
            'ImportDate' => now()->toDateString(),
            'CreatedDate' => now(),
            'Createdby' => auth()->user()->username,
            'Loaded' => 0,
        ];
    }

    /** Products, notes and files are added on the next page, like PHPRunner. */
    protected function getRedirectUrl(): string
    {
        return BookingRequestResource::canEdit($this->getRecord())
            ? static::getResource()::getUrl('edit', ['record' => $this->getRecord()])
            : static::getResource()::getUrl('index');
    }
}
