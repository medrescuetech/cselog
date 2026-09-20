<?php

use CseLog\Auth;
use CseLog\Http;
use CseLog\Support;

$csrf = Support::e(Http::csrfToken());
?>
<div class="page-head"><h1>Users</h1><div class="spacer"></div><a class="btn" href="/admin">Admin</a></div>

<div class="card">
    <p class="hint">
        viewer — read only · logger — log and close entries · supervisor — plus cancel, reopen and the location catalogue ·
        admin — everything, including users and maps.
    </p>
</div>

<?php foreach ($users as $user): ?>
    <div class="card">
        <form method="post" action="/admin/users/<?= (int) $user['id'] ?>" class="field-row" style="align-items:flex-end">
            <input type="hidden" name="_csrf" value="<?= $csrf ?>">
            <div class="field" style="flex:2 1 220px">
                <label><?= Support::e($user['username']) ?></label>
                <input type="text" name="display_name" value="<?= Support::e($user['display_name']) ?>">
            </div>
            <div class="field" style="flex:1 1 150px">
                <label>Role</label>
                <select name="role">
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= $role ?>" <?= $user['role'] === $role ? 'selected' : '' ?>><?= $role ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field" style="flex:0 0 auto"><button class="btn btn-primary" type="submit">Save</button></div>
        </form>

        <div class="chip-row">
            <?php if ((int) $user['active'] === 0): ?><span class="pill">inactive</span><?php endif; ?>
            <span class="pill">last seen <?= Support::e(Support::local($user['last_login_at'] === null ? null : (string) $user['last_login_at'])) ?></span>

            <form method="post" action="/admin/users/<?= (int) $user['id'] ?>" class="inline">
                <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                <input type="hidden" name="action" value="toggle">
                <button class="btn btn-sm btn-ghost" type="submit"><?= (int) $user['active'] === 1 ? 'Deactivate' : 'Reactivate' ?></button>
            </form>

            <form method="post" action="/admin/users/<?= (int) $user['id'] ?>" class="inline">
                <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                <input type="hidden" name="action" value="password">
                <input type="password" name="password" placeholder="New password" style="width:190px;display:inline-block">
                <button class="btn btn-sm" type="submit">Set password</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<form class="card" method="post" action="/admin/users">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <h2>Add a user</h2>
    <div class="field-row">
        <div class="field"><label for="username">Username</label><input type="text" name="username" id="username" required></div>
        <div class="field"><label for="display_name">Display name</label><input type="text" name="display_name" id="display_name"></div>
        <div class="field">
            <label for="role">Role</label>
            <select name="role" id="role">
                <?php foreach (Auth::roles() as $role): ?>
                    <option value="<?= $role ?>" <?= $role === 'logger' ? 'selected' : '' ?>><?= $role ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field"><label for="password">Password</label><input type="password" name="password" id="password" required></div>
    </div>
    <button class="btn btn-primary" type="submit">Add user</button>
</form>
