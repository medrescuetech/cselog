<?php

use CseLog\Config;
use CseLog\Support;

/** @var array<string, mixed> $map */
/** @var array<int, array<string, mixed>> $maps */
?>
<div class="page-head">
    <h1>Live map</h1>
    <span class="muted" id="map-status">loading…</span>
    <div class="spacer"></div>
    <?php if (count($maps) > 1): ?>
        <select onchange="window.location='/map?map=' + this.value" style="width:auto">
            <?php foreach ($maps as $option): ?>
                <option value="<?= (int) $option['id'] ?>" <?= (int) $option['id'] === (int) $map['id'] ? 'selected' : '' ?>>
                    <?= Support::e($option['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>
    <a class="btn" href="/board">Board view</a>
</div>

<div class="map-wrap map-full<?= $wallboard ? ' wallboard' : '' ?>">
    <div class="map-toolbar">
        <label><input type="checkbox" data-layer="pins" checked> Open work</label>
        <label><input type="checkbox" data-layer="areas" checked> Boundaries</label>
        <label><input type="checkbox" data-layer="landmarks" checked> Landmarks</label>
        <span class="muted">Refreshing every <?= Config::int('REFRESH_SECONDS', 30) ?>s</span>
    </div>
    <div id="map"></div>
</div>

<script>
(function () {
    const status = document.getElementById('map-status');
    let focusId = new URLSearchParams(window.location.search).get('focus');
    let api = null;

    CseLog.get('/api/maps/<?= (int) $map['id'] ?>').then(data => {
        api = CseLog.imageMap('map', data);
        refresh();
        setInterval(refresh, <?= Config::int('REFRESH_SECONDS', 30) ?> * 1000);
    });

    function refresh() {
        CseLog.get('/api/open-entries').then(payload => {
            api.layers.pins.clearLayers();
            let placed = 0;
            let focused = null;

            payload.entries.forEach(entry => {
                if (entry.map_id !== <?= (int) $map['id'] ?> || entry.x === null) return;
                const marker = CseLog.entryMarker(api, entry);
                marker.addTo(api.layers.pins);
                placed++;
                if (focusId && String(entry.id) === focusId) focused = marker;
            });

            const missing = payload.entries.filter(entry =>
                entry.map_id === <?= (int) $map['id'] ?> && entry.x === null
            ).length;
            status.textContent = placed + ' open on this map' +
                (missing > 0 ? ' · ' + missing + ' without a pin' : '') +
                ' · updated ' + payload.server_time;

            if (focused) {
                api.map.setView(focused.getLatLng(), 1);
                focused.openPopup();
                focusId = null;
            }
        });
    }
}());
</script>
