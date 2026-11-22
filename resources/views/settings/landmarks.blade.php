@extends('layouts.app')
@section('title', 'Settings · Map overlays')
@push('head')
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script src="/js/hwrt-map.js?v={{ filemtime(public_path('js/hwrt-map.js')) }}"></script>
<script src="{{ asset('js/hwrt-colour-picker.js') }}"></script>
<style>#landmark-map{height:480px}</style>
@endpush

@section('content')
<div class="space-y-5" x-data="landmarkSettings()" x-init="init()" @sector-colour.window="sectorColour = $event.detail; redrawSector()">
  <div class="flex items-start gap-3">
    <div>
      <h1 class="text-2xl font-bold">Settings · Map overlays</h1>
      <p class="text-sm text-slate-400 mt-1">Add static markers, road labels, muster points and custom sector polygons in MGA Zone 50.</p>
    </div>
    <div class="flex-1"></div>
    <a href="{{ route('settings.index') }}" class="rounded-lg bg-slate-800 hover:bg-slate-700 px-3 py-2 text-sm">Back to Settings</a>
  </div>

  <div class="grid gap-4 xl:grid-cols-[1fr_460px]">
    <div class="space-y-3">
      <div class="flex flex-wrap gap-2">
        <button type="button" @click="startMarkerMode" :class="mode === 'marker' ? 'bg-emerald-700' : 'bg-slate-800 hover:bg-slate-700'" class="rounded-lg px-3 py-2 text-sm font-semibold">Place marker / label</button>
        <button type="button" @click="startSectorMode" :class="mode === 'sector' ? 'bg-sky-700' : 'bg-slate-800 hover:bg-slate-700'" class="rounded-lg px-3 py-2 text-sm font-semibold">Draw sector</button>
      </div>
      <div id="landmark-map" class="rounded-xl border border-slate-700 overflow-hidden"></div>
      <div class="text-xs text-slate-500" x-show="mode === 'marker'">Click the map to fill the marker coordinates.</div>
      <div class="text-xs text-slate-500" x-show="mode === 'sector'">
        Click along the sector boundary, then press Finish or double-click to close it. Vertices: <b x-text="vertices.length"></b>
        <span x-show="sectorStatus" class="text-sky-300" x-text="sectorStatus"></span>
      </div>
    </div>

    <div class="space-y-4">
      <form x-show="mode === 'marker'" method="post" action="{{ route('settings.landmarks.store') }}" class="rounded-xl border border-slate-700 bg-slate-800/70 p-4 space-y-3">
        @csrf
        <h2 class="font-semibold text-lg">Add static marker or label</h2>
        <label class="block"><span class="text-xs text-slate-400">Name / road name</span>
          <input name="name" value="{{ old('name') }}" required maxlength="120" class="mt-1 w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
        <div class="grid grid-cols-2 gap-2">
          <label><span class="text-xs text-slate-400">Marker type</span>
            <select name="marker_type" x-model="markerType" class="mt-1 w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2">
              @foreach ($markerTypes as $value => $label)
                <option value="{{ $value }}" @selected(old('marker_type', 'landmark') === $value)>{{ $label }}</option>
              @endforeach
            </select></label>
          <label><span class="text-xs text-slate-400">Overlay category</span>
            <input name="category" x-model="category" maxlength="60" class="mt-1 w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
        </div>
        <div class="grid grid-cols-[1fr_120px] gap-2">
          <div><span class="text-xs text-slate-400">Colour</span>
            <div class="mt-1">@include('settings.partials.colour-picker', ['name' => 'colour', 'value' => old('colour', '#ff5f1f'), 'event' => 'marker-colour'])</div></div>
          <label><span class="text-xs text-slate-400">Label angle</span>
            <input name="rotation_deg" value="{{ old('rotation_deg', 0) }}" type="number" min="-180" max="180" class="mt-1 w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
        </div>
        <div class="grid grid-cols-2 gap-2">
          <label><span class="text-xs text-slate-400">Easting</span>
            <input name="easting" x-model="e" required type="number" step="0.001" class="mt-1 w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
          <label><span class="text-xs text-slate-400">Northing</span>
            <input name="northing" x-model="n" required type="number" step="0.001" class="mt-1 w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
        </div>
        <label class="block"><span class="text-xs text-slate-400">Notes</span>
          <textarea name="notes" rows="2" maxlength="1000" class="mt-1 w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2">{{ old('notes') }}</textarea></label>
        <input type="hidden" name="active" value="1">
        <input type="hidden" name="label_visible" value="0">
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="label_visible" value="1" checked> Show its name on the map</label>
        <button class="w-full rounded-lg bg-emerald-600 hover:bg-emerald-500 px-4 py-2 font-semibold">Add marker</button>
      </form>

      <form x-show="mode === 'sector'" method="post" action="{{ route('settings.map-sectors.store') }}" class="rounded-xl border border-slate-700 bg-slate-800/70 p-4 space-y-3">
        @csrf
        <h2 class="font-semibold text-lg">Add map sector</h2>
        <label class="block"><span class="text-xs text-slate-400">Sector name</span>
          <input name="name" value="{{ old('name') }}" required maxlength="120" class="mt-1 w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
        <div class="grid grid-cols-[1fr_100px_90px] gap-2 items-end">
          <div><span class="text-xs text-slate-400">Colour</span>
            <div class="mt-1">@include('settings.partials.colour-picker', ['name' => 'colour', 'value' => old('colour', '#38bdf8'), 'event' => 'sector-colour'])</div></div>
          <label><span class="text-xs text-slate-400">Opacity</span>
            <input name="fill_opacity" x-model="sectorOpacity" @input="redrawSector" type="number" min="0" max="1" step="0.01" class="mt-1 w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
          <label><span class="text-xs text-slate-400">Order</span>
            <input name="sort_order" value="{{ old('sort_order', 0) }}" type="number" min="-999" max="999" class="mt-1 w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
        </div>
        <label class="block"><span class="text-xs text-slate-400">Notes</span>
          <textarea name="notes" rows="2" maxlength="1000" class="mt-1 w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2">{{ old('notes') }}</textarea></label>
        <input type="hidden" name="geometry" x-model="sectorGeometry">
        <input type="hidden" name="active" value="1">
        <div class="grid grid-cols-[1fr_110px] gap-2 items-end rounded-lg bg-slate-900/50 p-3">
          <label class="flex gap-2 items-center text-sm"><input type="checkbox" x-model="snapToRoads"> Snap to Site C roads</label>
          <label><span class="block text-xs text-slate-400 mb-1">Snap metres</span>
            <input x-model="snapTolerance" type="number" min="1" max="50" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
        </div>
        <div class="grid grid-cols-4 gap-2">
          <button type="button" @click="undoVertex" class="rounded-lg bg-slate-700 hover:bg-slate-600 px-3 py-2">Undo</button>
          <button type="button" @click="clearVertices" class="rounded-lg bg-slate-700 hover:bg-slate-600 px-3 py-2">Clear</button>
          <button type="button" @click="finishSector" class="rounded-lg bg-sky-700 hover:bg-sky-600 px-3 py-2">Finish</button>
          <button type="button" @click="cancelSector" class="rounded-lg bg-slate-700 hover:bg-slate-600 px-3 py-2">Cancel</button>
        </div>
        <button :disabled="!sectorFinished" class="w-full rounded-lg bg-sky-600 hover:bg-sky-500 disabled:bg-slate-700 disabled:text-slate-500 px-4 py-2 font-semibold">Add sector</button>
      </form>
    </div>
  </div>

  <section class="space-y-3">
    <h2 class="text-lg font-semibold">Static markers and labels</h2>
    @foreach ($landmarks as $landmark)
      <form method="post" action="{{ route('settings.landmarks.update', $landmark) }}" class="rounded-xl border {{ $landmark->active ? 'border-slate-700 bg-slate-800/70' : 'border-slate-800 bg-slate-900/60 opacity-75' }} p-4 space-y-3">
        @csrf @method('PATCH')
        <div class="grid gap-3 lg:grid-cols-12 items-end">
          <label class="lg:col-span-4"><span class="block text-xs text-slate-400 mb-1">Name</span>
            <input name="name" value="{{ $landmark->name }}" required class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
          <label class="lg:col-span-2"><span class="block text-xs text-slate-400 mb-1">Type</span>
            <select name="marker_type" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2">
              @foreach ($markerTypes as $value => $label)
                <option value="{{ $value }}" @selected($landmark->marker_type === $value)>{{ $label }}</option>
              @endforeach
            </select></label>
          <label class="lg:col-span-2"><span class="block text-xs text-slate-400 mb-1">Category</span>
            <input name="category" value="{{ $landmark->category }}" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
          <label class="lg:col-span-2"><span class="block text-xs text-slate-400 mb-1">Easting</span>
            <input name="easting" value="{{ $landmark->easting }}" required type="number" step="0.001" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
          <label class="lg:col-span-2"><span class="block text-xs text-slate-400 mb-1">Northing</span>
            <input name="northing" value="{{ $landmark->northing }}" required type="number" step="0.001" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
        </div>
        <div class="grid gap-3 lg:grid-cols-12 items-end">
          <div class="lg:col-span-5"><span class="block text-xs text-slate-400 mb-1">Colour</span>
            @include('settings.partials.colour-picker', ['name' => 'colour', 'value' => $landmark->colour ?: '#ff5f1f', 'event' => 'landmark-colour-'.$landmark->id])</div>
          <label class="lg:col-span-2"><span class="block text-xs text-slate-400 mb-1">Label angle</span>
            <input name="rotation_deg" value="{{ $landmark->rotation_deg }}" type="number" min="-180" max="180" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
          <label class="lg:col-span-2 flex gap-2 items-center"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked($landmark->active)> Active</label>
          <label class="lg:col-span-3 flex gap-2 items-center"><input type="hidden" name="label_visible" value="0"><input type="checkbox" name="label_visible" value="1" @checked($landmark->label_visible)> Show label</label>
        </div>
        <div class="grid gap-3 lg:grid-cols-12 items-end">
          <label class="lg:col-span-11"><span class="block text-xs text-slate-400 mb-1">Notes</span>
            <input name="notes" value="{{ $landmark->notes }}" maxlength="1000" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
          <div class="lg:col-span-1"><button class="w-full rounded-lg bg-slate-700 hover:bg-slate-600 px-3 py-2">Save</button></div>
        </div>
        <div class="text-xs text-slate-500">Source: {{ $landmark->source ?: 'manual' }}</div>
      </form>
    @endforeach
  </section>

  <section class="space-y-3">
    <h2 class="text-lg font-semibold">Map sectors</h2>
    @foreach ($sectors as $sector)
      <form method="post" action="{{ route('settings.map-sectors.update', $sector) }}" class="rounded-xl border {{ $sector->active ? 'border-slate-700 bg-slate-800/70' : 'border-slate-800 bg-slate-900/60 opacity-75' }} p-4 space-y-3">
        @csrf @method('PATCH')
        <div class="grid gap-3 lg:grid-cols-12 items-end">
          <label class="lg:col-span-3"><span class="block text-xs text-slate-400 mb-1">Name</span>
            <input name="name" value="{{ $sector->name }}" required class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
          <div class="lg:col-span-4"><span class="block text-xs text-slate-400 mb-1">Colour</span>
            @include('settings.partials.colour-picker', ['name' => 'colour', 'value' => $sector->colour, 'event' => 'sector-colour-'.$sector->id])</div>
          <label class="lg:col-span-2"><span class="block text-xs text-slate-400 mb-1">Opacity</span>
            <input name="fill_opacity" value="{{ $sector->fill_opacity }}" required type="number" min="0" max="1" step="0.01" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
          <label class="lg:col-span-1"><span class="block text-xs text-slate-400 mb-1">Order</span>
            <input name="sort_order" value="{{ $sector->sort_order }}" required type="number" min="-999" max="999" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
          <label class="lg:col-span-2 flex gap-2 items-center"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked($sector->active)> Active</label>
          <div class="lg:col-span-1"><button class="w-full rounded-lg bg-slate-700 hover:bg-slate-600 px-3 py-2">Save</button></div>
        </div>
        <label><span class="block text-xs text-slate-400 mb-1">GeoJSON Polygon/MultiPolygon geometry</span>
          <textarea name="geometry" rows="3" required class="w-full font-mono text-xs rounded-lg bg-slate-900 border border-slate-700 px-3 py-2">{{ json_encode($sector->geometry) }}</textarea></label>
        <label><span class="block text-xs text-slate-400 mb-1">Notes</span>
          <input name="notes" value="{{ $sector->notes }}" maxlength="1000" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
      </form>
    @endforeach
  </section>
</div>
@endsection

@push('scripts')
<script>
function landmarkSettings() {
  return {
    mode: 'marker',
    e: '', n: '', markerType: @js(old('marker_type', 'landmark')), category: @js(old('category', 'Landmarks')),
    mapObj: null, marker: null,
    vertices: [], sectorLayer: null, cursorLayer: null, cursorPoint: null,
    sectorGeometry: '', sectorColour: @js(old('colour', '#38bdf8')), sectorOpacity: @js(old('fill_opacity', 0.08)),
    sectorDrawing: false, sectorFinished: false, sectorStatus: '',
    clickTimer: null, pendingSectorPoint: null,
    snapToRoads: true, snapTolerance: 8,
    async init() {
      this.mapObj = await HwrtMap.create('landmark-map', { skipPrints: true, collapsed: true });
      this.mapObj.map.on('click', event => this.mode === 'sector' ? this.queueSectorClick(event.latlng) : this.setPoint(event.latlng));
      this.mapObj.map.on('mousemove', event => this.updateCursor(event.latlng));
      this.mapObj.map.on('dblclick', event => {
        if (this.mode !== 'sector') return;
        event.originalEvent.preventDefault();
        this.finishSector(false);
      });
    },
    setPoint(latlng) {
      const p = this.mapObj.fromLL(latlng);
      this.e = p.e.toFixed(3);
      this.n = p.n.toFixed(3);
      if (!this.marker) this.marker = L.marker(latlng).addTo(this.mapObj.map);
      else this.marker.setLatLng(latlng);
    },
    startMarkerMode() {
      this.mode = 'marker';
      this.sectorDrawing = false;
      this.sectorStatus = '';
      this.clearPendingClick(false);
      this.mapObj?.map?.doubleClickZoom?.enable();
      this.setMapCursor();
      this.redrawCursor();
    },
    startSectorMode() {
      this.mode = 'sector';
      this.sectorDrawing = !this.sectorFinished;
      this.sectorStatus = this.sectorFinished
        ? 'Sector is closed. Use Undo to continue editing or Add sector to save.'
        : 'Click along the boundary line, then use Finish.';
      this.mapObj?.map?.doubleClickZoom?.disable();
      this.setMapCursor();
      this.redrawSector();
    },
    queueSectorClick(latlng) {
      if (!this.sectorDrawing) return;
      this.clearPendingClick(false);
      this.pendingSectorPoint = latlng;
      this.clickTimer = setTimeout(() => {
        const point = this.pendingSectorPoint;
        this.pendingSectorPoint = null;
        this.clickTimer = null;
        this.addVertex(point);
      }, 350);
    },
    clearPendingClick(commit) {
      if (this.clickTimer) clearTimeout(this.clickTimer);
      const point = this.pendingSectorPoint;
      this.clickTimer = null;
      this.pendingSectorPoint = null;
      if (commit && point) this.addVertex(point);
    },
    updateCursor(latlng) {
      if (this.mode !== 'sector' || !this.sectorDrawing) return;
      const snapped = this.snapPoint(latlng);
      this.cursorPoint = snapped.latlng;
      this.sectorStatus = snapped.snapped
        ? `Snapped to road linework. Vertices: ${this.vertices.length}`
        : `Drawing boundary line. Vertices: ${this.vertices.length}`;
      this.redrawCursor();
    },
    addVertex(latlng) {
      if (!latlng || !this.sectorDrawing) return;
      const snapped = this.snapPoint(latlng);
      const p = this.mapObj.fromLL(snapped.latlng);
      this.vertices.push([Number(p.e.toFixed(3)), Number(p.n.toFixed(3))]);
      this.sectorFinished = false;
      this.sectorGeometry = '';
      this.sectorStatus = `${this.vertices.length} point${this.vertices.length === 1 ? '' : 's'} added.`;
      this.redrawSector();
    },
    undoVertex() {
      this.clearPendingClick(false);
      if (this.sectorFinished) {
        this.sectorFinished = false;
        this.sectorDrawing = true;
        this.sectorGeometry = '';
        this.mapObj?.map?.doubleClickZoom?.disable();
      }
      this.vertices.pop();
      this.sectorStatus = this.vertices.length ? 'Removed the last point.' : 'Boundary cleared.';
      this.setMapCursor();
      this.redrawSector();
    },
    clearVertices() {
      this.clearPendingClick(false);
      this.vertices = [];
      this.cursorPoint = null;
      this.sectorFinished = false;
      this.sectorDrawing = this.mode === 'sector';
      this.sectorGeometry = '';
      this.sectorStatus = 'Boundary cleared. Click along the sector line to restart.';
      if (this.sectorDrawing) this.mapObj?.map?.doubleClickZoom?.disable();
      this.setMapCursor();
      this.redrawSector();
    },
    finishSector(commitPending = true) {
      if (this.mode !== 'sector') return;
      this.clearPendingClick(commitPending);
      if (this.vertices.length < 3) {
        this.sectorStatus = 'Add at least three boundary points before finishing.';
        return;
      }
      this.sectorDrawing = false;
      this.sectorFinished = true;
      this.cursorPoint = null;
      this.sectorGeometry = JSON.stringify({ type: 'Polygon', coordinates: [[...this.vertices, this.vertices[0]]] });
      this.sectorStatus = 'Sector closed. Review it, then Add sector or Undo to continue editing.';
      this.mapObj?.map?.doubleClickZoom?.enable();
      this.setMapCursor();
      this.redrawSector();
      this.redrawCursor();
    },
    cancelSector() {
      this.clearPendingClick(false);
      this.vertices = [];
      this.cursorPoint = null;
      this.sectorGeometry = '';
      this.sectorDrawing = false;
      this.sectorFinished = false;
      this.startMarkerMode();
      this.redrawSector();
    },
    redrawSector() {
      if (this.sectorLayer) this.sectorLayer.remove();
      this.sectorLayer = null;
      if (!this.vertices.length) {
        this.redrawCursor();
        return;
      }
      this.sectorLayer = L.layerGroup().addTo(this.mapObj.map);
      const points = this.vertices.map(v => this.mapObj.toLL(v[0], v[1]));
      if (this.sectorFinished) {
        L.polygon(points, { color: this.sectorColour, weight: 2, fillOpacity: Number(this.sectorOpacity) }).addTo(this.sectorLayer);
      } else if (points.length > 1) {
        L.polyline(points, { color: this.sectorColour, weight: 3 }).addTo(this.sectorLayer);
      }
      for (const point of points) {
        L.circleMarker(point, { radius: 4, color: '#fff', weight: 1, fillColor: this.sectorColour, fillOpacity: 1 }).addTo(this.sectorLayer);
      }
      this.redrawCursor();
    },
    redrawCursor() {
      if (this.cursorLayer) this.cursorLayer.remove();
      this.cursorLayer = null;
      if (!this.sectorDrawing || !this.cursorPoint) return;
      this.cursorLayer = L.layerGroup().addTo(this.mapObj.map);
      const last = this.vertices.at(-1);
      if (last) {
        L.polyline([this.mapObj.toLL(last[0], last[1]), this.cursorPoint], {
          color: this.sectorColour,
          weight: 2,
          dashArray: '6 6',
        }).addTo(this.cursorLayer);
      }
      L.circleMarker(this.cursorPoint, { radius: 3, color: this.sectorColour, weight: 2, fillOpacity: 0 }).addTo(this.cursorLayer);
    },
    setMapCursor() {
      const container = this.mapObj?.map?.getContainer?.();
      if (container) container.style.cursor = this.sectorDrawing ? 'crosshair' : '';
    },
    snapPoint(latlng) {
      const roads = this.mapObj?.roads;
      if (!this.snapToRoads || !roads || typeof roads.eachLayer !== 'function') {
        return { latlng, snapped: false };
      }
      const p = this.mapObj.fromLL(latlng);
      const tolerance = Number(this.snapTolerance) || 8;
      let best = null;
      roads.eachLayer(layer => {
        const lines = this.latLngLines(layer.getLatLngs ? layer.getLatLngs() : []);
        for (const line of lines) {
          for (let i = 1; i < line.length; i++) {
            const a = this.mapObj.fromLL(line[i - 1]);
            const b = this.mapObj.fromLL(line[i]);
            const candidate = this.closestOnSegment(p, a, b);
            if (candidate.distance <= tolerance && (!best || candidate.distance < best.distance)) {
              best = candidate;
            }
          }
        }
      });
      return best ? { latlng: this.mapObj.toLL(best.e, best.n), snapped: true } : { latlng, snapped: false };
    },
    latLngLines(value) {
      if (!Array.isArray(value)) return [];
      if (value.length && typeof value[0].lat === 'number') return [value];
      return value.flatMap(child => this.latLngLines(child));
    },
    closestOnSegment(p, a, b) {
      const dx = b.e - a.e;
      const dy = b.n - a.n;
      const lengthSquared = (dx * dx) + (dy * dy);
      const t = lengthSquared ? Math.max(0, Math.min(1, (((p.e - a.e) * dx) + ((p.n - a.n) * dy)) / lengthSquared)) : 0;
      const e = a.e + (t * dx);
      const n = a.n + (t * dy);
      return { e, n, distance: Math.hypot(p.e - e, p.n - n) };
    },
  };
}
</script>
@endpush
