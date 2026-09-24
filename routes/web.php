<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EntryController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MapController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'show'])->name('login')->middleware('guest');
Route::post('/login', [AuthController::class, 'login'])->middleware('guest');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::get('/', fn () => redirect()->route(auth()->check() && auth()->user()->role === 'map_only' ? 'map' : 'board'))->middleware('auth');

Route::middleware(['auth', 'role:map_only'])->group(function () {
    Route::get('/map', [MapController::class, 'index'])->name('map');
    Route::get('/api/open', [EntryController::class, 'openJson'])->name('api.open');
    Route::get('/api/layers', [MapController::class, 'layers'])->name('api.layers');
    Route::get('/api/areas', [MapController::class, 'areas'])->name('api.areas');
    Route::get('/api/landmarks', [MapController::class, 'landmarks'])->name('api.landmarks');
});

Route::middleware(['auth', 'role:viewer'])->group(function () {
    Route::get('/board', [EntryController::class, 'board'])->name('board');
    Route::get('/history', [EntryController::class, 'history'])->name('history');
    Route::get('/history.csv', [EntryController::class, 'csv'])->name('history.csv');
    Route::get('/api/locations', [LocationController::class, 'search'])->name('api.locations');
});

Route::middleware(['auth', 'role:logger'])->group(function () {
    Route::get('/log', [EntryController::class, 'create'])->name('entries.create');
    Route::post('/log', [EntryController::class, 'store'])->name('entries.store');
    Route::post('/entries/{entry}/close', [EntryController::class, 'close'])->name('entries.close');
    Route::post('/api/locations', [LocationController::class, 'store'])->name('api.locations.store');
    Route::get('/api/locations/nearby', [LocationController::class, 'nearby'])->name('api.locations.nearby');
});

Route::middleware(['auth', 'role:supervisor'])->group(function () {
    Route::get('/admin/locations', [\App\Http\Controllers\AdminLocationController::class, 'index'])->name('admin.locations.index');
    Route::post('/admin/locations/{location}/verify', [\App\Http\Controllers\AdminLocationController::class, 'verify'])->name('admin.locations.verify');
    Route::post('/admin/locations/{location}/archive', [\App\Http\Controllers\AdminLocationController::class, 'archive'])->name('admin.locations.archive');
    Route::post('/admin/locations/{location}/merge', [\App\Http\Controllers\AdminLocationController::class, 'merge'])->name('admin.locations.merge');
    Route::get('/admin/locations/export', [\App\Http\Controllers\AdminLocationController::class, 'export'])->name('admin.locations.export');
    Route::post('/admin/locations/import', [\App\Http\Controllers\AdminLocationController::class, 'import'])->name('admin.locations.import');
});

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin/settings', [\App\Http\Controllers\AdminSettingsController::class, 'index'])->name('admin.settings.index');
    Route::post('/admin/settings/users/{user}/role', [\App\Http\Controllers\AdminSettingsController::class, 'updateRole'])->name('admin.settings.users.role');
    Route::post('/admin/settings/work-types', [\App\Http\Controllers\AdminSettingsController::class, 'storeWorkType'])->name('admin.settings.worktypes.store');
    Route::post('/admin/settings/work-types/{workType}/toggle', [\App\Http\Controllers\AdminSettingsController::class, 'toggleWorkType'])->name('admin.settings.worktypes.toggle');
});
