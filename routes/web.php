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
Route::get('/files/loads/{id}', [TransferFileController::class, 'download'])
    ->defaults('kind', 'loads')
    ->whereNumber('id')
    ->name('load-files.download');

// Daily dispatch vouchers (PDF of the PHPRunner v_transfers voucher pages).
Route::get('/documents/vouchers/{page}/{transfer}', [VoucherController::class, 'show'])
    ->whereNumber('transfer')
    ->name('vouchers.show');
