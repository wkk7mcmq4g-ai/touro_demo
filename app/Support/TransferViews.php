<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Ready-made views of the Transfers list, the same for everyone ("Προβολές" menu next to the search
 * box; filter `view`). Each one is a question the office asks every day. A business repo adapts the
 * place keywords and the list below.
 */
class TransferViews
{
    /** Words that mark an airport / a port in From or To (free text in PHPRunner). "port" with a space, so "Airport" isn't a port. */
    private const AIRPORT = ['airport', 'αεροδρ', 'αερολιμ'];

    private const PORT = [' port', 'port ', 'λιμάν', 'piraeus', 'πειραι', 'lavrio', 'λαύρι', 'rafina', 'ραφήν'];

    /** @var array<string, array{label: string, icon: string}> */
    public const VIEWS = [
        'today_open' => ['label' => 'Σήμερα · Χωρίς όχημα', 'icon' => 'heroicon-m-truck'],
        'tomorrow_open' => ['label' => 'Αύριο · Χωρίς όχημα', 'icon' => 'heroicon-m-truck'],
        'tomorrow_airport' => ['label' => 'Αύριο · Αεροδρόμιο', 'icon' => 'heroicon-m-paper-airplane'],
        'tomorrow_port' => ['label' => 'Αύριο · Λιμάνι', 'icon' => 'heroicon-m-lifebuoy'],
        'week_no_guide' => ['label' => 'Tours 14 ημερών · Χωρίς ξεναγό', 'icon' => 'heroicon-m-user-minus'],
        'on_hold' => ['label' => 'Πληρωμή on hold', 'icon' => 'heroicon-m-pause-circle'],
        'to_invoice' => ['label' => 'Ολοκληρωμένα · Χωρίς παραστατικό', 'icon' => 'heroicon-m-document-text'],
    ];

    /** @return array<string, string> key => label */
    public static function options(): array
    {
        return array_map(fn (array $v): string => $v['label'], self::VIEWS);
    }

    public static function apply(Builder $query, string $key): Builder
    {
        $today = Carbon::today()->toDateString();
        $tomorrow = Carbon::tomorrow()->toDateString();
        $noCoach = fn (Builder $q): Builder => $q->whereRaw("IFNULL(`CoachCompanyID`, '') = ''");

        return match ($key) {
            'today_open' => $noCoach($query->where('TransferDate', $today)),
            'tomorrow_open' => $noCoach($query->where('TransferDate', $tomorrow)),
            'tomorrow_airport' => self::touches($query->where('TransferDate', $tomorrow), self::AIRPORT),
            'tomorrow_port' => self::touches($query->where('TransferDate', $tomorrow), self::PORT),
            'week_no_guide' => $query->where('TransferType', 'Tour')
                ->where('TransferDate', '>=', $today)->where('TransferDate', '<=', Carbon::today()->addDays(14)->toDateString())
                ->where(fn (Builder $q) => $q->whereRaw("IFNULL(`Assistant`, '') = ''")->orWhere('Assistant', 'TBA')),
            'on_hold' => $query->where('PaymentOnHoldFlag', 'Ναι'),
            'to_invoice' => $query->where('Status', 'Completed')
                ->whereRaw("IFNULL(`TobeInvoiced`, '') <> 'Όχι'")
                ->whereRaw("IFNULL(`InvoicedIssued`, '') <> 'Ναι'"),
            default => $query,
        };
    }

    /** From or To contains one of the words. */
    private static function touches(Builder $query, array $words): Builder
    {
        return $query->where(function (Builder $q) use ($words): void {
            foreach ($words as $word) {
                $q->orWhere('From', 'like', '%'.$word.'%')->orWhere('To', 'like', '%'.$word.'%');
            }
        });
    }
}
