/* HWRT map: Leaflet on CRS.Simple where 1 unit = 1 m of MGA50 (EPSG:28350).
 * latlng = [northing, easting]; CRS.Simple's transformation (1,0,-1,0) already maps lat up the screen.
 * Layers come from the sitemap manifest (/api/layers); nothing here knows about lat/lng. */
window.HwrtMap = (function () {
  const toLL = (e, n) => L.latLng(Number(n), Number(e));
  const fromLL = ll => ({ e: ll.lng, n: ll.lat });
  const Z = { imagery: 10, prints: 20, plan: 30 };

  /* Point-of-interest dot styling, keyed on landmark category. */
  const POI_TYPES = {
    'ert-pickup': { label: 'ERT Pickup Point', bg: '#2563eb', glyph: 'ERT', small: true },
    'aed': { label: 'AED', bg: '#dc2626', glyph: '✚' },
    'first-aid': { label: 'First Aid Kit', bg: '#16a34a', glyph: '✚' },
    'lv-rescue': { label: 'LV Rescue Kit', bg: '#eab308', glyph: 'LV', small: true },
    'muster': { label: 'Muster Point', bg: '#22c55e', glyph: 'M' },
    'obstruction': { label: 'Obstruction', bg: '#f97316', glyph: '!' },
    'hydration': { label: 'Hydration Station', bg: '#0284c7', glyph: 'H' },
    'gas-detector': { label: 'Gas Detector', bg: '#14b8a6', glyph: 'G' },
    'works': { label: 'Works', bg: '#a855f7', glyph: 'W' },
    'risk': { label: 'Risk / hazard', bg: '#ef4444', glyph: '!' },
    'other': { label: 'Other', bg: '#ff5f1f', glyph: '•' },
  };

  function ensureStyles() {
    if (document.getElementById('hwrt-map-styles')) return;
    const s = document.createElement('style');
    s.id = 'hwrt-map-styles';
    s.textContent = `
      .hwrt-road-label, .hwrt-ga-label { box-shadow: none; border: none; }
      .hwrt-road-label::before, .hwrt-ga-label::before { display: none; }
      .hwrt-road-label { background: rgba(226,232,240,.9); color: #0f172a; font-weight: 800; font-size: 12px; padding: 1px 6px; }
      .hwrt-label-hit div { pointer-events: auto; cursor: move; }
      .hwrt-ga-label { background: rgba(15,23,42,.82); color: #d9f99d; font-weight: 800; font-size: 13px;
        padding: 1px 6px; border-radius: 3px; white-space: nowrap;
        text-shadow: 0 0 4px #000, 0 0 2px #000, 0 1px 2px #000; }
      .hwrt-poi { display: flex; align-items: center; justify-content: center; width: 18px; height: 18px;
        border-radius: 50%; border: 2px solid #0f172a; color: #fff; font-weight: 800; font-size: 10px;
        box-shadow: 0 0 3px rgba(0,0,0,.6); }
    `;
    document.head.appendChild(s);
  }

  function landmarkIcon(lm) {
    const t = POI_TYPES[String(lm.category || '').toLowerCase()] || POI_TYPES.other;
    return L.divIcon({
      className: '',
      iconSize: [18, 18],
      iconAnchor: [9, 9],
      popupAnchor: [0, -10],
      html: `<div class="hwrt-poi" style="background:${t.bg};${t.small ? 'font-size:7px;' : ''}">${t.glyph}</div>`,
    });
  }

  const escHtml = v => String(v ?? '').replace(/[&<>"']/g, ch => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
  })[ch]);

  function hostFor(el) {
    return typeof el === 'string' ? document.getElementById(el) : el;
  }

  function notify(host, type, message, error = null) {
    if (!host) return;
    const detail = { message, error: error ? String(error.message || error) : null };
    host.dispatchEvent(new CustomEvent(`hwrt:${type}`, { detail }));

    if ((type === 'warning' || type === 'error') && window.hwrtReportError) {
      window.hwrtReportError(`map-${type}`, message, {
        stack: error?.stack || null,
        context: {
          map_element: host.id || null,
          detail_error: detail.error,
        },
      });
    }
  }

  async function fetchJson(url, label = 'Request') {
    const response = await fetch(url, { headers: { Accept: 'application/json' } });
    if (!response.ok) {
      throw new Error(`${label} failed (HTTP ${response.status})`);
    }
    try {
      return await response.json();
    } catch (error) {
      throw new Error(`${label} returned invalid JSON`);
    }
  }

  function validExtent(extent) {
    return extent
      && Number.isFinite(Number(extent.xmin))
      && Number.isFinite(Number(extent.xmax))
      && Number.isFinite(Number(extent.ymin))
      && Number.isFinite(Number(extent.ymax))
      && Number(extent.xmax) > Number(extent.xmin)
      && Number(extent.ymax) > Number(extent.ymin);
  }

  async function create(el, opts = {}) {
    ensureStyles();
    const host = hostFor(el);
    if (!host) throw new Error('Map container was not found.');
    host.dataset.hwrtMapState = 'loading';
    host.dataset.hwrtBasemap = 'loading';

    if (!window.L) {
      host.dataset.hwrtMapState = 'error';
      throw new Error('Leaflet did not load. Check network/CDN access.');
    }

    const m = opts.manifest || await fetchJson('/api/layers', 'Map manifest');
    const styling = m.styling || {};
    if (!m || !Array.isArray(m.rasters) || !m.rasters.length || !validExtent(m.extent)) {
      host.dataset.hwrtMapState = 'error';
      throw new Error('Map manifest does not contain usable raster data.');
    }

    const preferred = m.rasters.find(r => r.id && r.id.startsWith('site-cf') && r.default_visible)
      || m.rasters.find(r => r.default_visible)
      || m.rasters[0];

    if (!preferred || !validExtent(preferred.extent)) {
      host.dataset.hwrtMapState = 'error';
      throw new Error('No usable default raster was found in the map manifest.');
    }

    const ext = m.extent;
    const map = L.map(host, {
      crs: L.CRS.Simple,
      minZoom: -5,
      maxZoom: 6,
      zoomSnap: 0.25,
      attributionControl: false,
      maxBounds: L.latLngBounds(
        toLL(Number(ext.xmin) - 300, Number(ext.ymin) - 300),
        toLL(Number(ext.xmax) + 300, Number(ext.ymax) + 300)
      ),
      maxBoundsViscosity: 0.8,
      ...(opts.leaflet || {}),
    });

    const overlays = {};
    const plan = [];
    const rasterById = new Map();

    for (const r of m.rasters) {
      if (opts.skipPrints && r.group === 'prints') continue;
      if (!r.url || !validExtent(r.extent)) {
        notify(host, 'warning', `Skipped invalid map layer: ${r.title || r.id || 'unnamed layer'}`);
        continue;
      }

      const b = L.latLngBounds(
        toLL(r.extent.xmin, r.extent.ymin),
        toLL(r.extent.xmax, r.extent.ymax)
      );
      const ov = L.imageOverlay(r.url, b, {
        opacity: Number.isFinite(Number(r.opacity)) ? Number(r.opacity) : 1,
        zIndex: Z[r.group] || 25,
        className: r.group === 'imagery' && !String(r.id || '').startsWith('basemap') ? 'hwrt-nodata-black' : '',
      });

      rasterById.set(r.id, ov);
      ov.on('error', () => {
        const primary = r.id === preferred.id;
        if (primary) host.dataset.hwrtBasemap = 'error';
        notify(host, primary ? 'error' : 'warning',
          primary
            ? 'Map imagery could not be loaded.'
            : `Map layer could not be loaded: ${r.title || r.id}`);
      });
      if (r.id === preferred.id) {
        ov.on('load', () => {
          host.dataset.hwrtBasemap = 'loaded';
          notify(host, 'ready', 'Map imagery loaded.');
        });
      }

      if (r.default_visible) ov.addTo(map);
      if (r.group === 'plan') plan.push(ov);
      overlays[r.title || r.id] = ov;
    }

    const preferredOverlay = rasterById.get(preferred.id);
    if (!preferredOverlay) {
      host.dataset.hwrtMapState = 'error';
      throw new Error('The preferred raster could not be created.');
    }
    if (!map.hasLayer(preferredOverlay)) preferredOverlay.addTo(map);

    // Critical ordering: position the viewport before any optional API request can fail.
    const preferredBounds = L.latLngBounds(
      toLL(preferred.extent.xmin, preferred.extent.ymin),
      toLL(preferred.extent.xmax, preferred.extent.ymax)
    );
    map.fitBounds(preferredBounds);
    host.dataset.hwrtMapState = 'basemap-positioned';
    requestAnimationFrame(() => map.invalidateSize({ pan: false }));

    // Optional layers exist immediately so the basemap remains usable if their APIs fail.
    const coords = { coordsToLatLng: c => toLL(c[0], c[1]) };
    const areaStyle = f => ({
      color: (f.properties.kind === 'road' && styling.road) || (f.properties.kind === 'ga' && styling.ga) || f.properties.colour || '#fff',
      weight: f.properties.kind === 'infrastructure' ? 1 : (f.properties.kind === 'ga' ? 3 : 2),
      fill: f.properties.kind === 'infrastructure',
      fillOpacity: f.properties.kind === 'ga' ? 0 : 0.05,
    });
    const hoverTip = (f, l) => l.bindTooltip(f.properties.name, { sticky: true, direction: 'top' });

    /* Per-browser label positions for roads and GAs; when unset the label sits on its
     * original anchor (feature centre). Dragging a label moves only the label — the
     * underlying feature never moves. */
    const LABEL_KEY = 'hwrt-label-pos-v1';
    const labelPos = () => { try { return JSON.parse(localStorage.getItem(LABEL_KEY)) || {}; } catch { return {}; } };
    const draggableLabel = (key, ll, name, cls, extraCss = '') => {
      const saved = labelPos()[key];
      const mk = L.marker(saved ? toLL(saved[0], saved[1]) : ll, {
        icon: L.divIcon({
          className: 'hwrt-label-hit', iconSize: [0, 0], iconAnchor: [0, 0],
          html: `<div class="${cls}" style="transform:translate(-50%,-50%);${extraCss}">${escHtml(name)}</div>`,
        }),
        draggable: true, keyboard: false, zIndexOffset: 600,
      });
      mk.on('dragend', () => {
        const { e, n } = fromLL(mk.getLatLng());
        const pos = labelPos();
        pos[key] = [e, n];
        try { localStorage.setItem(LABEL_KEY, JSON.stringify(pos)); } catch { /* storage unavailable */ }
      });
      return mk;
    };

    // Draggable centred labels for roads and GAs; other kinds keep hover tooltips.
    const areaLabel = (f, l) => {
      const kind = f.properties.kind;
      if (kind === 'road' || kind === 'ga') {
        draggableLabel(`${kind}:${f.properties.name}`, l.getBounds().getCenter(), f.properties.name,
          kind === 'road' ? 'hwrt-road-label' : 'hwrt-ga-label',
          kind === 'ga' ? `color:${styling.ga || f.properties.colour || '#d9f99d'}` : '').addTo(areas);
      } else {
        hoverTip(f, l);
      }
    };

    const areas = L.geoJSON(null, {
      ...coords, style: areaStyle, onEachFeature: areaLabel,
      filter: f => f.properties.kind !== 'infrastructure',
    });
    if (opts.areas !== false) areas.addTo(map);
    overlays['Areas & structures'] = areas;

    const infraAreas = L.geoJSON(null, {
      ...coords, style: areaStyle, onEachFeature: hoverTip,
      filter: f => f.properties.kind === 'infrastructure',
    });
    overlays['Named infrastructure'] = infraAreas;

    const pois = L.layerGroup();
    if (opts.landmarks !== false) pois.addTo(map);
    overlays['Points of interest'] = pois;

    const infraLandmarks = L.layerGroup();
    overlays['Infrastructure labels'] = infraLandmarks;

    const pins = L.layerGroup().addTo(map);
    overlays['Open entries'] = pins;

    if (opts.layerControl !== false) {
      L.control.layers(null, overlays, { collapsed: opts.collapsed ?? true }).addTo(map);
    }

    // Road and GA landmarks are redundant — those names are drawn on the polygons.
    function addLandmark(lm) {
      const cat = String(lm.category || '').toLowerCase();
      if (cat === 'road' || cat === 'ga') return null;
      const target = cat === 'infrastructure' ? infraLandmarks : pois;
      const mk = L.marker(toLL(lm.easting, lm.northing), { icon: landmarkIcon(lm), zIndexOffset: 500 })
        .bindTooltip(escHtml(lm.name), { direction: 'top' });
      if (opts.poiDelete && lm.id && ['user', 'manual'].includes(String(lm.source || ''))) {
        mk.bindPopup(`<div><b>${escHtml(lm.name)}</b><br><button type="button" data-del style="margin-top:6px;background:#991b1b;color:#fff;border:none;border-radius:6px;padding:4px 10px;cursor:pointer">Remove point</button></div>`);
        mk.on('popupopen', e => {
          e.popup.getElement().querySelector('[data-del]')?.addEventListener('click', async () => {
            const r = await fetch(`/api/landmarks/${lm.id}`, {
              method: 'DELETE',
              headers: { 'X-CSRF-TOKEN': window.csrf, Accept: 'application/json' },
            });
            if (r.ok) mk.remove();
          });
        });
      }
      mk.addTo(target);
      return mk;
    }

    const api = {
      map,
      pins,
      pois,
      overlays,
      addLandmark,
      toLL,
      fromLL,
      manifest: m,
      setPlanOpacity: v => plan.forEach(l => l.setOpacity(v)),
      focus: (e, n, zoom = 1) => map.setView(toLL(e, n), zoom),
      optionalReady: null,
    };

    // Useful for diagnostics and browser acceptance tests; no application state depends on it.
    host._hwrtMapApi = api;

    const optionalLoads = [];

    if (opts.areas !== false) {
      optionalLoads.push(
        fetchJson('/api/areas', 'Site areas')
          .then(gj => { areas.addData(gj); infraAreas.addData(gj); })
          .catch(error => {
            console.error('HWRT map: areas unavailable', error);
            notify(host, 'warning', 'Map loaded, but site areas could not be loaded.', error);
            throw error;
          })
      );
    }

    if (opts.landmarks !== false) {
      optionalLoads.push(
        fetchJson('/api/landmarks', 'Landmarks')
          .then(rows => { for (const lm of rows) addLandmark(lm); })
          .catch(error => {
            console.error('HWRT map: landmarks unavailable', error);
            notify(host, 'warning', 'Map loaded, but landmark labels are unavailable.', error);
            throw error;
          })
      );
    }

    api.optionalReady = Promise.allSettled(optionalLoads);
    return api;
  }

  /* Pin for an open entry: coloured by work type, ring by age band, dashed if ad-hoc location.
   * `size` is the pin width in px — smaller pins make dense areas easier to read. */
  function entryIcon(en, size = 24) {
    const w = size;
    const h = Math.round(size * 1.4);
    const ring = en.band === 'red' ? '#ef4444' : en.band === 'amber' ? '#f59e0b' : '#ffffff';
    const dash = en.location_id ? '' : 'stroke-dasharray:3 2;';
    return L.divIcon({
      className: 'hwrt-pin',
      iconSize: [w, h],
      iconAnchor: [w / 2, h - 2],
      popupAnchor: [0, -h + 4],
      html: `<svg width="${w}" height="${h}" viewBox="0 0 30 42"><path d="M15 41 C15 41 2 24 2 15 A13 13 0 0 1 28 15 C28 24 15 41 15 41Z" fill="${en.colour}" stroke="${ring}" stroke-width="3" style="${dash}"/><circle cx="15" cy="15" r="5" fill="#fff"/></svg>`,
    });
  }

  return { create, entryIcon, landmarkIcon, POI_TYPES, toLL, fromLL, fetchJson };
})();