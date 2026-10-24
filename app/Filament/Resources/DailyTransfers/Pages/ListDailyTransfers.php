<?php

namespace App\Filament\Resources\DailyTransfers\Pages;

use App\Filament\Resources\DailyTransfers\DailyTransfersResource;
use App\Filament\Resources\Dispatch\DispatchResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListDailyTransfers extends ListRecords
{
    protected static string $resource = DailyTransfersResource::class;

    /** Wide like the other lists: tables use the whole width of the screen. */
    protected Width|string|null $maxContentWidth = Width::Full;

    /** One tab per day the dispatch view covers (today and the next four days). */
    public function getTabs(): array
    {
        return collect(range(0, 4))->mapWithKeys(function (int $i): array {
            $day = today()->addDays($i);
            $label = match ($i) {
                0 => 'Σήμερα', 1 => 'Αύριο', default => $day->locale('el')->translatedFormat('D d/m')
            };

            return ["d{$i}" => Tab::make($label)->modifyQueryUsing(fn (Builder $query): Builder => $query->whereDate('TransferDate', $day))];
        })->all();
    }

    /** PHPRunner "Επιστροφή". */
    protected function getHeaderActions(): array
    {
        return [Action::make('back')->label('Dispatch')->icon(Heroicon::OutlinedArrowUturnLeft)->color('gray')->url(DispatchResource::getUrl())];
    }
}
