<?php

namespace App\Filament\Portal\Resources\MyJobs\Pages;

use App\Filament\Portal\Resources\MyJobs\MyJobResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

class ListMyJobs extends ListRecords
{
    protected static string $resource = MyJobResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    /** PHPRunner's Σήμερα / Αύριο buttons; the view itself covers the next five days. */
    public function getTabs(): array
    {
        $count = fn (Carbon $day): ?int => MyJobResource::getEloquentQuery()->whereDate('TransferDate', $day)->count() ?: null;

        return [
            'today' => Tab::make('Σήμερα')->badge($count(today()))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereDate('TransferDate', today())),
            'tomorrow' => Tab::make('Αύριο')->badge($count(today()->addDay()))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereDate('TransferDate', today()->addDay())),
            'all' => Tab::make('Όλα'),
        ];
    }

    /** The day in one line: jobs, passengers, and when the next one starts. */
    public function getSubheading(): string|Htmlable|null
    {
        $tab = $this->activeTab ?? 'today';
        $query = MyJobResource::getEloquentQuery();
        $day = match ($tab) {
            'today' => today(),
            'tomorrow' => today()->addDay(),
            default => null,
        };
        if ($day) {
            $query->whereDate('TransferDate', $day);
        } else {
            $query->whereDate('TransferDate', '>=', today());
        }

        $jobs = $query->orderBy('TransferDate')->orderBy('TransferTime_sort')->get(['TransferID', 'TransferDate', 'TransferTime', 'Pax']);
        $date = ($day ?? today())->locale('el')->translatedFormat('l j F');

        if ($jobs->isEmpty()) {
            return new HtmlString('<span class="myat-day-date">'.e($date).'</span>');
        }

        $pax = $jobs->sum(fn ($job): int => (int) preg_replace('/\D/', '', (string) $job->Pax));
        $parts = [
            '<span class="myat-day-pill"><b>'.$jobs->count().'</b> '.($jobs->count() === 1 ? 'δρομολόγιο' : 'δρομολόγια').'</span>',
            '<span class="myat-day-pill"><b>'.$pax.'</b> άτομα</span>',
        ];

        if ($tab === 'today') {
            $now = now()->format('H:i');
            $next = $jobs->first(fn ($job): bool => substr((string) $job->TransferTime, 0, 5) >= $now);
            if ($next) {
                $minutes = (int) now()->diffInMinutes(Carbon::parse(today()->toDateString().' '.substr((string) $next->TransferTime, 0, 5)));
                $in = $minutes >= 60 ? intdiv($minutes, 60).'ώ '.($minutes % 60).'΄' : $minutes.'΄';
                $parts[] = '<span class="myat-day-pill myat-day-next">Επόμενο <b>'.e(substr((string) $next->TransferTime, 0, 5)).'</b> · σε '.$in.'</span>';
            } else {
                $parts[] = '<span class="myat-day-pill myat-day-done">Τέλος για σήμερα</span>';
            }
        } else {
            $parts[] = '<span class="myat-day-pill">Πρώτο <b>'.e(substr((string) $jobs->first()->TransferTime, 0, 5)).'</b></span>';
        }

        return new HtmlString('<span class="myat-day-date">'.e($tab === 'all' ? 'Από σήμερα και μετά' : $date).'</span><span class="myat-day">'.implode('', $parts).'</span>');
    }
}
