<?php

namespace App\Filament\Resources\Cash\Pages;

use App\Filament\Resources\Cash\CashResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCash extends EditRecord
{
    protected static string $resource = CashResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
