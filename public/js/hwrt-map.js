/* HWRT map: Leaflet on CRS.Simple where 1 unit = 1 m of MGA50 (EPSG:28350).
 * latlng = [northing, easting]; CRS.Simple's transformation (1,0,-1,0) already maps lat up the screen.
 * Layers come from the sitemap manifest (/api/layers); nothing here knows about lat/lng. */
window.HwrtMap = (function () {
  const toLL = (e, n) => L.latLng(Number(n), Number(e));
  const fromLL = ll => ({ e: ll.lng, n: ll.lat });
  const Z = { imagery: 10, prints: 20, plan: 30 };
  const esc = value => String(value ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
  const title = value => String(value || '').replace(/[_-]+/g, ' ').replace(/\b\w/g, ch => ch.toUpperCase());
  const markerGroupName = row => {
    if (row.marker_type === 'road_label') return 'Road labels';
    if (row.marker_type === 'muster_point') return 'Muster points';
    if (row.marker_type === 'emergency') return 'Emergency markers';
    const category = String(row.category || '').trim().toLowerCase();
    return category && category !== 'landmark' ? title(category) : 'Landmarks';
  };

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

  function validView(view, extent) {
    if (!view) return null;
    const easting = Number(view.easting);
    const northing = Number(view.northing);
    const zoom = Number(view.zoom);
    if (!Number.isFinite(easting) || !Number.isFinite(northing) || !Number.isFinite(zoom)) return null;
    if (validExtent(extent)) {
      const margin = 500;
      if (easting < Number(extent.xmin) - margin || easting > Number(extent.xmax) + margin
        || northing < Number(extent.ymin) - margin || northing > Number(extent.ymax) + margin) return null;
    }
    return { easting, northing, zoom };
  }

  function readStoredView(storageKey, extent) {
    if (!storageKey) return null;
    try {
      return validView(JSON.parse(localStorage.getItem(storageKey) || 'null'), extent);
    } catch {
      return null;
    }
  }

  function writeStoredView(storageKey, view) {
    if (!storageKey) return;
    try {
      localStorage.setItem(storageKey, JSON.stringify(view));
    } catch {
      return;
    }
  }

  async function create(el, opts = {}) {
    const host = hostFor(el);
    if (!host) throw new Error('Map container was not found.');
    host.dataset.hwrtMapState = 'loading';
    host.dataset.hwrtBasemap = 'loading';

    if (!window.L) {
      host.dataset.hwrtMapState = 'error';
      throw new Error('Leaflet did not load. Check network/CDN access.');
    }

    const m = opts.manifest || await fetchJson('/api/layers', 'Map manifest');
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
    const viewStorageKey = opts.viewKey ? `hwrt.map.view.${opts.viewKey}` : null;
    let siteDefaultView = validView(m.default_view, ext);
    map.fitBounds(preferredBounds);
    const initialView = readStoredView(viewStorageKey, ext) || siteDefaultView;
    if (initialView) map.setView(toLL(initialView.easting, initialView.northing), initialView.zoom);
    host.dataset.hwrtMapState = 'basemap-positioned';
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

    const roads = L.geoJSON(null, {
      coordsToLatLng: c => toLL(c[0], c[1]),
      style: f => ({
        color: f.properties.colour || '#e2e8f0',
        weight: 1.5,
        fill: false,
      }),
      onEachFeature: (f, l) => l.bindTooltip(f.properties.name, { sticky: true, direction: 'top' }),
    });
    if (opts.roads !== false) roads.addTo(map);
    overlays['Site C roads'] = roads;

    let layerControl = null;
    const markerGroups = new Map();
    const staticGroup = name => {
      if (!markerGroups.has(name)) {
        const group = L.layerGroup();
        if (opts.landmarks !== false) group.addTo(map);
        markerGroups.set(name, group);
        overlays[name] = group;
        if (layerControl) layerControl.addOverlay(group, name);
      }
      return markerGroups.get(name);
    };
    for (const name of ['Landmarks', 'Road labels', 'Muster points', 'Emergency markers']) staticGroup(name);

    const sectors = L.geoJSON(null, {
      coordsToLatLng: c => toLL(c[0], c[1]),
      style: f => ({
        color: f.properties.colour || '#38bdf8',
        weight: 2,
        fill: true,
        fillOpacity: Number.isFinite(Number(f.properties.fill_opacity)) ? Number(f.properties.fill_opacity) : 0.08,
      }),
      onEachFeature: (f, l) => l.bindTooltip(f.properties.name, { sticky: true, direction: 'top' }),
    });
    if (opts.sectors !== false) sectors.addTo(map);
    overlays['Map sectors'] = sectors;

    const pins = L.layerGroup().addTo(map);
    overlays['Open entries'] = pins;

    if (opts.layerControl !== false) {
      layerControl = L.control.layers(null, overlays, { collapsed: opts.collapsed ?? true }).addTo(map);
    }

    let suppressViewSave = false;
    const currentView = () => {
      const centre = fromLL(map.getCenter());
      return {
        easting: Number(centre.e.toFixed(3)),
        northing: Number(centre.n.toFixed(3)),
        zoom: Number(map.getZoom().toFixed(3)),
      };
    };
    const api = {
      map,
      pins,
      overlays,
      markerGroups,
      sectors,
      roads,
      toLL,
      fromLL,
      manifest: m,
      defaultView: siteDefaultView,
      currentView,
      setDefaultView: view => {
        siteDefaultView = validView(view, ext);
        api.defaultView = siteDefaultView;
      },
      setPlanOpacity: v => plan.forEach(l => l.setOpacity(v)),
      focus: (e, n, zoom = 1) => map.setView(toLL(e, n), zoom),
      home: () => {
        if (viewStorageKey) {
          suppressViewSave = true;
          try {
            localStorage.removeItem(viewStorageKey);
          } catch {
          }
        }
        if (siteDefaultView) map.setView(toLL(siteDefaultView.easting, siteDefaultView.northing), siteDefaultView.zoom);
        else map.fitBounds(preferredBounds);
        setTimeout(() => { suppressViewSave = false; }, 400);
      },
      optionalReady: null,
    };
    if (viewStorageKey) {
      map.on('moveend', () => {
        if (!suppressViewSave) writeStoredView(viewStorageKey, currentView());
      });
    }

    // Useful for diagnostics and browser acceptance tests; no application state depends on it.
    host._hwrtMapApi = api;

    const optionalLoads = [];

    if (opts.areas !== false || opts.roads !== false) {
      optionalLoads.push(
        fetchJson('/api/areas', 'Site areas')
          .then(gj => {
            const features = Array.isArray(gj.features) ? gj.features : [];
            const roadFeatures = features.filter(f => f?.properties?.kind === 'road');
            const otherFeatures = features.filter(f => f?.properties?.kind !== 'road');
            if (opts.areas !== false) areas.addData({ ...gj, features: otherFeatures });
            if (opts.roads !== false) roads.addData({ ...gj, features: roadFeatures });
          })
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
          .then(rows => {
            for (const lm of rows) {
              const colour = /^#[0-9a-f]{6}$/i.test(String(lm.colour || '')) ? lm.colour : '#ff5f1f';
              const point = toLL(lm.easting, lm.northing);
              const group = staticGroup(markerGroupName(lm));
              const marker = L.circleMarker(point, {
                radius: lm.marker_type === 'muster_point' ? 6 : 4,
                color: '#fff',
                weight: 1.5,
                fillColor: colour,
                fillOpacity: 1,
              }).bindTooltip(`${lm.name}${lm.category ? ` · ${title(lm.category)}` : ''}`, { direction: 'top' });
              marker.addTo(group);

              if (lm.label_visible) {
                const rotation = Number(lm.rotation_deg) || 0;
                const labelOffset = lm.marker_type === 'road_label' ? '-50%' : '-135%';
                const icon = L.divIcon({
                  className: '',
                  iconSize: [0, 0],
                  iconAnchor: [0, 0],
                  html: `<div style="transform:translate(-50%,${labelOffset}) rotate(${rotation}deg);transform-origin:center;color:${colour};font:700 12px/1.1 system-ui,sans-serif;text-shadow:0 1px 3px #000,0 0 4px #000;white-space:nowrap;pointer-events:none">${esc(lm.name)}</div>`,
                });
                L.marker(point, { icon, interactive: false }).addTo(group);
              }
            }
          })
          .catch(error => {
            console.error('HWRT map: landmarks unavailable', error);
            notify(host, 'warning', 'Map loaded, but map markers are unavailable.', error);
            throw error;
          })
      );
    }

    if (opts.sectors !== false) {
      optionalLoads.push(
        fetchJson('/api/map-sectors', 'Map sectors')
          .then(gj => sectors.addData(gj))
          .catch(error => {
            console.error('HWRT map: sectors unavailable', error);
            notify(host, 'warning', 'Map loaded, but map sectors are unavailable.', error);
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
      className: 'hwrt-pin',
      iconSize: [30, 42],
      iconAnchor: [15, 40],
      popupAnchor: [0, -36],
      html: `<svg width="30" height="42" viewBox="0 0 30 42"><path d="M15 41 C15 41 2 24 2 15 A13 13 0 0 1 28 15 C28 24 15 41 15 41Z" fill="${en.colour}" stroke="${ring}" stroke-width="3" style="${dash}"/><circle cx="15" cy="15" r="5" fill="#fff"/></svg>`,
    });
  }

  return { create, entryIcon, toLL, fromLL, fetchJson };
})();