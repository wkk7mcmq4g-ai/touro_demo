<?php

namespace App\Support;

use Filament\Resources\Pages\Page as ResourcePage;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Layout\Component as LayoutComponent;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Component;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Throwable;

/**
 * "Εξαγωγή σε Excel" for every table: the rows the table shows now (tab, search, filters, column
 * filters, sort; every page, not just the current one) with the columns the user picks, as .xlsx.
 * Values are written as the table shows them (lookups, dates), numbers stay numbers.
 */
class TableExports
{
    public const MAX_ROWS = 50000;

    /** May this user export this table? Resources follow PHPRunner's "P" (print/export) letter. */
    public static function allowed(Component $livewire): bool
    {
        if (! $livewire instanceof HasTable || self::columns($livewire) === []) {
            return false;
        }

        if ($livewire instanceof ResourcePage) {
            $resource = $livewire::getResource();
            if (property_exists($resource, 'permissionTable')) {
                $table = (fn () => static::$permissionTable)->bindTo(null, $resource)();

                return Permissions::allows(auth()->user(), $table, 'export');
            }
        }

        return true;
    }

    /**
     * The table's columns in the order the user arranged them; `visible` = shown now.
     *
     * @return list<array{name: string, label: string, visible: bool}>
     */
    public static function columns(HasTable&Component $livewire): array
    {
        $out = [];
        foreach ($livewire->getTable()->getColumns() as $name => $column) {
            if (! $column instanceof Column || $column instanceof LayoutComponent || $column instanceof ViewColumn || blank($name) || $column->isHidden()) {
                continue;
            }
            $label = trim(strip_tags((string) $column->getLabel()));
            $out[] = ['name' => (string) $name, 'label' => $label !== '' ? $label : (string) $name, 'visible' => ! $column->isToggledHidden()];
        }

        return $out;
    }

    /**
     * Writes the file and returns a short-lived link to download it.
     *
     * @param  list<string>  $names  columns, in order
     */
    public static function export(HasTable&Component $livewire, array $names, string $title): string
    {
        abort_unless(self::allowed($livewire), 403);

        $available = collect(self::columns($livewire))->keyBy('name');
        $names = array_values(array_filter($names, fn ($n): bool => is_string($n) && $available->has($n)));
        abort_if($names === [], 422, 'Επιλέξτε τουλάχιστον μία στήλη.');

        $table = $livewire->getTable();
        $columns = array_map(fn (string $n): Column => $table->getColumn($n), $names);

        $dir = storage_path('app/exports');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        // Old files left behind (a download never opened) are removed after a day.
        foreach (glob($dir.'/*.xlsx') ?: [] as $old) {
            if (filemtime($old) < time() - 86400) {
                @unlink($old);
            }
        }

        $token = Str::random(40);
        $path = "{$dir}/{$token}.xlsx";
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(array_map(fn (string $n): string => $available[$n]['label'], $names), (new Style)->setFontBold()));

        $dateStyle = (new Style)->setFormat('dd/mm/yyyy');
        $dateTimeStyle = (new Style)->setFormat('dd/mm/yyyy hh:mm');
        $query = $livewire->getFilteredSortedTableQuery();
        $count = 0;
        foreach ($query->lazy(500) as $record) {
            if (++$count > self::MAX_ROWS) {
                break;
            }
            $cells = [];
            foreach ($columns as $column) {
                $column->record($record);
                $value = self::value($column);
                $column->clearCachedState();
                // Dates as real Excel dates (sortable, filterable), shown as dd/mm/yyyy.
                $cells[] = $value instanceof \DateTimeInterface
                    ? Cell::fromValue($value, $value->format('H:i:s') === '00:00:00' ? $dateStyle : $dateTimeStyle)
                    : Cell::fromValue($value);
            }
            $writer->addRow(new Row($cells));
        }
        $writer->close();

        $file = Str::slug($title ?: 'export').'-'.now()->format('Y-m-d_Hi').'.xlsx';
        session()->put("myat.exports.{$token}", ['path' => $path, 'name' => $file]);

        return route('table-exports.download', $token);
    }

    /** One cell: numbers as numbers, everything else as the table shows it, without HTML. */
    private static function value(Column $column): mixed
    {
        try {
            $state = $column->getState();
        } catch (Throwable) {
            return null;
        }

        if (method_exists($column, 'isDate') && ($column->isDate() || $column->isDateTime()) && filled($state) && ! is_array($state)) {
            try {
                return Carbon::parse($state)->toDateTimeImmutable();
            } catch (Throwable) {
                // not a date after all: written as text below
            }
        }
        if ($state instanceof \DateTimeInterface) {
            return $state;
        }
        if ($column instanceof IconColumn && is_bool($state)) {
            return $state ? 'Ναι' : 'Όχι';
        }
        if (is_array($state)) {
            $state = implode(', ', array_map(fn ($v) => is_scalar($v) ? (string) $v : '', $state));
        }
        if ($state instanceof \BackedEnum) {
            $state = $state->value;
        }
        if (is_int($state) || is_float($state)) {
            return $state;
        }
        if (is_string($state) && is_numeric($state) && ! preg_match('/^0\d/', $state) && strlen($state) < 15) {
            return $state + 0;
        }

        try {
            $shown = method_exists($column, 'formatState') ? $column->formatState($state) : $state;
        } catch (Throwable) {
            $shown = $state;
        }
        if ($shown instanceof HtmlString || (is_object($shown) && method_exists($shown, 'toHtml'))) {
            $shown = $shown->toHtml();
        }
        if ($shown instanceof \DateTimeInterface) {
            $shown = Carbon::instance($shown)->format('d/m/Y H:i');
        }
        if (! is_scalar($shown) && $shown !== null) {
            $shown = is_object($shown) && method_exists($shown, '__toString') ? (string) $shown : null;
        }

        return $shown === null ? null : html_entity_decode(trim(preg_replace('/\s+/u', ' ', strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", (string) $shown)))), ENT_QUOTES | ENT_HTML5);
    }
}
