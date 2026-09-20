<?php

use CseLog\Http;
use CseLog\Support;

/** @var array<string, mixed> $entry */
/** @var array<string, mixed> $map */
?>
<div class="page-head">
    <h1>Where is it?</h1>
    <span class="muted">Entry #<?= (int) $entry['id'] ?> · click the map to drop the pin</span>
</div>

<div class="map-wrap map-full">
    <div class="map-toolbar">
        <label><input type="checkbox" data-layer="areas" checked> Boundaries</label>
        <label><input type="checkbox" data-layer="landmarks" checked> Landmarks</label>
        <span class="muted" id="coords">No pin yet</span>
    </div>

    <div id="map"></div>

    <form class="pin-panel" method="post" action="/entries/<?= (int) $entry['id'] ?>/pin" id="pin-form">
        <input type="hidden" name="_csrf" value="<?= Support::e(Http::csrfToken()) ?>">
        <input type="hidden" name="x" id="x">
        <input type="hidden" name="y" id="y">

        <h2 style="margin-top:0">Save this location?</h2>
        <div id="dup" class="dup-warning" hidden></div>

        <div class="field">
            <label for="location_name">Location name</label>
            <input type="text" name="location_name" id="location_name" placeholder="e.g. Sump 4 – north wall">
            <div class="hint">Use the name people say on the radio.</div>
        </div>

        <div class="field">
            <button class="btn btn-primary btn-lg" type="submit" name="save_location" value="1" style="width:100%">
                Yes — save for next time
            </button>
        </div>
        <div class="field">
            <button class="btn" type="submit" name="save_location" value="0" style="width:100%">
                No — just this once
            </button>
            <input type="hidden" name="adhoc_label" id="adhoc_label">
        </div>
        <p class="hint">Either way the pin stays on this entry and in the history.</p>
    </form>
</div>

<script>
(function () {
    let api = null;
    let marker = null;
    let dupTimer = null;

    const xField = document.getElementById('x');
    const yField = document.getElementById('y');
    const coords = document.getElementById('coords');
    const nameField = document.getElementById('location_name');
    const dup = document.getElementById('dup');

    CseLog.get('/api/maps/<?= (int) $map['id'] ?>').then(data => {
        api = CseLog.imageMap('map', data);
        api.map.on('click', e => place(api.toPixels(e.latlng)));
    });

    function place(point) {
        xField.value = point.x;
        yField.value = point.y;
        coords.textContent = 'Pin at ' + point.x + ', ' + point.y;

        const latlng = api.toLatLng(point.x, point.y);
        if (marker) {
            marker.setLatLng(latlng);
        } else {
            marker = L.marker(latlng, {
                draggable: true,
                icon: L.divIcon({ className: '', html: '<div class="pin-marker" style="background:#d93a2b"></div>', iconSize: [16, 16], iconAnchor: [8, 8] })
            }).addTo(api.map);
            marker.on('dragend', () => place(api.toPixels(marker.getLatLng())));
        }

        checkDuplicates();
    }

    function checkDuplicates() {
        clearTimeout(dupTimer);
        dupTimer = setTimeout(() => {
            const q = '/api/locations/nearby?map_id=<?= (int) $map['id'] ?>&x=' + xField.value + '&y=' + yField.value +
                '&name=' + encodeURIComponent(nameField.value);
            CseLog.get(q).then(matches => {
                if (!matches.length) { dup.hidden = true; return; }
                dup.hidden = false;
                dup.innerHTML = '<b>Already saved nearby?</b><br>' + matches.map(m =>
                    '<label style="display:block;color:var(--text);margin-top:4px">' +
                    '<input type="radio" name="existing_pick" style="width:auto" data-name="' + CseLog.escapeHtml(m.name) + '"> ' +
                    CseLog.escapeHtml(m.name) + ' <span class="muted">' +
                    (m.distance_px !== null ? m.distance_px + 'px away' : 'similar name') + '</span></label>'
                ).join('');
                dup.querySelectorAll('input[name=existing_pick]').forEach(radio => {
                    radio.addEventListener('change', () => { nameField.value = radio.dataset.name; });
                });
            });
        }, 250);
    }

    nameField.addEventListener('input', checkDuplicates);

    document.getElementById('pin-form').addEventListener('submit', e => {
        if (!xField.value) {
            e.preventDefault();
            coords.textContent = 'Click the map to drop the pin first.';
            return;
        }
        const save = e.submitter && e.submitter.value === '1';
        if (save && !nameField.value.trim()) {
            e.preventDefault();
            nameField.focus();
            nameField.placeholder = 'Name required to save this location';
        }
        if (!save) {
            document.getElementById('adhoc_label').value = nameField.value.trim() || 'Dropped pin';
        }
    });
}());
</script>
