<?php

use CseLog\Auth;
use CseLog\Config;
use CseLog\Http;
use CseLog\Support;

/** @var string $content */
$user = Auth::user();
$nav = [
    '/board' => 'Open board',
    '/log' => 'Log entry',
    '/map' => 'Live map',
    '/history' => 'History',
];
$current = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= Support::e(($title ?? 'CSE Log') . ' · ' . (Config::get('APP_NAME', 'CSE Log') ?? 'CSE Log')) ?></title>
<link rel="stylesheet" href="/assets/vendor/leaflet.css">
<link rel="stylesheet" href="/assets/app.css">
<meta name="csrf-token" content="<?= Support::e(Http::csrfToken()) ?>">
</head>
<body data-amber-hours="<?= Config::int('ALERT_AMBER_HOURS', 2) ?>" data-red-hours="<?= Config::int('ALERT_RED_HOURS', 4) ?>"<?= ($autoRefresh ?? false) ? ' data-refresh="' . Config::int('REFRESH_SECONDS', 30) . '"' : '' ?>>
<header class="topbar">
    <a class="brand" href="/board"><?= Support::e(Config::get('APP_NAME', 'CSE Log')) ?></a>
    <nav>
        <?php foreach ($nav as $href => $label): ?>
            <a href="<?= $href ?>" class="<?= str_starts_with($current, $href) ? 'active' : '' ?>"><?= Support::e($label) ?></a>
        <?php endforeach; ?>
        <?php if (Auth::can('supervisor')): ?>
            <a href="/admin/locations" class="<?= str_starts_with($current, '/admin') ? 'active' : '' ?>">Admin</a>
        <?php endif; ?>
    </nav>
    <div class="topbar-right">
        <a class="btn btn-primary btn-sm" href="/log">+ Log</a>
        <?php if ($user !== null): ?>
            <span class="who"><?= Support::e($user['display_name']) ?> <em><?= Support::e($user['role']) ?></em></span>
            <form method="post" action="/logout" class="inline">
                <input type="hidden" name="_csrf" value="<?= Support::e(Http::csrfToken()) ?>">
                <button class="btn btn-sm" type="submit">Sign out</button>
            </form>
        <?php endif; ?>
    </div>
</header>

<?php if (isset($flash) && is_array($flash)): ?>
    <div class="flash flash-<?= Support::e($flash['type']) ?>"><?= Support::e($flash['message']) ?></div>
<?php endif; ?>

<main class="page">
    <?= $content ?>
</main>

<script src="/assets/vendor/leaflet.js"></script>
<script src="/assets/app.js"></script>
</body>
</html>
