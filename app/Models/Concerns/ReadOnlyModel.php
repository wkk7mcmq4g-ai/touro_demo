<?php

namespace App\Models\Concerns;

use LogicException;

/**
 * For models over database views: reading is fine, writing is a bug.
 */
trait ReadOnlyModel
{
    public static function bootReadOnlyModel(): void
    {
        $deny = fn ($model) => throw new LogicException(class_basename($model).' is a database view and is read-only.');

        static::saving($deny);
        static::deleting($deny);
    }
}
