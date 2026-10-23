<?php

namespace App\Filament\Portal\Resources\MyJobs\Pages;

use App\Filament\Portal\Resources\MyJobs\MyJobResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;

class ListMyJobs extends ListRecords
{
    protected static string $resource = MyJobResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    /** PHPRunner's Σήμερα / Αύριο buttons; the view itself covers the next five days. */
    public function getTabs(): array
    {
        return [
            'today' => Tab::make('Σήμερα')->modifyQueryUsing(fn (Builder $query): Builder => $query->whereDate('TransferDate', today())),
            'tomorrow' => Tab::make('Αύριο')->modifyQueryUsing(fn (Builder $query): Builder => $query->whereDate('TransferDate', today()->addDay())),
            'all' => Tab::make('Όλα'),
        ];
    }
}
