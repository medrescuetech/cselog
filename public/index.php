<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use CseLog\Auth;
use CseLog\Config;
use CseLog\Controllers\AdminController;
use CseLog\Controllers\AuthController;
use CseLog\Controllers\BoardController;
use CseLog\Controllers\EntryController;
use CseLog\Controllers\HistoryController;
use CseLog\Controllers\LocationController;
use CseLog\Controllers\MapDataController;
use CseLog\Http;
use CseLog\Router;

if (!Config::isConfigured()) {
    http_response_code(503);
    exit('CSE Log is not installed yet. Run: php bin/install.php');
}

Auth::start();

$router = new Router();

$auth = new AuthController();
$entries = new EntryController();
$board = new BoardController();
$history = new HistoryController();
$locations = new LocationController();
$maps = new MapDataController();
$admin = new AdminController();

// Public
$router->get('/login', [$auth, 'loginForm'], 'public');
$router->post('/login', [$auth, 'login'], 'public');
$router->post('/logout', [$auth, 'logout'], 'viewer');

// Board and map
$router->get('/', static fn () => Http::redirect('/board'), 'viewer');
$router->get('/board', [$board, 'index'], 'viewer');
$router->get('/map', [$board, 'map'], 'viewer');
$router->get('/api/open-entries', [$board, 'openEntriesJson'], 'viewer');
$router->get('/api/maps/{id}', [$maps, 'show'], 'viewer');
$router->get('/api/map', [$maps, 'show'], 'viewer');

// Logging
$router->get('/log', [$entries, 'create'], 'logger');
$router->post('/log', [$entries, 'store'], 'logger');
$router->get('/entries/{id}/pin', [$entries, 'pinForm'], 'logger');
$router->post('/entries/{id}/pin', [$entries, 'pinStore'], 'logger');
$router->get('/entries/{id}', [$entries, 'show'], 'viewer');
$router->post('/entries/{id}/close', [$entries, 'close'], 'logger');
$router->post('/entries/{id}/cancel', [$entries, 'cancel'], 'supervisor');
$router->post('/entries/{id}/reopen', [$entries, 'reopen'], 'supervisor');
$router->post('/entries/{id}/promote', [$locations, 'promote'], 'supervisor');

// History
$router->get('/history', [$history, 'index'], 'viewer');
$router->get('/history/export.csv', [$history, 'exportCsv'], 'viewer');
$router->get('/shift-report', [$history, 'shiftReport'], 'viewer');

// Locations
$router->get('/api/locations', [$locations, 'search'], 'viewer');
$router->get('/api/locations/nearby', [$locations, 'nearby'], 'logger');

// Admin
$router->get('/admin', [$admin, 'index'], 'admin');
$router->get('/admin/work-types', [$admin, 'workTypes'], 'admin');
$router->post('/admin/work-types', [$admin, 'workTypeStore'], 'admin');
$router->post('/admin/work-types/{id}', [$admin, 'workTypeUpdate'], 'admin');
$router->get('/admin/locations', [$locations, 'index'], 'supervisor');
$router->get('/admin/locations/export.csv', [$locations, 'exportCsv'], 'supervisor');
$router->post('/admin/locations/{id}', [$locations, 'update'], 'supervisor');
$router->post('/admin/locations/{id}/verify', [$locations, 'verify'], 'supervisor');
$router->post('/admin/locations/{id}/archive', [$locations, 'archive'], 'supervisor');
$router->post('/admin/locations/{id}/alias', [$locations, 'addAlias'], 'supervisor');
$router->post('/admin/locations/{id}/merge', [$locations, 'merge'], 'supervisor');
$router->post('/admin/locations/{id}/move', [$locations, 'move'], 'supervisor');
$router->get('/admin/maps', [$admin, 'maps'], 'admin');
$router->post('/admin/maps', [$admin, 'mapStore'], 'admin');
$router->post('/admin/maps/{id}', [$admin, 'mapUpdate'], 'admin');
$router->get('/admin/overlays', [$admin, 'overlays'], 'admin');
$router->post('/admin/areas', [$admin, 'areaStore'], 'admin');
$router->post('/admin/areas/{id}/delete', [$admin, 'areaDelete'], 'admin');
$router->post('/admin/landmarks', [$admin, 'landmarkStore'], 'admin');
$router->post('/admin/landmarks/{id}/delete', [$admin, 'landmarkDelete'], 'admin');
$router->get('/admin/users', [$admin, 'users'], 'admin');
$router->post('/admin/users', [$admin, 'userStore'], 'admin');
$router->post('/admin/users/{id}', [$admin, 'userUpdate'], 'admin');

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
