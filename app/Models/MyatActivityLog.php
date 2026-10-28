<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * `myat_activity_log`: Ιστορικό ενεργειών of the new app (written by App\Support\ActivityLog, read-only here).
 *
 * @property int $ID
 * @property Carbon|null $CreatedAt
 * @property string $Username
 * @property string $IP
 * @property string $Action
 * @property string $TableName
 * @property string $RecordID
 * @property array|null $Changes
 * @property string $Panel
 * @property string $Url
 * @property string $AppVersion
 */
class MyatActivityLog extends Model
{
    protected $table = 'myat_activity_log';

    protected $primaryKey = 'ID';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'ID' => 'integer',
            'CreatedAt' => 'datetime',
            'Changes' => 'array',
        ];
    }
}
