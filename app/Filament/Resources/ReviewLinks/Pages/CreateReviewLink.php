<?php

namespace App\Filament\Resources\ReviewLinks\Pages;

use App\Filament\Resources\ReviewLinks\ReviewLinkResource;
use Filament\Resources\Pages\CreateRecord;

class CreateReviewLink extends CreateRecord
{
    protected static string $resource = ReviewLinkResource::class;

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
