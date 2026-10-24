<?php

namespace App\Filament\Resources\OptionValues\Pages;

use App\Filament\Resources\OptionValues\OptionValueResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;

class ManageOptionValues extends ManageRecords
{
    protected static string $resource = OptionValueResource::class;

    /** Wide like the other lists: tables use the whole width of the screen. */
    protected Width|string|null $maxContentWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
