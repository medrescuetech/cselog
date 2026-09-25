/* HRWT map: Leaflet on CRS.Simple where 1 unit = 1 m of MGA50 (EPSG:28350).
 * latlng = [-northing, easting] (Simple's y grows downward, so northing is negated).
 * Layers come from the sitemap manifest (/api/layers); nothing here knows about lat/lng. */
window.HrwtMap = (function () {
  const toLL = (e, n) => L.latLng(-n, e);
  const fromLL = ll => ({ e: ll.lng, n: -ll.lat });
  const Z = { imagery: 10, prints: 20, plan: 30 };

  async function create(el, opts = {}) {
    const lRes = await fetch('/api/layers');
    if (!lRes.ok) throw new Error(`Failed to load layers manifest: ${lRes.status}`);
    const m = await lRes.json();
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
      const ov = L.imageOverlay(r.url, b, { opacity: r.opacity, zIndex: Z[r.group] || 25, className: r.group === 'imagery' && !r.id.startsWith('basemap') ? 'hrwt-nodata-black' : '' });
      if (r.default_visible) ov.addTo(map);
      if (r.group === 'plan') plan.push(ov);
      overlays[r.title] = ov;
    }

    // Fit bounds immediately after adding rasters so the basemap is rendered in viewport before optional fetch calls
    const cf = m.rasters.find(r => r.id.startsWith('site-cf') && r.default_visible) || m.rasters[0];
    if (cf) {
      map.fitBounds(L.latLngBounds(toLL(cf.extent.xmin, cf.extent.ymin), toLL(cf.extent.xmax, cf.extent.ymax)));
    }

    // Areas DB layer (optional, handled resiliently)
    if (opts.areas !== false) {
      try {
        const aRes = await fetch('/api/areas');
        if (aRes.ok) {
          const aData = await aRes.json();
          const areas = L.geoJSON(aData, {
            coordsToLatLng: c => toLL(c[0], c[1]),
            style: f => ({ color: f.properties.colour || '#fff', weight: f.properties.kind === 'infrastructure' ? 1 : 2, fill: f.properties.kind === 'infrastructure', fillOpacity: 0.05 }),
            onEachFeature: (f, l) => l.bindTooltip(f.properties.name, { sticky: true, direction: 'top' }),
          });
          areas.addTo(map);
          overlays['Areas & structures'] = areas;
        }
      } catch (err) {
        console.warn('Could not load areas overlay:', err);
      }
    }

    // Landmarks DB layer (optional, handled resiliently)
    try {
      const lmRes = await fetch('/api/landmarks');
      if (lmRes.ok) {
        const lmData = await lmRes.json();
        const landmarks = L.layerGroup();
        for (const lm of lmData) {
          L.circleMarker(toLL(lm.easting, lm.northing), { radius: 3, color: '#fff', weight: 1, fillColor: '#ff5f1f', fillOpacity: 1 })
            .bindTooltip(lm.name, { direction: 'top' }).addTo(landmarks);
        }
        overlays['Landmark labels'] = landmarks;
      }
    } catch (err) {
      console.warn('Could not load landmarks overlay:', err);
    }

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
      className: 'hrwt-pin', iconSize: [30, 42], iconAnchor: [15, 40], popupAnchor: [0, -36],
      html: `<svg width="30" height="42" viewBox="0 0 30 42"><path d="M15 41 C15 41 2 24 2 15 A13 13 0 0 1 28 15 C28 24 15 41 15 41Z" fill="${en.colour}" stroke="${ring}" stroke-width="3" style="${dash}"/><circle cx="15" cy="15" r="5" fill="#fff"/></svg>`,
    });
  }

  return { create, entryIcon, toLL, fromLL };
})();
