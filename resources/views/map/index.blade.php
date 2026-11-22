@extends('layouts.app')
@section('title', 'Map')
@section('main-class', 'relative')
@push('head')
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script src="/js/hwrt-map.js"></script>
<script src="/js/hwrt-draw.js"></script>
<style>
  #map { position: absolute; inset: 0; background: #0f172a; }
  .leaflet-container { font: inherit; }
  .leaflet-control-layers { background: #1e293b; color: #e2e8f0; border: 1px solid #334155; }
  .leaflet-control-layers-toggle { filter: invert(1); }
  .leaflet-popup-content-wrapper, .leaflet-popup-tip { background: #1e293b; color: #e2e8f0; }
</style>
@endpush

@section('content')
<div id="map"></div>

<div id="map-status" class="hidden absolute top-3 left-1/2 -translate-x-1/2 z-[1100] max-w-xl rounded-lg border px-4 py-3 text-sm shadow-xl" role="status" aria-live="polite">
  <span id="map-status-text"></span>
  <button id="map-retry" type="button" class="hidden ml-3 underline font-semibold" onclick="location.reload()">Retry</button>
</div>

<div class="absolute left-3 bottom-3 z-[1000] font-mono text-xs bg-slate-900/90 rounded px-2 py-1" id="coords">E — N —</div>
<div class="absolute right-3 bottom-3 z-[1000] bg-slate-900/90 rounded px-3 py-2 text-xs flex items-center gap-2">
  <label>plan <input id="planop" type="range" min="0" max="1" step="0.05" value="1" class="align-middle w-28"></label>
  <label>pin <input id="pinsize" type="range" min="14" max="42" step="1" value="24" class="align-middle w-20"></label>
  <span id="count" class="ml-2 font-semibold"></span>
</div>

<button id="addpoi" type="button" class="absolute left-14 top-3 z-[1000] rounded-lg bg-slate-900/90 border border-slate-600 px-3 py-2 text-xs font-semibold hover:bg-slate-800">＋ Point</button>

<div id="poi-form" class="hidden absolute left-3 bottom-14 z-[1100] w-72 rounded-lg border border-slate-600 bg-slate-900/95 p-3 text-sm space-y-2">
  <div class="font-semibold">Add map point</div>
  <label class="block text-xs text-slate-400">Type
    <select id="poi-type" class="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-2 py-1.5"></select>
  </label>
  <label class="block text-xs text-slate-400">Name
    <input id="poi-name" maxlength="120" class="mt-1 w-full rounded bg-slate-800 border border-slate-600 px-2 py-1.5">
  </label>
  <div id="poi-coords" class="font-mono text-[11px] text-slate-400"></div>
  <div id="poi-error" class="hidden text-red-400 text-xs"></div>
  <div class="flex gap-2 pt-1">
    <button id="poi-save" type="button" class="flex-1 rounded bg-emerald-600 hover:bg-emerald-500 px-3 py-2 font-semibold">Save</button>
    <button id="poi-cancel" type="button" class="rounded bg-slate-700 hover:bg-slate-600 px-3 py-2">Cancel</button>
  </div>
</div>
@endsection

@push('scripts')
<script>
(async () => {
  const mapEl = document.getElementById('map');
  const statusEl = document.getElementById('map-status');
  const statusText = document.getElementById('map-status-text');
  const retry = document.getElementById('map-retry');
  let warningTimer = null;

  function showNotice(message, fatal = false) {
    clearTimeout(warningTimer);
    statusText.textContent = message;
    statusEl.className = 'absolute top-3 left-1/2 -translate-x-1/2 z-[1100] max-w-xl rounded-lg border px-4 py-3 text-sm shadow-xl '
      + (fatal ? 'bg-red-950/95 border-red-700 text-red-100' : 'bg-amber-950/95 border-amber-700 text-amber-100');
    retry.classList.toggle('hidden', !fatal);
    if (!fatal) warningTimer = setTimeout(() => statusEl.classList.add('hidden'), 8000);
  }

  mapEl.addEventListener('hwrt:warning', e => showNotice(e.detail.message, false));
  mapEl.addEventListener('hwrt:error', e => showNotice(e.detail.message, true));

  try {
    if (!window.L) throw new Error('Leaflet did not load. Check network/CDN access.');

    const cm = await HwrtMap.create('map', { collapsed: false, poiDelete: true });
    const { map, pins, toLL, fromLL } = cm;

    if (window.HwrtDraw) HwrtDraw.attach(cm);

    // --- POI quick-add ---
    const addBtn = document.getElementById('addpoi');
    const poiForm = document.getElementById('poi-form');
    const typeSel = document.getElementById('poi-type');
    const nameEl = document.getElementById('poi-name');
    const errEl = document.getElementById('poi-error');
    let poiMode = false, poiPoint = null;

    for (const [key, t] of Object.entries(HwrtMap.POI_TYPES)) {
      const o = document.createElement('option');
      o.value = key; o.textContent = t.label;
      typeSel.appendChild(o);
    }
    typeSel.value = 'muster';

    const setPoiMode = on => {
      poiMode = on;
      addBtn.classList.toggle('bg-amber-700', on);
      map.getContainer().style.cursor = on ? 'crosshair' : '';
      if (!on) { poiForm.classList.add('hidden'); errEl.classList.add('hidden'); }
    };
    addBtn.addEventListener('click', () => setPoiMode(!poiMode));
    map.on('click', e => {
      if (!poiMode) return;
      poiPoint = fromLL(e.latlng);
      document.getElementById('poi-coords').textContent = `E ${poiPoint.e.toFixed(1)}  N ${poiPoint.n.toFixed(1)}  MGA50`;
      if (!nameEl.value) nameEl.value = HwrtMap.POI_TYPES[typeSel.value]?.label || '';
      poiForm.classList.remove('hidden');
    });
    typeSel.addEventListener('change', () => {
      const labels = Object.values(HwrtMap.POI_TYPES).map(t => t.label);
      if (!nameEl.value || labels.includes(nameEl.value)) nameEl.value = HwrtMap.POI_TYPES[typeSel.value].label;
    });
    document.getElementById('poi-cancel').addEventListener('click', () => setPoiMode(false));
    document.getElementById('poi-save').addEventListener('click', async () => {
      errEl.classList.add('hidden');
      const r = await fetch('/api/landmarks', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.csrf, Accept: 'application/json' },
        body: JSON.stringify({ name: nameEl.value, category: typeSel.value, easting: poiPoint.e, northing: poiPoint.n }),
      });
      const j = await r.json().catch(() => ({}));
      if (!r.ok) {
        errEl.textContent = j.message || 'Could not save point.';
        errEl.classList.remove('hidden');
        return;
      }
      cm.addLandmark(j);
      nameEl.value = '';
      setPoiMode(false);
    });

    // --- adjustable entry pin size ---
    let pinSize = +document.getElementById('pinsize').value;
    document.getElementById('pinsize').addEventListener('input', e => {
      pinSize = +e.target.value;
      for (const mk of markers.values()) mk.setIcon(HwrtMap.entryIcon(mk._en, pinSize));
    });

    document.getElementById('planop').addEventListener('input', e => cm.setPlanOpacity(+e.target.value));
    map.on('mousemove', e => {
      const { e: E, n: N } = fromLL(e.latlng);
      document.getElementById('coords').textContent = `E ${E.toFixed(1)}  N ${N.toFixed(1)}  MGA50`;
    });

    const canClose = true;
    const markers = new Map();
    const esc = value => String(value ?? '').replace(/[&<>"']/g, ch => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
    })[ch]);

    function popup(en) {
      const el = document.createElement('div');
      el.innerHTML = `<div class="font-mono text-xs text-slate-400">${esc(en.hrw_ref || '')}</div>
        <div class="font-semibold text-base">${esc(en.location)}</div>
        <div class="text-xs">${esc(en.type_display || en.type)} · ${esc(en.area || '—')} · open <b>${fmtElapsed(en.elapsed_s)}</b></div>
        ${en.notes ? `<div class="text-xs mt-1">${esc(en.notes)}</div>` : ''}
        ${en.location_document_url ? `<div class="mt-2"><a href="${esc(en.location_document_url)}" target="_blank" rel="noopener" class="underline font-semibold">Open location PDF${en.location_document_name ? ' — ' + esc(en.location_document_name) : ''}</a></div>` : ''}
        <div class="text-xs text-slate-400 mt-1">${esc(new Date(en.opened_at).toLocaleTimeString('en-AU', {timeZone:'Australia/Perth',hour:'2-digit',minute:'2-digit'}))} · ${esc(en.opened_by || '')}${en.permit_no ? ' · permit ' + esc(en.permit_no) : ''}</div>`;
      if (canClose) {
        const f = document.createElement('form');
        f.method = 'post';
        f.action = `/entries/${en.id}/close`;
        f.className = 'mt-2 flex gap-1';
        f.innerHTML = `<input type="hidden" name="_token" value="${esc(window.csrf)}"><input name="close_note" placeholder="note" class="flex-1 rounded bg-slate-800 border border-slate-600 px-2 py-1 text-xs"><button class="rounded bg-emerald-600 px-3 py-1 text-xs font-semibold">Close</button>`;
        el.appendChild(f);
      }
      return el;
    }

    async function refresh() {
      try {
        const j = await HwrtMap.fetchJson('/api/open', 'Open entries');
        const seen = new Set();
        for (const en of j.entries) {
          seen.add(en.id);
          let mk = markers.get(en.id);
          if (!mk) {
            mk = L.marker(toLL(en.easting, en.northing), {
              icon: HwrtMap.entryIcon(en, pinSize),
              zIndexOffset: 1000,
            }).addTo(pins);
            markers.set(en.id, mk);
          } else {
            mk.setIcon(HwrtMap.entryIcon(en, pinSize));
          }
          mk._en = en;
          mk.bindTooltip(`${en.location} · ${fmtElapsed(en.elapsed_s)}`, { direction: 'top', offset: [0, -Math.round(pinSize * 1.4)] });
          mk.bindPopup(popup(en));
        }
        for (const [id, mk] of markers) {
          if (!seen.has(id)) {
            mk.remove();
            markers.delete(id);
          }
        }
        document.getElementById('count').textContent = `${j.entries.length} open`;
      } catch (error) {
        console.error('HWRT map: open entries unavailable', error);
        window.hwrtReportError?.('map-open-entries', error.message || 'Open entries refresh failed', {
          stack: error.stack || null,
        });
        showNotice('Map loaded, but open entries could not be refreshed.', false);
      }
    }

    await refresh();
    setInterval(refresh, {{ config('hwrt.poll_seconds') }} * 1000);

    const focus = new URLSearchParams(location.search);
    if (focus.get('e') && focus.get('n')) cm.focus(+focus.get('e'), +focus.get('n'), 2);
  } catch (error) {
    console.error('HWRT map failed to initialise', error);
    window.hwrtReportError?.('map-init', error.message || 'Map failed to initialise', {
      stack: error.stack || null,
    });
    mapEl.dataset.hwrtMapState = 'error';
    showNotice('Map imagery could not be loaded. ' + (error.message || ''), true);
  }
})();
</script>
@endpush
