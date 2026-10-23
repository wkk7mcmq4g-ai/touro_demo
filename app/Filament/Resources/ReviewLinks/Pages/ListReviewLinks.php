<?php

namespace App\Filament\Resources\ReviewLinks\Pages;

use App\Filament\Resources\ReviewLinks\ReviewLinkResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReviewLinks extends ListRecords
{
    protected static string $resource = ReviewLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
