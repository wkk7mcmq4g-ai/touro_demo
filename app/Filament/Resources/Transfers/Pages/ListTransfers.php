<?php

namespace App\Filament\Resources\Transfers\Pages;

use App\Filament\Resources\Transfers\TransferResource;
use App\Models\Transfer;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ListTransfers extends ListRecords
{
    protected static string $resource = TransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New transfer'),
        ];
    }

    /**
     * The quick date buttons of the PHPRunner list (Today, Tomorrow, Next five days, ...).
     */
    public function getTabs(): array
    {
        $today = Carbon::today();

        return [
            'today' => $this->dateTab('Today', $today, $today),
            'tomorrow' => $this->dateTab('Tomorrow', $today->copy()->addDay(), $today->copy()->addDay()),
            'next5' => $this->dateTab('Next 5 days', $today, $today->copy()->addDays(4)),
            'pending' => Tab::make('Pending')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('Status', 'Pending')
                    ->whereDate('TransferDate', '>=', $today))
                ->badge(fn (): int => Transfer::query()->where('Status', 'Pending')->whereDate('TransferDate', '>=', $today)->count())
                ->badgeColor('warning'),
            'all' => Tab::make('All'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'today';
    }

    private function dateTab(string $label, Carbon $from, Carbon $until): Tab
    {
        $range = fn (Builder $query): Builder => $query
            ->whereDate('TransferDate', '>=', $from)
            ->whereDate('TransferDate', '<=', $until);

        return Tab::make($label)
            ->modifyQueryUsing($range)
            ->badge(fn (): int => $range(Transfer::query())->count());
    }
}
