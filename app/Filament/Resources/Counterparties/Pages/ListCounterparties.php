<?php

namespace App\Filament\Resources\Counterparties\Pages;

use App\Filament\Resources\Counterparties\CounterpartyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;

class ListCounterparties extends ListRecords
{
    protected static string $resource = CounterpartyResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New partner')];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'suppliers' => Tab::make('Customers / suppliers')->modifyQueryUsing(fn (Builder $query): Builder => $query->where('IsSupplier', 1)),
            'coaches' => Tab::make('Coach partners')->modifyQueryUsing(fn (Builder $query): Builder => $query->where('IsCoach', 1)),
            'guides' => Tab::make('Guides')->modifyQueryUsing(fn (Builder $query): Builder => $query->where('IsAssistant', 1)),
        ];
    }
}
