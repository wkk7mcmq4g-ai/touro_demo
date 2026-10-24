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
        // Today and later come from the one cached list; an earlier date (typed in the form) is asked for.
        if ($day->gte(today())) {
            $jobs = MyJobResource::upcomingOn($day);

            return [$jobs->count(), $jobs->max('aid')];
        }
        $row = VTransfer::query()->where('username', (string) auth()->user()?->username)->where('TransferDate', $day)
            ->toBase()->selectRaw('COUNT(*) AS jobs, MAX(aid) AS aid')->first();

        return [(int) ($row->jobs ?? 0), $row->aid ?? null];
    }

    private static function alreadyRead(Carbon $day, mixed $aid): bool
    {
        static $cache = [];

        return $aid !== null && ($cache[$day->toDateString().'#'.$aid] ??= DB::table('emails_sent')->where('TransferDate', $day->toDateString())->where('aID', (int) $aid)->exists());
    }

    /**
     * PHPRunner "Διαβάστηκαν" (ReadReceipt): the driver confirms they have read a day's schedule
     * (tomorrow by default) by typing how many jobs it has; the office sees it as Read in
     * Αποστολή Transfers.
     */
    protected function getHeaderActions(): array
    {
        $tomorrow = today()->addDay();

        return [
            Action::make('readReceipt')
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
                }),
        ];
    }

    /** PHPRunner's Σήμερα / Αύριο buttons; the view itself covers the next five days. */
    public function getTabs(): array
    {
        $count = fn (Carbon $day): ?int => MyJobResource::upcomingOn($day)->count() ?: null;

        return [
            'today' => Tab::make('Σήμερα')->badge($count(today()))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('TransferDate', today())),
            'tomorrow' => Tab::make('Αύριο')->badge($count(today()->addDay()))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('TransferDate', today()->addDay())),
            'all' => Tab::make('Όλα'),
        ];
    }

    /** The day in one line: jobs, passengers, and when the next one starts. */
    public function getSubheading(): string|Htmlable|null
    {
        $tab = $this->activeTab ?? 'today';
        $day = match ($tab) {
            'today' => today(),
            'tomorrow' => today()->addDay(),
            default => null,
        };
        $jobs = $day ? MyJobResource::upcomingOn($day) : MyJobResource::upcoming();
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
