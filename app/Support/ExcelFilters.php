<?php

namespace App\Support;

use Filament\Tables\Columns\Column;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Livewire\Component;
use Throwable;

/**
 * Excel-style column filters for every table: a funnel in each column header lists the
 * column's values (dates grouped by year and month) to tick. The ticks are kept in the
 * session per table, so they stay while the user moves around, and every table query applies
 * them through one query scope (AppServiceProvider).
 */
class ExcelFilters
{
    /** Stands for NULL and '' in the lists. */
    public const EMPTY = '__empty__';

    private const MAX_VALUES = 2000;

    private static function key(Component $livewire): string
    {
        return 'myat_excel.'.str_replace('.', '_', $livewire->getName());
    }

    /** @return array<string, list<string>> column => ticked values */
    public static function active(Component $livewire): array
    {
        return (array) session(self::key($livewire), []);
    }

    /** The table's own column, if it can be filtered on: a plain column of the query, not a relationship. */
    private static function column(HasTable&Component $livewire, string $name): ?Column
    {
        if (! preg_match('/^[A-Za-z0-9_ ]{1,64}(\.[A-Za-z0-9_]{1,64})?$/', $name)) {
            return null;
        }

        return $livewire->getTable()->getColumn($name);
    }

    /**
     * A column shown through a belongs-to relationship ("customer.SupplierName"): the relationship
     * and the related column, else null.
     *
     * @return array{0: string, 1: BelongsTo, 2: string}|null
     */
    private static function relation(Builder $query, string $name): ?array
    {
        if (! str_contains($name, '.')) {
            return null;
        }
        [$relation, $attribute] = explode('.', $name, 2);
        $model = $query->getModel();
        if (! method_exists($model, $relation)) {
            return null;
        }
        try {
            $related = $model->{$relation}();
        } catch (Throwable) {
            return null;
        }

        return $related instanceof BelongsTo ? [$relation, $related, $attribute] : null;
    }

    public static function apply(Builder $query, Component $livewire, bool $isResolvingRecord = false, ?string $except = null): Builder
    {
        if ($isResolvingRecord || ! $livewire instanceof HasTable) {
            return $query;
        }

        foreach (self::active($livewire) as $name => $values) {
            if ($name === $except || ! self::column($livewire, $name)) {
                continue;
            }

            $dates = array_values(array_filter($values, fn ($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v)));
            $plain = array_values(array_diff($values, [self::EMPTY]));
            $empty = in_array(self::EMPTY, $values, true);

            // Ticked values of one column, and blanks.
            $match = function (Builder $q, string $col) use ($plain, $dates): void {
                if ($plain !== [] && count($dates) === count($plain)) {
                    $q->whereIn(DB::raw('DATE('.$q->getQuery()->getGrammar()->wrap($col).')'), $dates);
                } elseif ($plain !== []) {
                    $q->whereIn($col, $plain);
                } else {
                    $q->whereRaw('1 = 0');
                }
            };

            if ($rel = self::relation($query, $name)) {
                // e.g. the supplier's name: rows whose related record has a ticked value; blanks are
                // rows with no related record or a blank value there.
                [$relation, , $attribute] = $rel;
                $query->where(function (Builder $q) use ($relation, $attribute, $match, $empty) {
                    $q->whereHas($relation, fn (Builder $r) => $match($r, $r->getModel()->qualifyColumn($attribute)));
                    if ($empty) {
                        $q->orWhereDoesntHave($relation, fn (Builder $r) => $r->whereNotNull($r->getModel()->qualifyColumn($attribute))
                            ->where($r->getModel()->qualifyColumn($attribute), '<>', ''));
                    }
                });

                continue;
            }

            // Models over views or subqueries name their alias as the table, so this works for both.
            $col = $query->getModel()->qualifyColumn($name);
            $query->where(function (Builder $q) use ($col, $match, $empty) {
                $match($q, $col);
                if ($empty) {
                    $q->orWhereNull($col)->orWhere($col, '');
                }
            });
        }

        return $query;
    }

    /**
     * The values to tick for a column: what the table shows with the other filters on.
     *
     * @return array{type: string, values: list<array{v: string, label: string}>, selected: list<string>|null, truncated: bool}
     */
    public static function values(HasTable&Component $livewire, string $name): array
    {
        $column = self::column($livewire, $name);
        abort_unless($column !== null, 422, 'This column cannot be filtered.');

        $active = self::active($livewire);

        try {
            // The table's query with every other filter, search and tab, but not this column's ticks.
            session()->put(self::key($livewire), array_diff_key($active, [$name => true]));
            $query = $livewire->getFilteredTableQuery();
            if ($rel = self::relation($query, $name)) {
                [$relation, $belongsTo, $attribute] = $rel;
                $related = $belongsTo->getRelated();
                $qualified = $related->qualifyColumn($attribute);
                $raw = $related->newQuery()->toBase()
                    ->whereIn($related->qualifyColumn($belongsTo->getOwnerKeyName()), $query->clone()->toBase()->reorder()
                        ->select($query->getModel()->qualifyColumn($belongsTo->getForeignKeyName())))
                    ->select($qualified)->distinct()->orderBy($qualified)->limit(self::MAX_VALUES + 1)->pluck($attribute);
                if ($query->clone()->whereDoesntHave($relation, fn (Builder $r) => $r->whereNotNull($qualified)->where($qualified, '<>', ''))->exists()) {
                    $raw->push(null);
                }
            } else {
                $qualified = $query->getModel()->qualifyColumn($name);
                $raw = $query->toBase()->reorder()->select($qualified)->distinct()->orderBy($qualified)
                    ->limit(self::MAX_VALUES + 1)->pluck($name);
            }
        } catch (Throwable) {
            abort(422, 'This column cannot be filtered.');
        } finally {
            session()->put(self::key($livewire), $active);
        }

        $truncated = $raw->count() > self::MAX_VALUES;
        $raw = $raw->take(self::MAX_VALUES);
        $hasEmpty = $raw->contains(fn ($v) => $v === null || $v === '');
        $values = $raw->reject(fn ($v) => $v === null || $v === '');

        $isDate = $values->isNotEmpty() && $values->every(fn ($v) => $v instanceof \DateTimeInterface || preg_match('/^\d{4}-\d{2}-\d{2}( |$)/', (string) $v));

        if ($isDate) {
            $list = $values->map(fn ($v) => Carbon::parse($v)->toDateString())->unique()->sort()->values()
                ->map(fn (string $d) => ['v' => $d, 'label' => $d]);
        } else {
            $list = $values->map(fn ($v) => ['v' => (string) $v, 'label' => self::label($column, $v)])
                ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)->values();
        }

        if ($hasEmpty) {
            $list->push(['v' => self::EMPTY, 'label' => '(Κενά)']);
        }

        return [
            'type' => $isDate ? 'date' : 'list',
            'values' => $list->all(),
            'selected' => $active[$name] ?? null,
            'truncated' => $truncated,
        ];
    }

    /** How the column shows a value (e.g. a vehicle's name for its ID), else the value itself. */
    private static function label(Column $column, mixed $value): string
    {
        try {
            $formatted = method_exists($column, 'formatState') ? $column->formatState($value) : $value;
        } catch (Throwable) {
            $formatted = $value;
        }

        if ($formatted instanceof HtmlString || is_object($formatted) && method_exists($formatted, 'toHtml')) {
            $formatted = trim(strip_tags($formatted->toHtml()));
        }

        return is_scalar($formatted) && $formatted !== '' ? (string) $formatted : (string) $value;
    }

    /** @param  list<string>|null  $values  null clears the column's filter */
    public static function set(HasTable&Component $livewire, string $name, ?array $values): void
    {
        abort_unless(self::column($livewire, $name) !== null, 422);

        // Only a column the table's query really has (not one worked out in PHP).
        try {
            $query = $livewire->getTable()->getQuery();
            if ($rel = self::relation($query, $name)) {
                $rel[1]->getRelated()->newQuery()->toBase()->select($rel[1]->getRelated()->qualifyColumn($rel[2]))->limit(0)->get();
            } else {
                $query->clone()->toBase()->select($query->getModel()->qualifyColumn($name))->limit(0)->get();
            }
        } catch (Throwable) {
            abort(422, 'This column cannot be filtered.');
        }

        $active = self::active($livewire);

        if ($values === null) {
            unset($active[$name]);
        } else {
            $active[$name] = array_values(array_unique(array_map('strval', array_slice($values, 0, 5000))));
        }

        session()->put(self::key($livewire), $active);

        if (method_exists($livewire, 'resetPage')) {
            $livewire->resetPage();
        }
    }

    public static function clear(HasTable&Component $livewire): void
    {
        session()->forget(self::key($livewire));

        if (method_exists($livewire, 'resetPage')) {
            $livewire->resetPage();
        }
    }
}
