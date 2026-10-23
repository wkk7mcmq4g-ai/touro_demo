<?php

namespace App\Models;

use App\Models\Concerns\ReadOnlyModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * PHPRunner "Άδειες Οδηγών" (drivers_holidays_master datasource): leave periods entered in
 * drivers_holidays_master plus the single days marked in the drivers' calendar (`schedule`
 * statuses 0 ΡΕΠΟ, 5 ΣΥΝΤΗΡΗΣΗ, 8 ΑΝΑΡΡΩΤΙΚΗ, 9 ΑΔΕΙΑ) up to the end of this month.
 *
 * @property int $row_key
 * @property int|null $DriverID
 * @property Carbon|null $HolidayStartDate
 * @property Carbon|null $HolidayEndDate
 * @property string|null $HolidayType
 * @property int|null $HolidayDays
 * @property Carbon|null $HolidayPeriod
 * @property string|null $ActiveHoliday
 */
class DriverLeaveEntry extends Model
{
    use ReadOnlyModel;

    protected $table = 'leave_entries';

    protected $primaryKey = 'row_key';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'HolidayStartDate' => 'date',
            'HolidayEndDate' => 'date',
            'HolidayPeriod' => 'date',
            'HolidayDays' => 'integer',
            'DriverID' => 'integer',
        ];
    }

    public static function source(): Builder
    {
        $union = <<<'SQL'
            SELECT DriverID, HolidayStartDate, HolidayEndDate, HolidayType, HolidayDays, HolidayPeriod, ActiveHoliday
              FROM drivers_holidays_master
            UNION ALL
            SELECT s.driver_id, s.date, s.date,
                   CASE s.status WHEN 0 THEN 'ΡΕΠΟ' WHEN 9 THEN 'ΑΔΕΙΑ' WHEN 5 THEN 'ΣΥΝΤΗΡΗΣΗ' WHEN 8 THEN 'ΑΝΑΡΡΩΤΙΚΗ' END,
                   1, LAST_DAY(s.date), IF(CURDATE() = s.date, 'ΝΑΙ', 'ΟΧΙ')
              FROM schedule s
             WHERE s.status IN (0, 5, 9, 8) AND s.date <= LAST_DAY(CURRENT_DATE)
        SQL;

        return static::query()->fromSub(
            DB::query()->fromRaw("({$union}) a")->selectRaw('a.*, ROW_NUMBER() OVER (ORDER BY HolidayStartDate DESC, DriverID) AS row_key'),
            'leave_entries',
        );
    }
}
