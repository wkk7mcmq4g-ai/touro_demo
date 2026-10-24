<?php

namespace App\Filament\Portal\Resources\BookingRequests\Pages;

use App\Filament\Portal\Resources\BookingRequests\BookingRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;

class EditBookingRequest extends EditRecord
{
    protected static string $resource = BookingRequestResource::class;

    /** The products, notes and files are inside the form's tabs (LoadFormTabs), as on PHPRunner's page. */
    public function getRelationManagersContentComponent(): Component
    {
        return Group::make()->hidden();
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
