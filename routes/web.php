<?php

use App\Http\Controllers\TransferFileController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

// Transfer files are stored in the database (myat_transfers_files.Attachment).
Route::get('/files/transfers/{id}', [TransferFileController::class, 'download'])
    ->whereNumber('id')
    ->name('transfer-files.download');
