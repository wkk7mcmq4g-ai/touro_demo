<?php

namespace App\Filament\Resources\Expenses\Pages;

use App\Filament\Resources\Expenses\ExpenseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;

class ListExpenses extends ListRecords
{
    protected static string $resource = ExpenseResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Όλες'),
            ...collect(ExpenseResource::COMPANIES)->mapWithKeys(fn (string $company): array => [
                $company => Tab::make($company)->modifyQueryUsing(fn (Builder $query): Builder => $query->where('Company', $company)),
            ])->all(),
        ];
    }
}
