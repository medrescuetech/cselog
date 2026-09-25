/* CSEM map: Leaflet on CRS.Simple where 1 unit = 1 m of MGA50 (EPSG:28350).
 * latlng = [-northing, easting] (Simple's y grows downward, so northing is negated).
 * Layers come from the sitemap manifest (/api/layers); nothing here knows about lat/lng. */
window.CsemMap = (function () {
  const toLL = (e, n) => L.latLng(-n, e);
  const fromLL = ll => ({ e: ll.lng, n: -ll.lat });
  const Z = { imagery: 10, prints: 20, plan: 30 };

  function showBanner(el, message) {
    const target = typeof el === 'string' ? document.getElementById(el) : el;
    if (!target) return;
    const div = document.createElement('div');
    div.className = 'csem-map-error-banner';
    div.style.cssText = 'position:absolute;top:12px;left:50%;transform:translateX(-50%);z-index:2000;background:#7f1d1d;color:#fecaca;padding:8px 16px;border-radius:6px;font-size:13px;border:1px solid #b91c1c;box-shadow:0 4px 6px -1px rgba(0,0,0,0.3);';
    div.textContent = message;
    target.appendChild(div);
  }

  async function create(el, opts = {}) {
    let m = opts.manifest;
    if (!m) {
      try {
        const res = await fetch('/api/layers');
        if (!res.ok) throw new Error(`HTTP status ${res.status}`);
        m = await res.json();
      } catch (err) {
        console.error('Failed to load map manifest:', err);
        showBanner(el, 'Map imagery layers could not be loaded. Please check network connection.');
        throw err;
      }
    }

    const ext = m.extent;
    const map = L.map(el, {
      crs: L.CRS.Simple, minZoom: -5, maxZoom: 4, zoomSnap: 0.25, attributionControl: false,
      maxBounds: L.latLngBounds(toLL(ext.xmin - 300, ext.ymin - 300), toLL(ext.xmax + 300, ext.ymax + 300)),
      maxBoundsViscosity: 0.8, ...(opts.leaflet || {}),
    });

    const overlays = {};
    const plan = [];
    for (const r of m.rasters) {
      if (opts.skipPrints && r.group === 'prints') continue;
      const b = L.latLngBounds(toLL(r.extent.xmin, r.extent.ymin), toLL(r.extent.xmax, r.extent.ymax));
      const ov = L.imageOverlay(r.url, b, {
        opacity: r.opacity,
        zIndex: Z[r.group] || 25,
        className: r.group === 'imagery' && !r.id.startsWith('basemap') ? 'csem-nodata-black' : ''
      });
      if (r.default_visible) ov.addTo(map);
      if (r.group === 'plan') plan.push(ov);
      overlays[r.title] = ov;
    }

    // Centring and fitting the map viewport on the base raster IMMEDIATELY
    const cf = m.rasters.find(r => r.id.startsWith('site-cf') && r.default_visible) || m.rasters[0];
    if (cf) {
      map.fitBounds(L.latLngBounds(toLL(cf.extent.xmin, cf.extent.ymin), toLL(cf.extent.xmax, cf.extent.ymax)));
    }

    // Areas & structures from application database
    const areas = L.geoJSON(null, {
      coordsToLatLng: c => toLL(c[0], c[1]),
      style: f => ({ color: f.properties.colour || '#fff', weight: f.properties.kind === 'infrastructure' ? 1 : 2, fill: f.properties.kind === 'infrastructure', fillOpacity: 0.05 }),
      onEachFeature: (f, l) => l.bindTooltip(f.properties.name, { sticky: true, direction: 'top' }),
    });
    if (opts.areas !== false) {
      areas.addTo(map);
      (async () => {
        try {
          const res = await fetch('/api/areas');
          if (res.ok) {
            const data = await res.json();
            areas.addData(data);
          } else {
            console.warn('Could not load site areas:', res.status);
          }
        } catch (e) {
          console.warn('Could not load site areas:', e);
        }
      })();
    }
    overlays['Areas & structures'] = areas;

    // Landmark labels from application database
    const landmarks = L.layerGroup();
    overlays['Landmark labels'] = landmarks;
    (async () => {
      try {
        const res = await fetch('/api/landmarks');
        if (res.ok) {
          const list = await res.json();
          for (const lm of list) {
            L.circleMarker(toLL(lm.easting, lm.northing), { radius: 3, color: '#fff', weight: 1, fillColor: '#ff5f1f', fillOpacity: 1 })
              .bindTooltip(lm.name, { direction: 'top' }).addTo(landmarks);
          }
        } else {
          console.warn('Could not load landmarks:', res.status);
        }
      } catch (e) {
        console.warn('Could not load landmarks:', e);
      }
    })();

    const pins = L.layerGroup().addTo(map);
    overlays['Open entries'] = pins;
    if (opts.layerControl !== false) L.control.layers(null, overlays, { collapsed: opts.collapsed ?? true }).addTo(map);

    return {
      map, pins, overlays, toLL, fromLL, manifest: m,
      setPlanOpacity: v => plan.forEach(l => l.setOpacity(v)),
      focus: (e, n, zoom = 1) => map.setView(toLL(e, n), zoom),
    };
  }

  /* Pin for an open entry: coloured by work type, ring by age band, dashed if ad-hoc location. */
  function entryIcon(en) {
    const ring = en.band === 'red' ? '#ef4444' : en.band === 'amber' ? '#f59e0b' : '#ffffff';
    const dash = en.location_id ? '' : 'stroke-dasharray:3 2;';
    return L.divIcon({
      className: 'csem-pin', iconSize: [30, 42], iconAnchor: [15, 40], popupAnchor: [0, -36],
      html: `<svg width="30" height="42" viewBox="0 0 30 42"><path d="M15 41 C15 41 2 24 2 15 A13 13 0 0 1 28 15 C28 24 15 41 15 41Z" fill="${en.colour}" stroke="${ring}" stroke-width="3" style="${dash}"/><circle cx="15" cy="15" r="5" fill="#fff"/></svg>`,
    });
  }

  return { create, entryIcon, toLL, fromLL };
})();
