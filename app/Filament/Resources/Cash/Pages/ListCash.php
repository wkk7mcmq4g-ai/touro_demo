<?php

namespace App\Filament\Resources\Cash\Pages;

use App\Filament\Resources\Cash\CashResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListCash extends ListRecords
{
    protected static string $resource = CashResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New cash count')];
    }
}
