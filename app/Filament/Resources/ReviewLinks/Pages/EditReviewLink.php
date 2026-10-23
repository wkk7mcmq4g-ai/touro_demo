<?php

namespace App\Filament\Resources\ReviewLinks\Pages;

use App\Filament\Resources\ReviewLinks\ReviewLinkResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditReviewLink extends EditRecord
{
    protected static string $resource = ReviewLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
