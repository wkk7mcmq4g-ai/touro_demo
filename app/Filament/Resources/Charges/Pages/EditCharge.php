<?php

namespace App\Filament\Resources\Charges\Pages;

use App\Filament\Resources\Charges\ChargeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCharge extends EditRecord
{
    protected static string $resource = ChargeResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
