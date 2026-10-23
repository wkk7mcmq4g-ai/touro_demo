<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * PHPRunner "Document upload" fields (e.g. drivers.Files, fuel_register.Files): a JSON
 * list of files kept in the PHPRunner app's own `files` folder on the server.
 * Files are linked through the PHPRunner site; new uploads are written into that folder
 * when PHPRUNNER_ROOT points at it (the same server), so PHPRunner keeps seeing them.
 */
class LegacyFiles
{
    /** @return list<array{name: string, usrName: string, size: int, type: string}> */
    public static function parse(?string $json): array
    {
        $files = json_decode((string) $json, true);

        return is_array($files) ? array_values(array_filter($files, fn ($f): bool => is_array($f) && filled($f['name'] ?? null))) : [];
    }

    public static function url(string $name): string
    {
        return rtrim((string) config('services.phprunner.url'), '/').'/'.implode('/', array_map('rawurlencode', explode('/', ltrim($name, '/'))));
    }

    /** Links to the files, for lists and forms. */
    public static function links(?string $json): HtmlString
    {
        return new HtmlString(implode('<br>', array_map(
            fn (array $f): string => '<a style="text-decoration:underline" target="_blank" rel="noopener" href="'.e(self::url($f['name'])).'">'.e($f['usrName'] ?? basename($f['name'])).'</a>',
            self::parse($json),
        )));
    }

    public static function canUpload(): bool
    {
        $root = config('services.phprunner.root');

        return filled($root) && is_dir($root.'/files') && is_writable($root.'/files');
    }

    /**
     * Store new uploads in PHPRunner's files folder and append them to the JSON list.
     *
     * @param  array<int, TemporaryUploadedFile>  $uploads
     */
    public static function append(?string $json, array $uploads): string
    {
        $files = self::parse($json);

        foreach ($uploads as $upload) {
            $original = $upload->getClientOriginalName();
            $stored = Str::slug(pathinfo($original, PATHINFO_FILENAME)).'_'.Str::lower(Str::random(8)).'.'.strtolower($upload->getClientOriginalExtension() ?: 'bin');
            $upload->storeAs('', $stored, ['disk' => 'phprunner_files']);

            $files[] = [
                'name' => 'files/'.$stored,
                'usrName' => $original,
                'size' => $upload->getSize(),
                'type' => (string) $upload->getMimeType(),
                'searchStr' => $original.',!:sStrEnd',
            ];
        }

        return json_encode($files, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
