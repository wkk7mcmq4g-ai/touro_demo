<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Excel-friendly CSV (UTF-8 with BOM, `;` separated, so Greek and decimals open correctly in
 * Greek Excel): PHPRunner's "Export" on lists and reports.
 */
class CsvDownload
{
    /**
     * @param  array<string, string>  $columns  column => header
     * @param  iterable<object|array<string, mixed>>  $rows
     */
    public static function make(string $filename, array $columns, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($columns, $rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_values($columns), ';');
            foreach ($rows as $row) {
                $row = (array) ($row instanceof Model ? $row->getAttributes() : $row);
                fputcsv($out, array_map(fn (string $c) => self::cell($row[$c] ?? null), array_keys($columns)), ';');
            }
            fclose($out);
        }, $filename.'-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private static function cell(mixed $value): string
    {
        if (is_float($value) || (is_string($value) && is_numeric($value) && str_contains($value, '.'))) {
            return str_replace('.', ',', (string) round((float) $value, 2));
        }

        $value = (string) $value;

        // Keep spreadsheet formulas from running when the file is opened.
        return preg_match('/^[=+\-@]/', $value) && ! is_numeric($value) ? "'".$value : $value;
    }
}
