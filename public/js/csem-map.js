/* CSEM map: Leaflet on CRS.Simple where 1 unit = 1 m of MGA50 (EPSG:28350).
 * latlng = [-northing, easting] (Simple's y grows downward, so northing is negated).
 * Layers come from the sitemap manifest (/api/layers); nothing here knows about lat/lng. */
window.CsemMap = (function () {
  const toLL = (e, n) => L.latLng(-Number(n), Number(e));
  const fromLL = ll => ({ e: ll.lng, n: -ll.lat });
  const Z = { imagery: 10, prints: 20, plan: 30 };

  function hostFor(el) {
    return typeof el === 'string' ? document.getElementById(el) : el;
  }

  function notify(host, type, message, error = null) {
    if (!host) return;
    const detail = { message, error: error ? String(error.message || error) : null };
    host.dispatchEvent(new CustomEvent(`csem:${type}`, { detail }));

    if ((type === 'warning' || type === 'error') && window.csemReportError) {
      window.csemReportError(`map-${type}`, message, {
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
    const host = hostFor(el);
    if (!host) throw new Error('Map container was not found.');
    host.dataset.csemMapState = 'loading';
    host.dataset.csemBasemap = 'loading';

    if (!window.L) {
      host.dataset.csemMapState = 'error';
      throw new Error('Leaflet did not load. Check network/CDN access.');
    }

    const m = opts.manifest || await fetchJson('/api/layers', 'Map manifest');
    if (!m || !Array.isArray(m.rasters) || !m.rasters.length || !validExtent(m.extent)) {
      host.dataset.csemMapState = 'error';
      throw new Error('Map manifest does not contain usable raster data.');
    }

    const preferred = m.rasters.find(r => r.id && r.id.startsWith('site-cf') && r.default_visible)
      || m.rasters.find(r => r.default_visible)
      || m.rasters[0];

    if (!preferred || !validExtent(preferred.extent)) {
      host.dataset.csemMapState = 'error';
      throw new Error('No usable default raster was found in the map manifest.');
    }

    const ext = m.extent;
    const map = L.map(host, {
      crs: L.CRS.Simple,
      minZoom: -5,
      maxZoom: 4,
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
        className: r.group === 'imagery' && !String(r.id || '').startsWith('basemap') ? 'csem-nodata-black' : '',
      });

      rasterById.set(r.id, ov);
      ov.on('error', () => {
        const primary = r.id === preferred.id;
        if (primary) host.dataset.csemBasemap = 'error';
        notify(host, primary ? 'error' : 'warning',
          primary
            ? 'Map imagery could not be loaded.'
            : `Map layer could not be loaded: ${r.title || r.id}`);
      });
      if (r.id === preferred.id) {
        ov.on('load', () => {
          host.dataset.csemBasemap = 'loaded';
          notify(host, 'ready', 'Map imagery loaded.');
        });
      }

      if (r.default_visible) ov.addTo(map);
      if (r.group === 'plan') plan.push(ov);
      overlays[r.title || r.id] = ov;
    }

    const preferredOverlay = rasterById.get(preferred.id);
    if (!preferredOverlay) {
      host.dataset.csemMapState = 'error';
      throw new Error('The preferred raster could not be created.');
    }
    if (!map.hasLayer(preferredOverlay)) preferredOverlay.addTo(map);

    // Critical ordering: position the viewport before any optional API request can fail.
    const preferredBounds = L.latLngBounds(
      toLL(preferred.extent.xmin, preferred.extent.ymin),
      toLL(preferred.extent.xmax, preferred.extent.ymax)
    );
    map.fitBounds(preferredBounds);
    host.dataset.csemMapState = 'basemap-positioned';
    requestAnimationFrame(() => map.invalidateSize({ pan: false }));

    // Optional layers exist immediately so the basemap remains usable if their APIs fail.
    const areas = L.geoJSON(null, {
      coordsToLatLng: c => toLL(c[0], c[1]),
      style: f => ({
        color: f.properties.colour || '#fff',
        weight: f.properties.kind === 'infrastructure' ? 1 : 2,
        fill: f.properties.kind === 'infrastructure',
        fillOpacity: 0.05,
      }),
      onEachFeature: (f, l) => l.bindTooltip(f.properties.name, { sticky: true, direction: 'top' }),
    });
    if (opts.areas !== false) areas.addTo(map);
    overlays['Areas & structures'] = areas;

    const landmarks = L.layerGroup();
    if (opts.landmarks !== false) landmarks.addTo(map);
    overlays['Landmark labels'] = landmarks;

    const pins = L.layerGroup().addTo(map);
    overlays['Open entries'] = pins;

    if (opts.layerControl !== false) {
      L.control.layers(null, overlays, { collapsed: opts.collapsed ?? true }).addTo(map);
    }

    const api = {
      map,
      pins,
      overlays,
      toLL,
      fromLL,
      manifest: m,
      setPlanOpacity: v => plan.forEach(l => l.setOpacity(v)),
      focus: (e, n, zoom = 1) => map.setView(toLL(e, n), zoom),
      optionalReady: null,
    };

    // Useful for diagnostics and browser acceptance tests; no application state depends on it.
    host._csemMapApi = api;

    const optionalLoads = [];

    if (opts.areas !== false) {
      optionalLoads.push(
        fetchJson('/api/areas', 'Site areas')
          .then(gj => areas.addData(gj))
          .catch(error => {
            console.error('CSEM map: areas unavailable', error);
            notify(host, 'warning', 'Map loaded, but site areas could not be loaded.', error);
            throw error;
          })
      );
    }

    if (opts.landmarks !== false) {
      optionalLoads.push(
        fetchJson('/api/landmarks', 'Landmarks')
          .then(rows => {
            for (const lm of rows) {
              L.circleMarker(toLL(lm.easting, lm.northing), {
                radius: 3,
                color: '#fff',
                weight: 1,
                fillColor: '#ff5f1f',
                fillOpacity: 1,
              }).bindTooltip(lm.name, { direction: 'top' }).addTo(landmarks);
            }
          })
          .catch(error => {
            console.error('CSEM map: landmarks unavailable', error);
            notify(host, 'warning', 'Map loaded, but landmark labels are unavailable.', error);
            throw error;
          })
      );
    }

    api.optionalReady = Promise.allSettled(optionalLoads);
    return api;
  }

  /* Pin for an open entry: coloured by work type, ring by age band, dashed if ad-hoc location. */
  function entryIcon(en) {
    const ring = en.band === 'red' ? '#ef4444' : en.band === 'amber' ? '#f59e0b' : '#ffffff';
    const dash = en.location_id ? '' : 'stroke-dasharray:3 2;';
    return L.divIcon({
      className: 'csem-pin',
      iconSize: [30, 42],
      iconAnchor: [15, 40],
      popupAnchor: [0, -36],
      html: `<svg width="30" height="42" viewBox="0 0 30 42"><path d="M15 41 C15 41 2 24 2 15 A13 13 0 0 1 28 15 C28 24 15 41 15 41Z" fill="${en.colour}" stroke="${ring}" stroke-width="3" style="${dash}"/><circle cx="15" cy="15" r="5" fill="#fff"/></svg>`,
    });
  }

  return { create, entryIcon, toLL, fromLL, fetchJson };
})();