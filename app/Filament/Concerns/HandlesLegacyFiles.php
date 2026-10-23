<?php

namespace App\Filament\Concerns;

use App\Support\LegacyFiles;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Text;
use Illuminate\Database\Eloquent\Model;

/**
 * For create/edit pages of tables with a PHPRunner "Document upload" column: existing files
 * are listed as links, new ones are added to PHPRunner's files folder.
 */
trait HandlesLegacyFiles
{
    /** Form components for a document column. */
    public static function legacyFilesComponents(string $column = 'Files', string $label = 'Files'): array
    {
        return [
            Text::make(fn (?Model $record) => $record && LegacyFiles::parse($record->{$column}) !== []
                ? LegacyFiles::links($record->{$column})
                : 'No files.')
                ->columnSpanFull(),
            FileUpload::make("{$column}__upload")
                ->label("Add {$label}")
                ->multiple()
                ->storeFiles(false)
                ->maxSize(20 * 1024)
                ->dehydrated()
                ->visible(fn (): bool => LegacyFiles::canUpload())
                ->columnSpanFull(),
        ];
    }

    protected function mergeLegacyFiles(array $data, ?Model $record, string $column = 'Files'): array
    {
        $uploads = $data["{$column}__upload"] ?? [];
        unset($data["{$column}__upload"]);

        if ($uploads !== [] && $uploads !== null) {
            $data[$column] = LegacyFiles::append($record?->{$column}, (array) $uploads);
        }

        return $data;
    }
}
