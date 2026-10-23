<?php

namespace App\Support;

use App\Models\Transfer;
use Illuminate\Support\Carbon;

/**
 * PHPRunner's BeforeAdd / BeforeEdit date rules for transfers, with the same messages.
 * Used by the edit form and by editing in the list.
 */
class TransferRules
{
    public static function transferDateError(mixed $value, ?Transfer $record, mixed $importDate, bool $creating): ?string
    {
        if (blank($value)) {
            return null;
        }

        $date = Carbon::parse($value)->startOfDay();
        $import = filled($importDate) ? Carbon::parse($importDate)->startOfDay() : null;

        if ($creating) {
            if ($date->lte(Carbon::today()->subDays(7))) {
                return 'Η ημερομηνία δεν μπορεί να είναι παλαιότερη των 7 ημερών';
            }

            return ($import && $date->lt($import)) ? 'Transfer Date cannot be before Date in date' : null;
        }

        // Only a changed date is checked, so old transfers stay editable.
        if (($record?->TransferDate && $record->TransferDate->isSameDay($date)) || $date->year < 2000) {
            return null;
        }

        if ($date->lte(Carbon::yesterday())) {
            return 'Η ημερομηνία δεν μπορεί να είναι παλαιότερη της σημερινής';
        }

        return ($import && $date->lt($import)) ? 'Transfer Date cannot be before Date in date' : null;
    }
}
