@extends('layouts.app')
@section('title', 'Map')
@section('main-class', 'relative')
@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/js/hrwt-map.js"></script>
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
<div class="absolute left-3 bottom-3 z-[1000] font-mono text-xs bg-slate-900/90 rounded px-2 py-1" id="coords">E — N —</div>
<div class="absolute right-3 bottom-3 z-[1000] bg-slate-900/90 rounded px-3 py-2 text-xs flex items-center gap-2">
  <label>plan <input id="planop" type="range" min="0" max="1" step="0.05" value="1" class="align-middle w-28"></label>
  <span id="count" class="ml-2 font-semibold"></span>
</div>
@endsection

@push('scripts')
<script>
(async () => {
  const cm = await HrwtMap.create('map', { collapsed: false });
  const { map, pins, toLL, fromLL } = cm;
  document.getElementById('planop').addEventListener('input', e => cm.setPlanOpacity(+e.target.value));
  map.on('mousemove', e => { const { e: E, n: N } = fromLL(e.latlng); document.getElementById('coords').textContent = `E ${E.toFixed(1)}  N ${N.toFixed(1)}  MGA50`; });

  const canClose = {{ auth()->user()->atLeast('logger') ? 'true' : 'false' }};
  const markers = new Map();
  function popup(en) {
    const el = document.createElement('div');
    el.innerHTML = `<div class="font-semibold text-base">${en.location}</div>
      <div class="text-xs">${en.type} · ${en.area || '—'} · open <b>${fmtElapsed(en.elapsed_s)}</b></div>
      ${en.notes ? `<div class="text-xs mt-1">${en.notes}</div>` : ''}
      <div class="text-xs text-slate-400 mt-1">${new Date(en.opened_at).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'})} · ${en.opened_by || ''}${en.permit_no ? ' · permit ' + en.permit_no : ''}</div>`;
    if (canClose) {
      const f = document.createElement('form'); f.method = 'post'; f.action = `/entries/${en.id}/close`; f.className = 'mt-2 flex gap-1';
      f.innerHTML = `<input type="hidden" name="_token" value="${window.csrf}"><input name="close_note" placeholder="note" class="flex-1 rounded bg-slate-800 border border-slate-600 px-2 py-1 text-xs"><button class="rounded bg-emerald-600 px-3 py-1 text-xs font-semibold">Close</button>`;
      el.appendChild(f);
    }
    return el;
  }
  async function refresh() {
    const j = await fetch('/api/open', { headers: { Accept: 'application/json' } }).then(r => r.json());
    const seen = new Set();
    for (const en of j.entries) {
      seen.add(en.id);
      let mk = markers.get(en.id);
      if (!mk) { mk = L.marker(toLL(en.easting, en.northing), { icon: HrwtMap.entryIcon(en), zIndexOffset: 1000 }).addTo(pins); markers.set(en.id, mk); }
      else mk.setIcon(HrwtMap.entryIcon(en));
      mk.bindTooltip(`${en.location} · ${fmtElapsed(en.elapsed_s)}`, { direction: 'top', offset: [0, -36] });
      mk.bindPopup(popup(en));
    }
    for (const [id, mk] of markers) if (!seen.has(id)) { mk.remove(); markers.delete(id); }
    document.getElementById('count').textContent = `${j.entries.length} open`;
  }
  await refresh();
  setInterval(refresh, {{ config('hrwt.poll_seconds', 15) }} * 1000);
  const focus = new URLSearchParams(location.search);
  if (focus.get('e')) cm.focus(+focus.get('e'), +focus.get('n'), 2);
})();
</script>
@endpush
