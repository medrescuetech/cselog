@extends('layouts.app')
@section('title', 'Map')
@section('main-class', 'relative')
@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/js/hwrt-map.js"></script>
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
  <span id="count" class="ml-2 font-semibold"></span>
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

    const cm = await HwrtMap.create('map', { collapsed: false });
    const { map, pins, toLL, fromLL } = cm;

    document.getElementById('planop').addEventListener('input', e => cm.setPlanOpacity(+e.target.value));
    map.on('mousemove', e => {
      const { e: E, n: N } = fromLL(e.latlng);
      document.getElementById('coords').textContent = `E ${E.toFixed(1)}  N ${N.toFixed(1)}  MGA50`;
    });

    const canClose = {{ auth()->user()->atLeast('logger') ? 'true' : 'false' }};
    const markers = new Map();
    const esc = value => String(value ?? '').replace(/[&<>"']/g, ch => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
    })[ch]);

    function popup(en) {
      const el = document.createElement('div');
      el.innerHTML = `<div class="font-semibold text-base">${esc(en.location)}</div>
        <div class="text-xs">${esc(en.type)} · ${esc(en.area || '—')} · open <b>${fmtElapsed(en.elapsed_s)}</b></div>
        ${en.notes ? `<div class="text-xs mt-1">${esc(en.notes)}</div>` : ''}
        <div class="text-xs text-slate-400 mt-1">${esc(new Date(en.opened_at).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'}))} · ${esc(en.opened_by || '')}${en.permit_no ? ' · permit ' + esc(en.permit_no) : ''}</div>`;
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
              icon: HwrtMap.entryIcon(en),
              zIndexOffset: 1000,
            }).addTo(pins);
            markers.set(en.id, mk);
          } else {
            mk.setIcon(HwrtMap.entryIcon(en));
          }
          mk.bindTooltip(`${en.location} · ${fmtElapsed(en.elapsed_s)}`, { direction: 'top', offset: [0, -36] });
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
        console.error('CSEM map: open entries unavailable', error);
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
    console.error('CSEM map failed to initialise', error);
    window.hwrtReportError?.('map-init', error.message || 'Map failed to initialise', {
      stack: error.stack || null,
    });
    mapEl.dataset.hwrtMapState = 'error';
    showNotice('Map imagery could not be loaded. ' + (error.message || ''), true);
  }
})();
</script>
@endpush
