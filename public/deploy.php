<?php

/*
 * Unpacks a new version sent by GitHub Actions (see .github/workflows/package.yml).
 *
 * The workflow uploads one zip over FTPS to the app folder (deploy-package.zip), then calls this
 * page with the DEPLOY_TOKEN from the server's .env. Without that token, or if it is not set,
 * the page answers 404 and does nothing. The server's .env and storage/ are never touched.
 */

$root = dirname(__DIR__);

function myat_env(string $file, string $key): string
{
    foreach (is_readable($file) ? file($file, FILE_IGNORE_NEW_LINES) : [] as $line) {
        if (preg_match('/^\s*'.preg_quote($key, '/').'\s*=\s*"?([^"\s#]*)"?/', $line, $m)) {
            return $m[1];
        }
    }

    return '';
}

$token = myat_env($root.'/.env', 'DEPLOY_TOKEN');
$given = (string) ($_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? '');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || strlen($token) < 32 || ! hash_equals($token, $given)) {
    http_response_code(404);
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
set_time_limit(600);
ignore_user_abort(true);

$package = $root.'/deploy-package.zip';
$zip = new ZipArchive;
if (! is_file($package) || $zip->open($package) !== true) {
    http_response_code(422);
    exit("No package at deploy-package.zip\n");
}

$written = 0;
for ($i = 0; $i < $zip->numFiles; $i++) {
    $name = (string) $zip->getNameIndex($i);
    // Entries are touro_myat/<path>.
    $path = preg_replace('#^touro_myat/#', '', $name);
    if ($path === '' || str_ends_with($path, '/') || str_contains($path, '..') || str_starts_with($path, '/')
        || $path === '.env' || str_starts_with($path, 'storage/') || str_starts_with($path, 'public/storage/')
        || $path === 'deploy-package.zip') {
        continue;
    }

    $target = $root.'/'.$path;
    if (! is_dir(dirname($target)) && ! mkdir(dirname($target), 0755, true) && ! is_dir(dirname($target))) {
        http_response_code(500);
        exit("Cannot create folder for {$path}\n");
    }

    $in = $zip->getStream($name);
    $tmp = $target.'.deploying';
    $out = fopen($tmp, 'wb');
    if (! $in || ! $out) {
        http_response_code(500);
        exit("Cannot write {$path}\n");
    }
    stream_copy_to_stream($in, $out);
    fclose($in);
    fclose($out);
    rename($tmp, $target);
    $written++;
}
$zip->close();
unlink($package);

// Cached config/routes and compiled views from the old version.
foreach (array_merge(
    glob($root.'/bootstrap/cache/{config,events}.php', GLOB_BRACE) ?: [],
    glob($root.'/bootstrap/cache/routes-*.php') ?: [],
    glob($root.'/storage/framework/views/*.php') ?: [],
) as $file) {
    @unlink($file);
}
if (function_exists('opcache_reset')) {
    opcache_reset();
}

echo "Deployed {$written} files\n";
