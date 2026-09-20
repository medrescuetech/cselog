<?php

use CseLog\Http;
use CseLog\Support;

$csrf = Support::e(Http::csrfToken());
?>
<div class="page-head"><h1>Maps</h1><div class="spacer"></div><a class="btn" href="/admin/overlays">Landmarks &amp; boundaries</a></div>

<div class="card">
    <h2>Replacing the placeholder</h2>
    <p class="hint">
        Upload any image of the site — a survey plan, a GIS export, a satellite screenshot, even a photo of the
        plan on the wall. Pins are stored in the image's own pixel space, so nothing needs georeferencing.
        Existing pins stay attached to the map they were placed on, so draw the new boundaries and landmarks
        before you make the new map the default.
    </p>
</div>

<form class="card" method="post" action="/admin/maps" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <h2>Upload a map</h2>
    <div class="field-row">
        <div class="field"><label for="name">Name</label><input type="text" name="name" id="name" value="Site plan" required></div>
        <div class="field"><label for="image">Image (PNG, JPEG, WebP, SVG)</label><input type="file" name="image" id="image" accept="image/*" required></div>
    </div>
    <div class="field-row">
        <div class="field"><label for="width_px">Width in px (SVG only)</label><input type="number" name="width_px" id="width_px" placeholder="2000"></div>
        <div class="field"><label for="height_px">Height in px (SVG only)</label><input type="number" name="height_px" id="height_px" placeholder="1400"></div>
    </div>
    <button class="btn btn-primary" type="submit">Upload</button>
</form>

<?php foreach ($maps as $item): ?>
    <div class="card">
        <form method="post" action="/admin/maps/<?= (int) $item['id'] ?>" class="field-row" style="align-items:flex-end">
            <input type="hidden" name="_csrf" value="<?= $csrf ?>">
            <div class="field" style="flex:2 1 260px">
                <label>Name</label>
                <input type="text" name="name" value="<?= Support::e($item['name']) ?>">
            </div>
            <div class="field" style="flex:0 0 auto"><button class="btn btn-primary" type="submit">Save</button></div>
        </form>

        <div class="muted">
            <?= Support::e($item['image_path'] ?? 'no image') ?> ·
            <?= (int) $item['width_px'] ?>×<?= (int) $item['height_px'] ?>px ·
            <?= Support::e($item['crs']) ?>
            <?php if ((int) $item['is_default'] === 1): ?><span class="pill pill-green">default</span><?php endif; ?>
            <?php if ((int) $item['active'] === 0): ?><span class="pill">hidden</span><?php endif; ?>
        </div>

        <div class="chip-row">
            <a class="btn btn-sm" href="/map?map=<?= (int) $item['id'] ?>">View</a>
            <a class="btn btn-sm" href="/admin/overlays?map=<?= (int) $item['id'] ?>">Edit overlays</a>
            <?php foreach ([['default', 'Make default'], ['toggle', (int) $item['active'] === 1 ? 'Hide' : 'Show']] as [$action, $label]): ?>
                <form method="post" action="/admin/maps/<?= (int) $item['id'] ?>" class="inline">
                    <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                    <input type="hidden" name="action" value="<?= $action ?>">
                    <button class="btn btn-sm btn-ghost" type="submit"><?= $label ?></button>
                </form>
            <?php endforeach; ?>
        </div>

        <?php if ($item['image_path'] !== null): ?>
            <img src="<?= Support::e($item['image_path']) ?>" alt="" style="margin-top:12px;max-width:100%;max-height:220px;border:1px solid var(--line);border-radius:6px">
        <?php endif; ?>
    </div>
<?php endforeach; ?>
