<?php

namespace App\Filament\Resources\ReviewLinks\Pages;

use App\Filament\Resources\ReviewLinks\ReviewLinkResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListReviewLinks extends ListRecords
{
    protected static string $resource = ReviewLinkResource::class;

    /** Wide like the other lists: tables use the whole width of the screen. */
    protected Width|string|null $maxContentWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
