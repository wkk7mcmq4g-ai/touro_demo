<?php

namespace App\Filament\Portal\Resources\MyJobs\Pages;

use App\Filament\Portal\Resources\MyJobs\MyJobResource;
use App\Models\VTransfer;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class ListMyJobs extends ListRecords
{
    protected static string $resource = MyJobResource::class;

    protected Width|string|null $maxContentWidth = Width::SevenExtraLarge;

    /** The day's jobs of the signed-in driver: [count, their ID in the daily sends (aid)]. */
    private static function myDay(Carbon $day): array
    {
        $row = VTransfer::query()->where('username', (string) auth()->user()?->username)->whereDate('TransferDate', $day)
            ->toBase()->selectRaw('COUNT(*) AS jobs, MAX(aid) AS aid')->first();

        return [(int) ($row->jobs ?? 0), $row->aid ?? null];
    }

    private static function alreadyRead(Carbon $day, mixed $aid): bool
    {
        return $aid !== null && DB::table('emails_sent')->whereDate('TransferDate', $day)->where('aID', (int) $aid)->exists();
    }

    /**
     * PHPRunner "Διαβάστηκαν" (ReadReceipt): the driver confirms they have read a day's schedule
     * (tomorrow by default) by typing how many jobs it has; the office sees it as Read in
     * Αποστολή Transfers.
     */
    public function readReceiptAction(): Action
    {
        $tomorrow = today()->addDay();

        return Action::make('readReceipt')
            ->label(fn (): string => self::alreadyRead($tomorrow, self::myDay($tomorrow)[1]) ? 'Διαβάστηκαν (αύριο)' : 'Διαβάστηκαν')
            ->icon(fn (): Heroicon => self::alreadyRead($tomorrow, self::myDay($tomorrow)[1]) ? Heroicon::OutlinedCheckCircle : Heroicon::OutlinedEye)
            ->color(fn (): string => self::alreadyRead($tomorrow, self::myDay($tomorrow)[1]) ? 'success' : 'primary')
            ->modalHeading('Αποστολή ανάγνωσης')
            ->modalDescription('Επιβεβαιώστε ότι διαβάσατε το πρόγραμμα: γράψτε πόσα δρομολόγια έχετε εκείνη την ημέρα.')
            ->modalSubmitActionLabel('Αποστολή')
            ->schema([
                DatePicker::make('day')->label('Ημερομηνία')->native(false)->displayFormat('d/m/Y')->default($tomorrow)->required(),
                TextInput::make('transfers')->label('Δρομολόγια')->integer()->minValue(0)->required()->default(0),
            ])
            ->action(function (array $data, Action $action): void {
                $day = Carbon::parse($data['day'])->startOfDay();
                [$jobs, $aid] = self::myDay($day);

                if ($jobs === 0 || $aid === null || (int) $data['transfers'] !== $jobs) {
                    Notification::make()->danger()->title('Λάθος αριθμός δρομολογίων')->send();
                    $action->halt();
                }

                if (! self::alreadyRead($day, $aid)) {
                    DB::table('emails_sent')->insert(['TransferDate' => $day->toDateString(), 'aID' => (int) $aid]);
                }
                Notification::make()->success()->title('Ευχαριστούμε, καταχωρήθηκε')->send();
            });
    }

    /** The "Αύριο" card under the jobs: tomorrow's count, first pickup, and the Διαβάστηκαν button. */
    public function getFooter(): ?View
    {
        $tomorrow = today()->addDay();
        $jobs = MyJobResource::getEloquentQuery()->whereDate('TransferDate', $tomorrow)->orderBy('TransferTime_sort')->get(['TransferTime', 'aid']);

        return view('filament.portal.tomorrow-card', [
            'date' => $tomorrow->locale('el')->translatedFormat('D d/m'),
            'count' => $jobs->count(),
            'first' => $jobs->isNotEmpty() ? substr((string) $jobs->first()->TransferTime, 0, 5) : null,
            'read' => self::alreadyRead($tomorrow, $jobs->max('aid')),
        ]);
    }

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

    /** The day in one line: date, vehicle, jobs and passengers (the next job's countdown is on its card). */
    public function getSubheading(): string|Htmlable|null
    {
        $tab = $this->activeTab ?? 'today';
        $day = match ($tab) {
            'today' => today(),
            'tomorrow' => today()->addDay(),
            default => null,
        };
        $query = MyJobResource::getEloquentQuery()
            ->when($day, fn (Builder $q) => $q->whereDate('TransferDate', $day), fn (Builder $q) => $q->whereDate('TransferDate', '>=', today()));
        $jobs = $query->orderBy('TransferDate')->orderBy('TransferTime_sort')->get(['TransferTime', 'Pax', 'VehicleName']);

        $date = $tab === 'all' ? 'Από σήμερα και μετά' : ($day ?? today())->locale('el')->translatedFormat('l j F');
        $html = '<span class="myat-day-date">'.e($date).'</span>';

        if ($jobs->isNotEmpty()) {
            $pax = $jobs->sum(fn ($job): int => (int) preg_replace('/\D/', '', (string) $job->Pax));
            $vehicles = $jobs->pluck('VehicleName')->filter()->unique()->values();
            $line = '<b>'.$jobs->count().'</b> '.($jobs->count() === 1 ? 'δρομολόγιο' : 'δρομολόγια').' · <b>'.$pax.'</b> άτομα';
            if ($tab === 'tomorrow') {
                $line .= ' · πρώτο <b>'.e(substr((string) $jobs->first()->TransferTime, 0, 5)).'</b>';
            }
            $html .= '<span class="myat-day">'.$line.'</span>';
            if ($vehicles->count() === 1) {
                $html .= '<span class="myat-day-vehicle">'.e($vehicles->first()).'</span>';
            }
        }

        return new HtmlString($html);
    }
}
