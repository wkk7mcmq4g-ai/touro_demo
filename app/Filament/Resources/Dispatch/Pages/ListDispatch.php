<?php

namespace App\Filament\Resources\Dispatch\Pages;

use App\Filament\Resources\Dispatch\DispatchResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ListDispatch extends ListRecords
{
    protected static string $resource = DispatchResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    /** PHPRunner's Σήμερα / Αύριο buttons; the view itself covers the next five days. */
    public function getTabs(): array
    {
        return [
            'today' => Tab::make('Σήμερα')->modifyQueryUsing(fn (Builder $query): Builder => $query->whereDate('TransferDate', Carbon::today())),
            'tomorrow' => Tab::make('Αύριο')->modifyQueryUsing(fn (Builder $query): Builder => $query->whereDate('TransferDate', Carbon::tomorrow())),
            'all' => Tab::make('Next 5 days'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'today';
    }
}
