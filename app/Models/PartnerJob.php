<?php

namespace App\Models;

use App\Models\Concerns\ReadOnlyModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A job in a partner company's own app (config/partners.php), read from their database.
 *
 * @property int $TransferID
 * @property Carbon|null $TransferDate
 * @property string|null $MyatStatus
 */
class PartnerJob extends Model
{
    use ReadOnlyModel;

    protected $table = 'partner_jobs';

    protected $primaryKey = 'TransferID';

    public $incrementing = false;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['TransferDate' => 'date', 'ImportDate' => 'date', 'PurchasePrice' => 'float', 'SalePrice' => 'float'];
    }

    private static function database(string $partner): string
    {
        $db = (string) config("partners.{$partner}.database");
        abort_unless(preg_match('/^[A-Za-z0-9_]+$/', $db) === 1, 500, 'Bad partner database name.');

        return $db;
    }

    /** Jobs the partner gave MyAT (PHPRunner "<partner>_jobs"): New, or Copied into our transfers. */
    public static function forUs(string $partner): Builder
    {
        $p = config("partners.{$partner}");
        $db = self::database($partner);

        $copy = '(SELECT MIN(t.TransferID) FROM transfers t WHERE t.SupplierCode = CONCAT('.DB::getPdo()->quote($p['prefix']).', v.TransferID))';

        $jobs = DB::table("{$db}.v_transfers2 as v")
            ->where('v.CoachCompanyID', $p['coach'])
            ->select(['v.TransferID', 'v.Status', 'v.CarType', 'v.Pax', 'v.TransferDate', 'v.TransferType', 'v.TransferTime', 'v.From', 'v.To',
                'v.Flight_Ship_Num_From', 'v.Assistant', 'v.PassengerName', 'v.LeadName', 'v.LeadPhone', 'v.Notes', 'v.PurchasePrice', 'v.InvoicedRcvd'])
            ->selectRaw("{$copy} AS OurTransferID, IF({$copy} IS NULL, 'New', 'Copied') AS MyatStatus");

        return static::query()->fromSub($jobs, 'partner_jobs');
    }

    /** Jobs MyAT gave the partner (PHPRunner "myat_jobs_<partner>"): their transfers for our customer ID. */
    public static function byUs(string $partner): Builder
    {
        $db = self::database($partner);

        $jobs = DB::table("{$db}.transfers as t")
            ->where('t.cID_Customer', config("partners.{$partner}.our_customer"))
            ->select(['t.TransferID', 't.ImportDate', 't.Pax', 't.SupplierCode', 't.TransferType', 't.TransferDate', 't.TransferTime', 't.Status',
                't.From', 't.To', 't.Flight_Ship_Num_From', 't.LeadName', 't.Notes', 't.Assistant', 't.CarTypeID', 't.PassengerName', 't.SalePrice']);

        return static::query()->fromSub($jobs, 'partner_jobs');
    }

    /** Whether we can read that partner's database at all (it may be on another server or not granted). */
    public static function reachable(string $partner): bool
    {
        try {
            DB::table(self::database($partner).'.v_transfers2')->limit(1)->exists();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
