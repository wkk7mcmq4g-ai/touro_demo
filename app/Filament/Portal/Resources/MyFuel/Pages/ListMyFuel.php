<?php

namespace App\Filament\Portal\Resources\MyFuel\Pages;

use App\Filament\Portal\Resources\MyFuel\MyFuelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMyFuel extends ListRecords
{
    protected static string $resource = MyFuelResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New fuel receipt')];
    }
}
