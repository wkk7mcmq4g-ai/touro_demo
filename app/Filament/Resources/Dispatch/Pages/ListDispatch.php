<?php

namespace App\Filament\Resources\Dispatch\Pages;

use App\Filament\Resources\DailyTransfers\DailyTransfersResource;
use App\Filament\Resources\Dispatch\DispatchResource;
use App\Filament\Resources\Dispatch\Widgets\DispatchDayTotals;
use Filament\Actions\Action;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ListDispatch extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = DispatchResource::class;

    /** Totals of each day the list shows, above it (also in full screen). */
    protected function getHeaderWidgets(): array
    {
        return [DispatchDayTotals::class];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

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

    /** PHPRunner "Αποστολή Transfers" (New_Button39). */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTransfers')->label('Αποστολή Transfers')->icon(Heroicon::OutlinedPaperAirplane)
                ->visible(fn (): bool => DailyTransfersResource::canViewAny())
                ->url(DailyTransfersResource::getUrl()),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'today';
    }
}
