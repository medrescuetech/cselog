<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EntryController;
use App\Http\Controllers\ErrorLogController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MapController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'show'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->middleware('guest');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::middleware(['auth', 'role:viewer'])->group(function () {
    Route::get('/', fn () => redirect()->route('board'));
    Route::get('/board', [EntryController::class, 'board'])->name('board');
    Route::get('/history', [EntryController::class, 'history'])->name('history');
    Route::get('/history.csv', [EntryController::class, 'csv'])->name('history.csv');
    Route::get('/map', [MapController::class, 'index'])->name('map');

    // JSON used by the board/map pollers and browser diagnostics.
    Route::get('/api/open', [EntryController::class, 'openJson'])->name('api.open');
    Route::get('/api/layers', [MapController::class, 'layers'])->name('api.layers');
    Route::get('/api/areas', [MapController::class, 'areas'])->name('api.areas');
    Route::get('/api/landmarks', [MapController::class, 'landmarks'])->name('api.landmarks');
    Route::get('/api/locations', [LocationController::class, 'search'])->name('api.locations');
    Route::post('/api/client-errors', [ErrorLogController::class, 'client'])->name('api.client-errors');
});

Route::middleware(['auth', 'role:supervisor'])->group(function () {
    Route::get('/error', [ErrorLogController::class, 'index'])->name('errors.index');
    Route::get('/error/download', [ErrorLogController::class, 'download'])->name('errors.download');
});

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::post('/error/clear', [ErrorLogController::class, 'clear'])->name('errors.clear');
});

Route::middleware(['auth', 'role:logger'])->group(function () {
    Route::get('/log', [EntryController::class, 'create'])->name('entries.create');
    Route::post('/log', [EntryController::class, 'store'])->name('entries.store');
    Route::post('/entries/{entry}/close', [EntryController::class, 'close'])->name('entries.close');
    Route::post('/api/locations', [LocationController::class, 'store'])->name('api.locations.store');
    Route::get('/api/locations/nearby', [LocationController::class, 'nearby'])->name('api.locations.nearby');
});
