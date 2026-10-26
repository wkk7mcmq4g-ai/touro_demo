<?php

use App\Filament\Pages\DayTimeline;
use App\Http\Controllers\TransferFileController;
use App\Http\Controllers\VoucherController;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Route;

// One way in for everyone: the login sends office users to /admin and everyone else to /portal.
Route::get('/', fn () => redirect()->to(match (auth()->user()?->homePanelId()) {
    'admin' => '/admin',
    'portal' => '/portal',
    default => '/login',
}));
Route::redirect('/login', '/admin/login')->name('login');

// The portal's web app manifest ("Add to Home Screen"), named and coloured from config/business.php.
Route::get('/portal.webmanifest', fn () => response()->json([
    'name' => config('business.name'),
    'short_name' => config('business.short_name'),
    'start_url' => '/portal',
    'scope' => '/portal',
    'display' => 'standalone',
    'background_color' => config('business.colors.page'),
    'theme_color' => config('business.colors.navy'),
    'icons' => [
        ['src' => asset(config('business.logo.icon_192')), 'sizes' => '192x192', 'type' => 'image/png'],
        ['src' => asset(config('business.logo.icon_512')), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
    ],
], 200, ['Content-Type' => 'application/manifest+json'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))->name('portal.manifest');

// Transfer files are stored in the database (myat_transfers_files.Attachment).
Route::get('/files/transfers/{id}', [TransferFileController::class, 'download'])
    ->whereNumber('id')
    ->name('transfer-files.download');
Route::get('/files/partners/{id}', [TransferFileController::class, 'download'])
    ->defaults('kind', 'partners')
    ->whereNumber('id')
    ->name('partner-files.download');
Route::get('/files/assignments/{id}', [TransferFileController::class, 'download'])
    ->defaults('kind', 'assignments')
    ->whereNumber('id')
    ->name('assignment-files.download');
Route::get('/files/loads/{id}', [TransferFileController::class, 'download'])
    ->defaults('kind', 'loads')
    ->whereNumber('id')
    ->name('load-files.download');

// Daily dispatch vouchers (PDF of the PHPRunner v_transfers voucher pages).
Route::get('/documents/vouchers/{page}/{transfer}', [VoucherController::class, 'show'])
    ->whereNumber('transfer')
    ->name('vouchers.show');

// Ημερήσιο πλάνο as PDF, for the date shown on the page.
Route::get('/documents/day-plan/{date}', function (string $date) {
    abort_unless(auth()->check() && DayTimeline::canAccess(), 403);
    // Carbon would roll 2026-02-31 over into March: refuse dates that don't exist.
    [$y, $m, $d] = array_map('intval', explode('-', $date));
    abort_unless(checkdate($m, $d, $y), 404);
    $day = CarbonImmutable::createFromDate($y, $m, $d)->startOfDay();

    return response(DayTimeline::pdf($day), 200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'inline; filename="'.'plano-'.$day->format('Y-m-d').'.pdf"',
    ]);
})->where('date', '\d{4}-\d{2}-\d{2}')->middleware('web')->name('day-plan.pdf');

// A table export made a moment ago by the signed-in user (App\Support\TableExports); used once.
Route::get('/exports/{token}', function (string $token) {
    $export = session()->pull("myat.exports.{$token}");
    abort_unless(auth()->check() && is_array($export) && is_file($export['path'] ?? ''), 404);

    return response()->download($export['path'], $export['name'], [
        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ])->deleteFileAfterSend();
})->where('token', '[A-Za-z0-9]{40}')->middleware('web')->name('table-exports.download');
