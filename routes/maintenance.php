<?php

use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MaintenanceController;
use Illuminate\Support\Facades\Route;

/*
| Routes in this file run in the "maintenance" middleware group: no session,
| no cookie encryption and no database, so they work on a fresh server.
*/

Route::get('/locale/{locale}', LocaleController::class)->name('locale.switch');

Route::controller(MaintenanceController::class)->prefix('maintenance')->group(function () {
    Route::get('/', 'show')->name('maintenance');
    Route::post('/login', 'login')->name('maintenance.login');
    Route::post('/logout', 'logout')->name('maintenance.logout');
    Route::post('/generate-key', 'generateKey')->name('maintenance.generate-key');
    Route::post('/migrate', 'migrate')->name('maintenance.migrate');
    Route::post('/clear-caches', 'clearCaches')->name('maintenance.clear-caches');
});
