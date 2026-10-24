<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Every select filter is multi-select (AppServiceProvider). Filters with their own query get the
 * picked values from here and match any of them (OR); nothing picked = no filter.
 */
class FilterValues
{
    /** @return list<string> */
    public static function of(array $data): array
    {
        $values = $data['values'] ?? (isset($data['value']) ? [$data['value']] : []);

        return array_values(array_filter(array_map('strval', (array) $values), fn (string $v): bool => $v !== ''));
    }

    /**
     * @param  array<string, callable(Builder): mixed>  $conditions  value => adds where-clauses
     */
    public static function any(Builder $query, array $data, array $conditions): Builder
    {
        $picked = array_values(array_intersect(self::of($data), array_map('strval', array_keys($conditions))));
        if ($picked === []) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($picked, $conditions): void {
            foreach ($picked as $value) {
                $query->orWhere(fn (Builder $q) => $conditions[$value]($q));
            }
        });
    }
}
