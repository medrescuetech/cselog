/* HwrtDraw: a simple drawing overlay for HwrtMap.
 * Line / polygon / point tools with an erase mode and per-browser persistence.
 * Call HwrtDraw.attach(api) where api is the object returned by HwrtMap.create(). */
window.HwrtDraw = (function () {
  const KEY = 'hwrt-draw-v1';

  function ensureStyles() {
    if (document.getElementById('hwrt-draw-styles')) return;
    const s = document.createElement('style');
    s.id = 'hwrt-draw-styles';
    s.textContent = `
      .hwrt-draw-ctl { background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 4px;
        display: flex; flex-direction: column; gap: 4px; }
      .hwrt-draw-ctl button { background: #0f172a; color: #e2e8f0; border: 1px solid #475569;
        border-radius: 6px; padding: 4px 8px; font-size: 12px; cursor: pointer; text-align: left; }
      .hwrt-draw-ctl button.hwrt-draw-active { background: #b45309; border-color: #f59e0b; }
      .hwrt-draw-ctl input[type=color] { width: 100%; height: 22px; border: none; background: none; padding: 0; cursor: pointer; }
    `;
    document.head.appendChild(s);
  }

  function load() {
    try { return JSON.parse(localStorage.getItem(KEY)) || []; } catch { return []; }
  }

  function save(features) {
    try { localStorage.setItem(KEY, JSON.stringify(features)); } catch { /* storage unavailable */ }
  }

  function attach(api) {
    ensureStyles();
    const { map, toLL, fromLL } = api;
    const layer = L.layerGroup().addTo(map);
    const features = load();
    let mode = null;
    let draft = null;
    let colour = '#f8fafc';

    function render(f) {
      const opts = { color: f.colour, weight: 3, fillOpacity: 0.15 };
      const mk = f.kind === 'point'
        ? L.circleMarker(toLL(f.e, f.n), { ...opts, radius: 6, fillOpacity: 0.9 })
        : f.kind === 'polygon'
          ? L.polygon(f.pts.map(([e, n]) => toLL(e, n)), opts)
          : L.polyline(f.pts.map(([e, n]) => toLL(e, n)), opts);
      mk._drawFeature = f;
      mk.on('click', ev => {
        if (mode !== 'erase') return;
        L.DomEvent.stop(ev);
        mk.remove();
        const i = features.indexOf(f);
        if (i >= 0) features.splice(i, 1);
        save(features);
      });
      mk.addTo(layer);
    }

    function addFeature(f) {
      features.push(f);
      save(features);
      render(f);
    }

    function finishDraft() {
      if (!draft) return;
      const min = draft.kind === 'polygon' ? 3 : 2;
      if (draft.pts.length >= min) addFeature({ kind: draft.kind, pts: draft.pts, colour });
      draft.layer.remove();
      draft = null;
      map.doubleClickZoom.enable();
    }

    function setMode(m) {
      if (draft) { draft.layer.remove(); draft = null; }
      mode = m;
      map.getContainer().style.cursor = m ? 'crosshair' : '';
      map.doubleClickZoom[m ? 'disable' : 'enable']();
      ctlEl.querySelectorAll('button[data-mode]').forEach(b =>
        b.classList.toggle('hwrt-draw-active', b.dataset.mode === m));
    }

    map.on('click', e => {
      if (!mode || mode === 'erase' || mode === 'point') return;
      const { e: E, n: N } = fromLL(e.latlng);
      if (!draft) {
        draft = { kind: mode, pts: [[E, N]] };
        draft.layer = (mode === 'polygon' ? L.polygon([], { color: colour, dashArray: '4' }) : L.polyline([], { color: colour, dashArray: '4' })).addTo(map);
      }
      draft.pts.push([E, N]);
      draft.layer.setLatLngs(draft.pts.map(([x, y]) => toLL(x, y)));
    });
    map.on('dblclick', () => finishDraft());
    map.on('click', e => {
      if (mode === 'point') {
        const { e: E, n: N } = fromLL(e.latlng);
        addFeature({ kind: 'point', e: E, n: N, colour });
        setMode(null);
      }
    });

    const ctl = L.control({ position: 'topleft' });
    const ctlEl = L.DomUtil.create('div', 'hwrt-draw-ctl');
    ctl.onAdd = () => ctlEl;
    ctlEl.innerHTML = `
      <button type="button" data-mode="line">— Line</button>
      <button type="button" data-mode="polygon">⬠ Area</button>
      <button type="button" data-mode="point">● Point</button>
      <button type="button" data-mode="erase">✕ Erase</button>
      <input type="color" value="${colour}" title="Draw colour">
      <button type="button" data-clear>Clear all</button>`;
    L.DomEvent.disableClickPropagation(ctlEl);
    L.DomEvent.disableScrollPropagation(ctlEl);
    ctlEl.querySelectorAll('button[data-mode]').forEach(b =>
      b.addEventListener('click', () => setMode(mode === b.dataset.mode ? null : b.dataset.mode)));
    ctlEl.querySelector('input[type=color]').addEventListener('input', ev => { colour = ev.target.value; });
    ctlEl.querySelector('button[data-clear]').addEventListener('click', () => {
      features.length = 0;
      save(features);
      layer.clearLayers();
      setMode(null);
    });
    ctl.addTo(map);

    features.forEach(render);

    return { layer, features };
  }

  return { attach };
})();
