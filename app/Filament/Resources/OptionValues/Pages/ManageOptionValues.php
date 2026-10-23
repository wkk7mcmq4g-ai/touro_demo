<?php

namespace App\Filament\Resources\OptionValues\Pages;

use App\Filament\Resources\OptionValues\OptionValueResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageOptionValues extends ManageRecords
{
    protected static string $resource = OptionValueResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
