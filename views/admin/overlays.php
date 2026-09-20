<?php

use CseLog\Http;
use CseLog\Support;

$csrf = Support::e(Http::csrfToken());
?>
<div class="page-head">
    <h1>Landmarks &amp; boundaries</h1>
    <span class="muted"><?= Support::e($map['name']) ?></span>
    <div class="spacer"></div>
    <?php if (count($maps) > 1): ?>
        <select onchange="window.location='/admin/overlays?map=' + this.value" style="width:auto">
            <?php foreach ($maps as $option): ?>
                <option value="<?= (int) $option['id'] ?>" <?= (int) $option['id'] === (int) $map['id'] ? 'selected' : '' ?>>
                    <?= Support::e($option['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>
    <a class="btn" href="/admin/maps">Maps</a>
</div>

<div class="map-wrap map-full">
    <div class="map-toolbar">
        <button class="btn btn-sm" id="mode-landmark" type="button">Add landmark</button>
        <button class="btn btn-sm" id="mode-area" type="button">Draw boundary</button>
        <button class="btn btn-sm" id="finish-area" type="button" hidden>Finish boundary</button>
        <button class="btn btn-sm btn-ghost" id="cancel" type="button">Cancel</button>
        <span class="muted" id="hint">Pick a tool, then click the map.</span>
    </div>
    <div id="map"></div>
</div>

<div class="card">
    <h2>Boundaries (<?= count($areas) ?>)</h2>
    <table>
        <thead><tr><th>Name</th><th>Kind</th><th>Colour</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($areas as $area): ?>
            <tr>
                <td><?= Support::e($area['name']) ?></td>
                <td class="muted"><?= Support::e($area['kind'] ?? '—') ?></td>
                <td><span class="type-dot" style="background:<?= Support::e($area['colour']) ?>"></span></td>
                <td>
                    <form method="post" action="/admin/areas/<?= (int) $area['id'] ?>/delete" class="inline js-delete">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                        <button class="btn btn-sm" type="submit">Remove</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h2>Landmarks (<?= count($landmarks) ?>)</h2>
    <table>
        <thead><tr><th>Name</th><th>Category</th><th>Position</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($landmarks as $landmark): ?>
            <tr>
                <td><?= Support::e($landmark['name']) ?></td>
                <td class="muted"><?= Support::e($landmark['category'] ?? '—') ?></td>
                <td class="muted"><?= (float) $landmark['x'] ?>, <?= (float) $landmark['y'] ?></td>
                <td>
                    <form method="post" action="/admin/landmarks/<?= (int) $landmark['id'] ?>/delete" class="inline js-delete">
                        <input type="hidden" name="_csrf" value="<?= $csrf ?>">
                        <button class="btn btn-sm" type="submit">Remove</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
(function () {
    const mapId = <?= (int) $map['id'] ?>;
    const hint = document.getElementById('hint');
    const finishBtn = document.getElementById('finish-area');
    let api = null;
    let mode = null;
    let points = [];
    let draft = null;

    CseLog.get('/api/maps/' + mapId).then(data => {
        api = CseLog.imageMap('map', data);
        api.map.on('click', onClick);
    });

    function onClick(e) {
        const point = api.toPixels(e.latlng);

        if (mode === 'landmark') {
            const name = prompt('Landmark name (e.g. Gate 1, Muster Point A):');
            if (!name) return;
            const category = prompt('Category (gate, muster, infrastructure…):', 'infrastructure') || '';
            const body = new FormData();
            body.append('map_id', mapId);
            body.append('name', name);
            body.append('category', category);
            body.append('x', point.x);
            body.append('y', point.y);
            CseLog.post('/admin/landmarks', body).then(reloadOnOk);
            return;
        }

        if (mode === 'area') {
            points.push([point.x, point.y]);
            drawDraft();
            hint.textContent = points.length + ' points — click "Finish boundary" when the shape is closed.';
        }
    }

    function drawDraft() {
        if (draft) api.map.removeLayer(draft);
        if (points.length < 2) return;
        draft = L.polygon(points.map(p => api.toLatLng(p[0], p[1])), { color: '#2b7fd9', dashArray: '4 4' }).addTo(api.map);
    }

    function reloadOnOk(result) {
        if (result && result.ok) { window.location.reload(); }
        else { alert((result && result.error) || 'Could not save.'); }
    }

    document.getElementById('mode-landmark').addEventListener('click', () => {
        mode = 'landmark';
        hint.textContent = 'Click the map where the landmark is.';
        finishBtn.hidden = true;
    });

    document.getElementById('mode-area').addEventListener('click', () => {
        mode = 'area';
        points = [];
        finishBtn.hidden = false;
        hint.textContent = 'Click each corner of the area.';
    });

    finishBtn.addEventListener('click', () => {
        if (points.length < 3) { hint.textContent = 'A boundary needs at least three points.'; return; }
        const name = prompt('Boundary name (e.g. Tank Farm):');
        if (!name) return;
        const body = new FormData();
        body.append('map_id', mapId);
        body.append('name', name);
        body.append('kind', prompt('Kind (zone, restricted, building…):', 'zone') || 'zone');
        body.append('colour', '#2b7fd9');
        body.append('rings', JSON.stringify([points]));
        CseLog.post('/admin/areas', body).then(reloadOnOk);
    });

    document.getElementById('cancel').addEventListener('click', () => {
        mode = null;
        points = [];
        if (draft) { api.map.removeLayer(draft); draft = null; }
        finishBtn.hidden = true;
        hint.textContent = 'Pick a tool, then click the map.';
    });

    document.querySelectorAll('.js-delete').forEach(form => {
        form.addEventListener('submit', e => {
            if (!confirm('Remove this overlay? Existing entries keep the area they were logged against.')) {
                e.preventDefault();
            }
        });
    });
}());
</script>
