<?php

namespace App\Models;

use App\Models\Concerns\ReadOnlyModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One driver or tour guide with jobs on a day: PHPRunner's v_daily_transfers_emails, whose SQL is
 * broken on the server (#1271, mixed collations in its UNION), rebuilt here on v_transfers: who gets the day's transfers by e-mail or Telegram, and whether they
 * confirmed reading them (emails_sent).
 *
 * @property string $row_key
 * @property float $ID
 * @property Carbon $TransferDate
 * @property string|null $Fullname
 * @property string|null $Email
 * @property string $Type Driver | Assistant
 * @property string|null $VehicleName
 * @property int $Transfers
 * @property int|null $TelegramID
 * @property string $SendStatus Read | Not read
 */
class DailyRecipient extends Model
{
    use ReadOnlyModel;

    protected $table = 'daily_recipients';

    protected $primaryKey = 'row_key';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected function casts(): array
    {
        return ['TransferDate' => 'date', 'Transfers' => 'integer', 'TelegramID' => 'integer', 'ID' => 'float'];
    }

    public static function source(): Builder
    {
        // Text is converted to one collation: mixing them in a UNION is what breaks the PHPRunner view.
        $t = fn (string $column): string => "CONVERT({$column} USING utf8mb4) COLLATE utf8mb4_general_ci";

        $drivers = DB::table('v_transfers')
            ->selectRaw("aid AS ID, TransferDate, {$t('Fullname')} AS Fullname, {$t('Email')} AS Email, {$t("'Driver'")} AS Type, {$t('VehicleName')} AS VehicleName, COUNT(*) AS Transfers, MAX(TelegramID) AS TelegramID")
            ->groupBy('aid', 'TransferDate', 'Fullname', 'Email', 'VehicleName');

        $guides = DB::table('v_transfers')
            ->whereNotNull('assistantid')
            ->selectRaw("assistantid AS ID, TransferDate, {$t('cID_Assistant')} AS Fullname, {$t('AssistantEmail')} AS Email, {$t("'Assistant'")} AS Type, {$t("''")} AS VehicleName, COUNT(*) AS Transfers, NULL AS TelegramID")
            ->groupBy('assistantid', 'TransferDate', 'cID_Assistant', 'AssistantEmail');

        $all = DB::query()->fromSub($drivers->unionAll($guides), 'r')
            ->leftJoin('emails_sent as e', fn ($j) => $j->on('e.TransferDate', '=', 'r.TransferDate')->on('e.aID', '=', 'r.ID'))
            ->selectRaw("r.*, IF(MAX(e.ID) IS NULL, 'Not read', 'Read') AS SendStatus, CONCAT(r.Type, '-', r.ID, '-', r.TransferDate) AS row_key")
            ->groupBy('r.ID', 'r.TransferDate', 'r.Fullname', 'r.Email', 'r.Type', 'r.VehicleName', 'r.Transfers', 'r.TelegramID');

        return static::query()->fromSub($all, 'daily_recipients');
    }

    /** The recipient's jobs that day, as PHPRunner's buttons pick them (by e-mail address, in order). */
    public function jobs(): Collection
    {
        return VTransfer::query()
            ->where('TransferDate', $this->TransferDate)
            ->when($this->Type === 'Driver',
                fn ($q) => filled($this->Email) ? $q->where('Email', $this->Email) : $q->where('aid', $this->ID),
                fn ($q) => $q->where('AssistantEmail', $this->Email))
            ->orderBy('row_num')
            ->get();
    }

    /**
     * The day's jobs as one Telegram message: the view's "telsms", line for line
     * (drivers get a header and numbered jobs; guides the plain list).
     */
    public function telegramText(): string
    {
        $jobs = $this->jobs();
        $v = fn ($value): string => (string) $value;

        if ($this->Type !== 'Driver') {
            return $jobs->map(fn (VTransfer $j): string => implode("\r", [
                '*Ημερομηνία:* '.$j->TransferDate?->format('d/m/Y'),
                '*Ωρα Pickup:* '.$v($j->TransferTime),
                '*Άτομα:* '.$v($j->Pax),
                '*Τύπος:* '.$v($j->TransferType),
                '*Σημείο Παραλαβής:* '.$v($j->From),
                '*Σημείο Αποβίβασης:* '.$v($j->To),
                '*Πληροφορίες:* '.$v($j->Flight_Ship_Num_From),
                '*Όνομα επιβάτη:* '.$v($j->LeadName),
                '*Τηλέφωνο επιβάτη:* '.$v($j->LeadPhone),
                '*Συνοδός - Ξεναγός:* '.$v($j->Assistant),
                '*Οδηγός:* '.$v($j->Fullname).'-'.$v($j->DriverPhone),
                '*Παρατηρήσεις:* '.$v($j->Notes),
                '*Επιβάτες:* '.$v($j->PassengerName),
            ]))->implode("\r\n\r\n");
        }

        $header = implode("\r\n\r\n", [
            '*My Athens Transfers IKE: 0261E60000721601*',
            '*Ημερομηνία:* '.$this->TransferDate->format('d/m/Y'),
            '*Οδηγός:* '.$v($this->Fullname),
            '*Συνολικά Δρομολόγια:* '.$jobs->count(),
            '---------------------------------------',
        ]);

        return $header."\r\n\r\n".$jobs->map(fn (VTransfer $j): string => '*_Δρομολόγιο_* #'.$j->row_num."\r\n\r\n".implode("\n\n", [
            '*Ημερομηνία:* '.$j->TransferDate?->format('d/m/Y'),
            '*Ωρα Pickup:* '.$v($j->TransferTime),
            '*Άτομα:* '.$v($j->Pax),
            '*Τύπος:* '.$v($j->TransferType),
            '*Σημείο Παραλαβής:* '.$v($j->From),
            '*Σημείο Αποβίβασης:* '.$v($j->To),
            '*Πληροφορίες:* '.$v($j->Flight_Ship_Num_From),
            '*Όνομα επιβάτη:* '.$v($j->LeadName),
            '*Τηλέφωνο επιβάτη:* '.$v($j->LeadPhone),
            '*Συνοδός - Ξεναγός:* '.$v($j->Assistant),
            '*Οδηγός:* '.$v($j->Fullname).'-'.$v($j->DriverPhone),
            '*Παρατηρήσεις:* '.$v($j->Notes),
            '*Επιβάτες:* '.$v($j->PassengerName),
        ]))->implode("\r\n\r\n");
    }

    /** PHPRunner "Read" (New_Button87): the driver confirmed they read the day's transfers. */
    public function markRead(): void
    {
        DB::table('emails_sent')->insert(['TransferDate' => $this->TransferDate->toDateString(), 'aID' => (int) $this->ID]);
    }
}
