<?php

use CseLog\Http;
use CseLog\Support;

$csrf = Support::e(Http::csrfToken());
$tabs = [
    'active' => 'Active (' . $counts['active'] . ')',
    'unverified' => 'Unverified (' . $counts['unverified'] . ')',
    'unused' => 'Never used (' . $counts['unused'] . ')',
    'archived' => 'Archived (' . $counts['archived'] . ')',
];
?>
<div class="page-head">
    <h1>Locations</h1>
    <div class="spacer"></div>
    <a class="btn" href="/admin/locations/export.csv">Export CSV</a>
</div>

<div class="card">
    <div class="chip-row" style="margin:0 0 12px">
        <?php foreach ($tabs as $key => $label): ?>
            <a class="chip" href="/admin/locations?filter=<?= $key ?>"
               style="<?= $filter === $key ? 'border-color:var(--accent);color:var(--text)' : '' ?>"><?= Support::e($label) ?></a>
        <?php endforeach; ?>
    </div>
    <form method="get" action="/admin/locations" class="field-row" style="align-items:flex-end;margin:0">
        <input type="hidden" name="filter" value="<?= Support::e($filter) ?>">
        <div class="field" style="margin:0"><input type="search" name="q" value="<?= Support::e($search) ?>" placeholder="Search names"></div>
        <div class="field" style="margin:0;flex:0 0 auto"><button class="btn" type="submit">Search</button></div>
    </form>
</div>

<?php if ($locations === []): ?>
    <div class="card empty">Nothing here.</div>
<?php endif; ?>

<?php foreach ($locations as $location): ?>
    <div class="card">
        <div class="field-row" style="align-items:flex-end">
            <form method="post" action="/admin/locations/<?= (int) $location['id'] ?>" class="field-row" style="flex:3 1 420px;align-items:flex-end;margin:0">
                <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                <div class="field" style="flex:2 1 240px">
                    <label>Name <span class="muted">· used <?= (int) $location['usage_count'] ?>× · <?= (int) $location['entry_count'] ?> entries</span></label>
                    <input type="text" name="name" value="<?= Support::e($location['name']) ?>">
                </div>
                <div class="field" style="flex:1 1 120px">
                    <label>Code</label>
                    <input type="text" name="code" value="<?= Support::e($location['code'] ?? '') ?>">
                </div>
                <div class="field" style="flex:0 1 130px">
                    <label>Verified</label>
                    <label style="color:var(--text)">
                        <input type="checkbox" name="verified" value="1" style="width:auto" <?= (int) $location['verified'] === 1 ? 'checked' : '' ?>> Yes
                    </label>
                </div>
                <div class="field" style="flex:0 0 auto"><button class="btn btn-primary" type="submit">Save</button></div>
            </form>
        </div>

        <div class="muted">
            <?= Support::e($location['area_name'] ?? 'No area') ?> ·
            pin <?= (float) $location['x'] ?>, <?= (float) $location['y'] ?> ·
            last used <?= Support::e(Support::local($location['last_used_at'] === null ? null : (string) $location['last_used_at'])) ?>
            <?php if ($location['status'] === 'archived'): ?><span class="pill">archived</span><?php endif; ?>
            <?php if ((int) $location['verified'] === 0): ?><span class="pill pill-amber">unverified</span><?php endif; ?>
        </div>

        <div class="chip-row">
            <?php if ((int) $location['verified'] === 0): ?>
                <form method="post" action="/admin/locations/<?= (int) $location['id'] ?>/verify" class="inline">
                    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                    <input type="hidden" name="filter" value="<?= Support::e($filter) ?>">
                    <button class="btn btn-sm" type="submit">Verify</button>
                </form>
            <?php endif; ?>

            <form method="post" action="/admin/locations/<?= (int) $location['id'] ?>/archive" class="inline">
                <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                <input type="hidden" name="filter" value="<?= Support::e($filter) ?>">
                <button class="btn btn-sm" type="submit"><?= $location['status'] === 'archived' ? 'Restore' : 'Archive' ?></button>
            </form>

            <form method="post" action="/admin/locations/<?= (int) $location['id'] ?>/alias" class="inline">
                <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                <input type="text" name="alias" placeholder="Also known as…" style="width:190px;display:inline-block">
                <button class="btn btn-sm" type="submit">Add alias</button>
            </form>

            <form method="post" action="/admin/locations/<?= (int) $location['id'] ?>/merge" class="inline"
                  onsubmit="return confirm('Merge this location into the selected one? History moves with it.');">
                <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                <select name="target_id" style="width:230px;display:inline-block">
                    <option value="">Merge into…</option>
                    <?php foreach ($allLocations as $option): ?>
                        <?php if ((int) $option['id'] !== (int) $location['id']): ?>
                            <option value="<?= (int) $option['id'] ?>"><?= Support::e($option['name']) ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-sm" type="submit">Merge</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>
