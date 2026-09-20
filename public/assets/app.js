/* CSE Log front end. No build step: plain ES2017 + Leaflet. */
(function () {
    'use strict';

    const CseLog = window.CseLog = {};

    const meta = document.querySelector('meta[name="csrf-token"]');
    CseLog.csrf = meta ? meta.getAttribute('content') : '';

    CseLog.post = function (url, body) {
        return fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CseLog.csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: body
        }).then(r => r.json().catch(() => ({ ok: r.ok })));
    };

    CseLog.get = function (url) {
        return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json());
    };

    CseLog.hhmm = function (seconds) {
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        return h + ':' + String(m).padStart(2, '0');
    };

    /* ── Live clocks on the open board ──────────────────────────────────── */

    function tickClocks() {
        document.querySelectorAll('[data-age-seconds]').forEach(el => {
            const seconds = parseInt(el.dataset.ageSeconds, 10) + 1;
            el.dataset.ageSeconds = seconds;
            el.textContent = CseLog.hhmm(seconds);

            const card = el.closest('[data-age-level]');
            if (!card) return;
            const amber = parseInt(document.body.dataset.amberHours || '2', 10) * 3600;
            const red = parseInt(document.body.dataset.redHours || '4', 10) * 3600;
            const level = seconds >= red ? 'red' : (seconds >= amber ? 'amber' : 'grey');
            if (card.dataset.ageLevel !== level) {
                card.classList.remove('age-grey', 'age-amber', 'age-red');
                card.classList.add('age-' + level);
                card.dataset.ageLevel = level;
            }
        });
    }

    if (document.querySelector('[data-age-seconds]')) {
        setInterval(tickClocks, 1000);
    }

    /* Reload the board periodically so new radio calls appear on every screen. */
    const autoRefresh = document.body.dataset.refresh;
    if (autoRefresh) {
        setInterval(() => {
            if (!document.querySelector('.combo-list:not([hidden])')) {
                window.location.reload();
            }
        }, parseInt(autoRefresh, 10) * 1000);
    }

    /* ── Image map ──────────────────────────────────────────────────────── */

    /**
     * Builds a Leaflet map in pixel space (CRS.Simple) over the site image.
     * Pixel coordinates map to Leaflet latLng as [height - y, x], so the image
     * top-left is (0,0) and everything stored in the database is plain pixels.
     */
    CseLog.imageMap = function (elementId, data, options) {
        options = options || {};
        const h = data.height, w = data.width;

        const map = L.map(elementId, {
            crs: L.CRS.Simple,
            minZoom: -4,
            maxZoom: 3,
            zoomSnap: 0.25,
            attributionControl: false
        });

        const bounds = [[0, 0], [h, w]];
        L.imageOverlay(data.image, bounds).addTo(map);
        map.fitBounds(bounds);
        map.setMaxBounds([[-h * 0.25, -w * 0.25], [h * 1.25, w * 1.25]]);

        const api = {
            map: map,
            data: data,
            toLatLng: (x, y) => L.latLng(h - y, x),
            toPixels: (latlng) => ({ x: Math.round(latlng.lng * 10) / 10, y: Math.round((h - latlng.lat) * 10) / 10 }),
            layers: {
                areas: L.layerGroup(),
                landmarks: L.layerGroup(),
                pins: L.layerGroup()
            }
        };

        (data.areas || []).forEach(area => {
            const rings = area.rings.map(ring => ring.map(p => api.toLatLng(p[0], p[1])));
            L.polygon(rings, {
                color: area.colour, weight: 2, opacity: 0.9, fillOpacity: 0.08, interactive: false
            }).bindTooltip(area.name, { sticky: true }).addTo(api.layers.areas);
        });

        (data.landmarks || []).forEach(landmark => {
            L.marker(api.toLatLng(landmark.x, landmark.y), {
                interactive: true,
                icon: L.divIcon({ className: 'landmark-icon', html: '▲', iconSize: [18, 18] })
            }).bindTooltip(landmark.name + (landmark.category ? ' · ' + landmark.category : ''))
                .addTo(api.layers.landmarks);
        });

        api.layers.areas.addTo(map);
        api.layers.landmarks.addTo(map);
        api.layers.pins.addTo(map);

        if (options.toggles !== false) {
            bindToggles(api);
        }

        return api;
    };

    function bindToggles(api) {
        document.querySelectorAll('[data-layer]').forEach(box => {
            const layer = api.layers[box.dataset.layer];
            if (!layer) return;
            box.addEventListener('change', () => {
                box.checked ? layer.addTo(api.map) : api.map.removeLayer(layer);
            });
        });
    }

    CseLog.entryMarker = function (api, entry) {
        const colour = entry.age_level === 'red' ? '#d93a2b'
            : entry.age_level === 'amber' ? '#e8871a'
                : (entry.colour || '#2b7fd9');

        const marker = L.marker(api.toLatLng(entry.x, entry.y), {
            icon: L.divIcon({
                className: '',
                html: '<div class="pin-marker" style="background:' + colour + '"></div>',
                iconSize: [16, 16],
                iconAnchor: [8, 8]
            })
        });

        marker.bindTooltip(entry.label + ' · ' + entry.age_human, {
            permanent: true, direction: 'right', offset: [10, 0], className: 'pin-label'
        });

        marker.bindPopup(
            '<b>' + escapeHtml(entry.label) + '</b><br>' +
            escapeHtml(entry.work_type) + ' · open ' + entry.age_human + '<br>' +
            (entry.notes ? escapeHtml(entry.notes) + '<br>' : '') +
            (entry.reported_by ? 'Radio: ' + escapeHtml(entry.reported_by) + '<br>' : '') +
            '<a href="/entries/' + entry.id + '">Open entry #' + entry.id + '</a>'
        );

        return marker;
    };

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }
    CseLog.escapeHtml = escapeHtml;

    /* ── Location type-ahead ────────────────────────────────────────────── */

    CseLog.combo = function (input, list, hidden, onSelect) {
        let items = [];
        let cursor = -1;
        let timer = null;

        function render() {
            list.innerHTML = '';
            items.forEach((item, i) => {
                const row = document.createElement('div');
                row.className = i === cursor ? 'active' : '';
                row.innerHTML = '<div>' + escapeHtml(item.name) + (item.verified ? '' : ' <span class="sub">(unverified)</span>') +
                    '</div><div class="sub">' + escapeHtml(item.area || 'No area') + ' · used ' + item.usage_count + '×</div>';
                row.addEventListener('mousedown', e => { e.preventDefault(); choose(i); });
                list.appendChild(row);
            });
            list.hidden = items.length === 0;
        }

        function choose(i) {
            const item = items[i];
            if (!item) return;
            input.value = item.name;
            hidden.value = item.id;
            list.hidden = true;
            if (onSelect) onSelect(item);
        }

        function search() {
            CseLog.get('/api/locations?q=' + encodeURIComponent(input.value)).then(rows => {
                items = rows;
                cursor = -1;
                render();
            });
        }

        input.addEventListener('input', () => {
            hidden.value = '';
            clearTimeout(timer);
            timer = setTimeout(search, 120);
        });
        input.addEventListener('focus', search);
        input.addEventListener('blur', () => setTimeout(() => { list.hidden = true; }, 120));
        input.addEventListener('keydown', e => {
            if (list.hidden) return;
            if (e.key === 'ArrowDown') { cursor = Math.min(cursor + 1, items.length - 1); render(); e.preventDefault(); }
            if (e.key === 'ArrowUp') { cursor = Math.max(cursor - 1, 0); render(); e.preventDefault(); }
            if (e.key === 'Enter' && cursor >= 0) { choose(cursor); e.preventDefault(); }
            if (e.key === 'Escape') { list.hidden = true; }
        });
    };
}());
