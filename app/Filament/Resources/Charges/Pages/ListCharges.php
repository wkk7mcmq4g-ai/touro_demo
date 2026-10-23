<?php

namespace App\Filament\Resources\Charges\Pages;

use App\Filament\Resources\Charges\ChargeResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;

class ListCharges extends ListRecords
{
    protected static string $resource = ChargeResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    public function getTabs(): array
    {
        return [
            'income' => Tab::make('Έσοδα')->modifyQueryUsing(fn (Builder $query): Builder => $query->where('ChargeValue', '<>', 0)),
            'expenses' => Tab::make('Έξοδα')->modifyQueryUsing(fn (Builder $query): Builder => $query->where('Expense', '<>', 0)),
        ];
    }

    public function updatedActiveTab(): void
    {
        parent::updatedActiveTab();
        $this->resetTable();
    }
}
