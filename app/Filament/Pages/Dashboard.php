<?php

namespace App\Filament\Pages;

use App\Filament\Resources\DailyTransfers\DailyTransfersResource;
use App\Filament\Resources\Dispatch\DispatchResource;
use App\Filament\Resources\Transfers\TransferResource;
use App\Filament\Widgets\Dashboard\FinanceSnapshot;
use App\Filament\Widgets\Dashboard\KpiTiles;
use App\Filament\Widgets\Dashboard\NeedsAttention;
use App\Filament\Widgets\Dashboard\TodayBoard;
use App\Filament\Widgets\Dashboard\TransfersChart;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;

/** The day at a glance: today's jobs, what needs attention, the next days and the month's money. */
class Dashboard extends BaseDashboard
{
    public function getHeading(): string|Htmlable
    {
        $hour = Carbon::now()->hour;
        $greeting = match (true) {
            $hour < 12 => 'Καλημέρα',
            $hour < 18 => 'Καλησπέρα',
            default => 'Καλό βράδυ',
        };
        $name = trim(explode(' ', (string) filament()->getUserName(auth()->user()))[0] ?? '');

        return $name !== '' ? "{$greeting}, {$name}" : $greeting;
    }

    public function getSubheading(): string|Htmlable|null
    {
        return Carbon::today()->locale('el')->translatedFormat('l j F Y');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('dispatch')->label('Dispatch')->icon(Heroicon::OutlinedMap)->color('gray')
                ->url(fn (): string => DispatchResource::getUrl())->visible(fn (): bool => DispatchResource::canAccess()),
            Action::make('send')->label('Αποστολή Transfers')->icon(Heroicon::OutlinedPaperAirplane)->color('gray')
                ->url(fn (): string => DailyTransfersResource::getUrl())->visible(fn (): bool => DailyTransfersResource::canAccess()),
            Action::make('new')->label('Νέο transfer')->icon(Heroicon::OutlinedPlus)
                ->url(fn (): string => TransferResource::getUrl('create'))->visible(fn (): bool => TransferResource::canCreate()),
        ];
    }

    public function getWidgets(): array
    {
        return [KpiTiles::class, TodayBoard::class, NeedsAttention::class, TransfersChart::class, FinanceSnapshot::class];
    }

    public function getColumns(): int|array
    {
        return ['default' => 1, 'lg' => 12];
    }
}
