<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EntryController;
use App\Http\Controllers\ErrorLogController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\LocationSettingsController;
use App\Http\Controllers\LandmarkSettingsController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\MapSettingsController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkTypeController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'show'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->middleware('guest');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::middleware(['auth', 'role:user'])->group(function () {
    Route::get('/', fn () => redirect()->route('board'));
    Route::get('/board', [EntryController::class, 'board'])->name('board');
    Route::get('/map', [MapController::class, 'index'])->name('map');

    Route::get('/log', [EntryController::class, 'create'])->name('entries.create');
    Route::post('/log', [EntryController::class, 'store'])->name('entries.store');
    Route::post('/entries/{entry}/close', [EntryController::class, 'close'])->name('entries.close');
    Route::post('/entries/{entry}/start', [EntryController::class, 'start'])->name('entries.start');
    Route::post('/entries/{entry}/cancel', [EntryController::class, 'cancel'])->name('entries.cancel');
    Route::get('/pending', [EntryController::class, 'pending'])->name('pending');

    Route::get('/locations/{location}/document', [LocationSettingsController::class, 'download'])->name('locations.document');

    Route::get('/logbook', [ReportController::class, 'logbook'])->name('logbook');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports.csv', [ReportController::class, 'csv'])->name('reports.csv');

    // Retain the detailed historical view for compatibility and audit review.
    Route::get('/history', [EntryController::class, 'history'])->name('history');
    Route::get('/history.csv', [EntryController::class, 'csv'])->name('history.csv');

    Route::get('/api/open', [EntryController::class, 'openJson'])->name('api.open');
    Route::get('/api/layers', [MapController::class, 'layers'])->name('api.layers');
    Route::get('/api/areas', [MapController::class, 'areas'])->name('api.areas');
    Route::get('/api/landmarks', [MapController::class, 'landmarks'])->name('api.landmarks');
    Route::get('/api/locations', [LocationController::class, 'search'])->name('api.locations');
    Route::post('/api/locations', [LocationController::class, 'store'])->name('api.locations.store');
    Route::get('/api/locations/nearby', [LocationController::class, 'nearby'])->name('api.locations.nearby');
    Route::post('/api/client-errors', [ErrorLogController::class, 'client'])->name('api.client-errors');
});

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::post('/settings/appearance', [SettingsController::class, 'appearance'])->name('settings.appearance');

    Route::get('/settings/users', [UserController::class, 'index'])->name('settings.users.index');
    Route::post('/settings/users', [UserController::class, 'store'])->name('settings.users.store');
    Route::patch('/settings/users/{user}', [UserController::class, 'update'])->name('settings.users.update');

    Route::get('/settings/work-types', [WorkTypeController::class, 'index'])->name('settings.work-types.index');
    Route::post('/settings/work-types', [WorkTypeController::class, 'store'])->name('settings.work-types.store');
    Route::patch('/settings/work-types/{workType}', [WorkTypeController::class, 'update'])->name('settings.work-types.update');

    Route::post('/settings/map', [MapSettingsController::class, 'update'])->name('settings.map.update');
    Route::post('/settings/map/refresh', [MapSettingsController::class, 'requestRefresh'])->name('settings.map.refresh');

    Route::get('/settings/locations', [LocationSettingsController::class, 'index'])->name('settings.locations.index');
    Route::post('/settings/locations/{location}/document', [LocationSettingsController::class, 'upload'])->name('settings.locations.document.upload');
    Route::delete('/settings/locations/{location}/document', [LocationSettingsController::class, 'remove'])->name('settings.locations.document.remove');

    Route::get('/settings/landmarks', [LandmarkSettingsController::class, 'index'])->name('settings.landmarks.index');
    Route::post('/settings/landmarks', [LandmarkSettingsController::class, 'store'])->name('settings.landmarks.store');
    Route::patch('/settings/landmarks/{landmark}', [LandmarkSettingsController::class, 'update'])->name('settings.landmarks.update');

    Route::get('/error', [ErrorLogController::class, 'index'])->name('errors.index');
    Route::get('/error/download', [ErrorLogController::class, 'download'])->name('errors.download');
    Route::post('/error/clear', [ErrorLogController::class, 'clear'])->name('errors.clear');
});
