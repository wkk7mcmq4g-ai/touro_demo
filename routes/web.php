<?php

use App\Http\Controllers\TransferFileController;
use App\Http\Controllers\VoucherController;
use Illuminate\Support\Facades\Route;

// One way in for everyone: the login sends office users to /admin and everyone else to /portal.
Route::get('/', fn () => redirect()->to(match (auth()->user()?->homePanelId()) {
    'admin' => '/admin',
    'portal' => '/portal',
    default => '/login',
}));
Route::redirect('/login', '/admin/login')->name('login');

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

// A table export made a moment ago by the signed-in user (App\Support\TableExports); used once.
Route::get('/exports/{token}', function (string $token) {
    $export = session()->pull("myat.exports.{$token}");
    abort_unless(auth()->check() && is_array($export) && is_file($export['path'] ?? ''), 404);

    return response()->download($export['path'], $export['name'], [
        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ])->deleteFileAfterSend();
})->where('token', '[A-Za-z0-9]{40}')->middleware('web')->name('table-exports.download');
