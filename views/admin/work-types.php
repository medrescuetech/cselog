<?php

use CseLog\Http;
use CseLog\Support;

$csrf = Support::e(Http::csrfToken());
?>
<div class="page-head"><h1>Work types</h1><div class="spacer"></div><a class="btn" href="/admin">Admin</a></div>

<?php foreach ($workTypes as $type): ?>
    <div class="card">
        <form method="post" action="/admin/work-types/<?= (int) $type['id'] ?>" class="field-row" style="align-items:flex-end">
            <input type="hidden" name="_csrf" value="<?= $csrf ?>">
            <div class="field" style="flex:2 1 260px">
                <label>Name</label>
                <input type="text" name="name" value="<?= Support::e($type['name']) ?>">
            </div>
            <div class="field" style="flex:0 0 90px">
                <label>Colour</label>
                <input type="color" name="colour" value="<?= Support::e($type['colour']) ?>" style="padding:2px">
            </div>
            <div class="field" style="flex:0 1 160px">
                <label>Note required</label>
                <label style="color:var(--text)">
                    <input type="checkbox" name="requires_note" value="1" style="width:auto" <?= (int) $type['requires_note'] === 1 ? 'checked' : '' ?>> Yes
                </label>
            </div>
            <div class="field" style="flex:0 0 auto">
                <button class="btn btn-primary" type="submit">Save</button>
            </div>
        </form>

        <div class="chip-row">
            <?php if ((int) $type['is_default'] === 1): ?>
                <span class="pill pill-green">default on the log form</span>
            <?php endif; ?>
            <?php if ((int) $type['active'] === 0): ?>
                <span class="pill">retired</span>
            <?php endif; ?>

            <?php
            $buttons = [
                ['default', 'Make default', []],
                ['toggle', (int) $type['active'] === 1 ? 'Retire' : 'Reactivate', []],
                ['move', '↑ Up', ['direction' => 'up']],
                ['move', '↓ Down', ['direction' => 'down']],
            ];
            foreach ($buttons as [$action, $label, $extra]): ?>
                <form method="post" action="/admin/work-types/<?= (int) $type['id'] ?>" class="inline">
                    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                    <input type="hidden" name="action" value="<?= $action ?>">
                    <?php foreach ($extra as $key => $value): ?>
                        <input type="hidden" name="<?= Support::e($key) ?>" value="<?= Support::e($value) ?>">
                    <?php endforeach; ?>
                    <button class="btn btn-sm btn-ghost" type="submit"><?= $label ?></button>
                </form>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>

<form class="card" method="post" action="/admin/work-types">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <h2>Add a work type</h2>
    <div class="field-row">
        <div class="field"><label for="name">Name</label><input type="text" name="name" id="name" required></div>
        <div class="field" style="flex:0 0 90px"><label for="colour">Colour</label><input type="color" name="colour" id="colour" value="#6b7785" style="padding:2px"></div>
        <div class="field" style="flex:0 1 160px">
            <label>Require a note</label>
            <label style="color:var(--text)"><input type="checkbox" name="requires_note" value="1" style="width:auto"> Yes</label>
        </div>
    </div>
    <button class="btn btn-primary" type="submit">Add</button>
    <p class="hint">Retiring keeps history intact; the type just disappears from the log form.</p>
</form>
