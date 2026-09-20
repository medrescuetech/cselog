<?php

use CseLog\Config;
use CseLog\Http;
use CseLog\Support;

$appName = Config::get('APP_NAME', 'CSE Log');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= Support::e('Sign in · ' . $appName) ?></title>
<link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<div class="login-wrap">
    <form class="card login-card" method="post" action="/login">
        <input type="hidden" name="_csrf" value="<?= Support::e(Http::csrfToken()) ?>">
        <h2><?= Support::e($appName) ?></h2>

        <?php if (isset($flash) && is_array($flash)): ?>
            <div class="flash flash-<?= Support::e($flash['type']) ?>" style="margin:0 0 12px;border-radius:6px;border:1px solid var(--line)">
                <?= Support::e($flash['message']) ?>
            </div>
        <?php endif; ?>

        <?php if ($noUsers): ?>
            <p class="hint">No users yet — run <code>php bin/install.php</code> to create the admin account.</p>
        <?php endif; ?>

        <div class="field">
            <label for="username">Username</label>
            <input type="text" name="username" id="username" autocomplete="username" autofocus>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input type="password" name="password" id="password" autocomplete="current-password">
        </div>
        <button class="btn btn-primary btn-lg" type="submit" style="width:100%">Sign in</button>
    </form>
</div>
</body>
</html>
