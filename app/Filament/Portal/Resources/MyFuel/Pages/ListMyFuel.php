<?php

namespace App\Filament\Portal\Resources\MyFuel\Pages;

use App\Filament\Portal\Resources\MyFuel\MyFuelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;

class ListMyFuel extends ListRecords
{
    protected static string $resource = MyFuelResource::class;

    /** This month at a glance: how much, how many, how many still to be checked. */
    public function getSubheading(): string|Htmlable|null
    {
        $month = MyFuelResource::getEloquentQuery()->whereBetween('FuelDate', [today()->startOfMonth(), today()->endOfMonth()])
            ->toBase()->selectRaw('COUNT(*) AS n, SUM(FuelAmount) AS total')->first();
        $pending = MyFuelResource::getEloquentQuery()->where(fn ($q) => $q->whereNull('ApprovedStatus')->orWhere('ApprovedStatus', ''))->count();

        return new HtmlString('<span class="myat-day-date">'.e(ucfirst(today()->locale('el')->translatedFormat('F Y'))).'</span>'
            .'<span class="myat-day"><b>'.e(Number::currency((float) ($month->total ?? 0), 'EUR', 'el')).'</b> · <b>'.(int) ($month->n ?? 0).'</b> αποδείξεις'
            .($pending ? ' · <b>'.$pending.'</b> σε έλεγχο' : '').'</span>');
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Νέα απόδειξη')->icon(Heroicon::OutlinedPlus)];
    }
}
