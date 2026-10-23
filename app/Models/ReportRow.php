<?php

namespace App\Models;

use App\Models\Concerns\ReadOnlyModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * A row of a report (a database view or a PHPRunner SQL datasource), read-only.
 * Views have no key, so each row gets a `row_key` for the table.
 */
class ReportRow extends Model
{
    use ReadOnlyModel;

    protected $table = 'report_rows';

    protected $primaryKey = 'row_key';

    public $timestamps = false;

    public static function over(QueryBuilder $source): Builder
    {
        return static::query()->fromSub(
            $source->getConnection()->query()->fromSub($source, 'src')->selectRaw('src.*, ROW_NUMBER() OVER () AS row_key'),
            'report_rows',
        );
    }
}
