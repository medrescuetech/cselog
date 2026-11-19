@extends('layouts.app')
@section('title', 'Settings · Map overlays')
@push('head')
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script src="/js/hwrt-map.js"></script>
<style>#landmark-map{height:480px}</style>
@endpush

@section('content')
<div class="space-y-5" x-data="landmarkSettings()" x-init="init()">
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
        <button type="button" @click="mode='marker'" :class="mode === 'marker' ? 'bg-emerald-700' : 'bg-slate-800 hover:bg-slate-700'" class="rounded-lg px-3 py-2 text-sm font-semibold">Place marker / label</button>
        <button type="button" @click="mode='sector'" :class="mode === 'sector' ? 'bg-sky-700' : 'bg-slate-800 hover:bg-slate-700'" class="rounded-lg px-3 py-2 text-sm font-semibold">Draw sector</button>
      </div>
      <div id="landmark-map" class="rounded-xl border border-slate-700 overflow-hidden"></div>
      <div class="text-xs text-slate-500" x-show="mode === 'marker'">Click the map to fill the marker coordinates.</div>
      <div class="text-xs text-slate-500" x-show="mode === 'sector'">
        Click at least three points to draw a sector. Vertices: <b x-text="vertices.length"></b>
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
        <div class="grid grid-cols-2 gap-2">
          <label><span class="text-xs text-slate-400">Colour</span>
            <input name="colour" type="color" value="{{ old('colour', '#ff5f1f') }}" class="mt-1 h-10 w-full rounded-lg bg-slate-900 border border-slate-700 px-1 py-1"></label>
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
        <div class="grid grid-cols-3 gap-2">
          <label><span class="text-xs text-slate-400">Colour</span>
            <input name="colour" x-model="sectorColour" type="color" class="mt-1 h-10 w-full rounded-lg bg-slate-900 border border-slate-700 px-1 py-1"></label>
          <label><span class="text-xs text-slate-400">Opacity</span>
            <input name="fill_opacity" x-model="sectorOpacity" type="number" min="0" max="1" step="0.01" class="mt-1 w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
          <label><span class="text-xs text-slate-400">Order</span>
            <input name="sort_order" value="{{ old('sort_order', 0) }}" type="number" min="-999" max="999" class="mt-1 w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
        </div>
        <label class="block"><span class="text-xs text-slate-400">Notes</span>
          <textarea name="notes" rows="2" maxlength="1000" class="mt-1 w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2">{{ old('notes') }}</textarea></label>
        <input type="hidden" name="geometry" x-model="sectorGeometry">
        <input type="hidden" name="active" value="1">
        <div class="flex gap-2">
          <button type="button" @click="undoVertex" class="flex-1 rounded-lg bg-slate-700 hover:bg-slate-600 px-3 py-2">Undo point</button>
          <button type="button" @click="clearVertices" class="flex-1 rounded-lg bg-slate-700 hover:bg-slate-600 px-3 py-2">Clear</button>
        </div>
        <button :disabled="vertices.length < 3" class="w-full rounded-lg bg-sky-600 hover:bg-sky-500 disabled:bg-slate-700 disabled:text-slate-500 px-4 py-2 font-semibold">Add sector</button>
      </form>
    </div>
  </div>

  <section class="space-y-3">
    <h2 class="text-lg font-semibold">Static markers and labels</h2>
    @foreach ($landmarks as $landmark)
      <form method="post" action="{{ route('settings.landmarks.update', $landmark) }}" class="rounded-xl border {{ $landmark->active ? 'border-slate-700 bg-slate-800/70' : 'border-slate-800 bg-slate-900/60 opacity-75' }} p-4 space-y-3">
        @csrf @method('PATCH')
        <div class="grid gap-3 lg:grid-cols-12 items-end">
          <label class="lg:col-span-3"><span class="block text-xs text-slate-400 mb-1">Name</span>
            <input name="name" value="{{ $landmark->name }}" required class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
          <label class="lg:col-span-2"><span class="block text-xs text-slate-400 mb-1">Type</span>
            <select name="marker_type" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2">
              @foreach ($markerTypes as $value => $label)
                <option value="{{ $value }}" @selected($landmark->marker_type === $value)>{{ $label }}</option>
              @endforeach
            </select></label>
          <label class="lg:col-span-2"><span class="block text-xs text-slate-400 mb-1">Category</span>
            <input name="category" value="{{ $landmark->category }}" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
          <label class="lg:col-span-1"><span class="block text-xs text-slate-400 mb-1">Colour</span>
            <input name="colour" type="color" value="{{ $landmark->colour ?: '#ff5f1f' }}" class="h-10 w-full rounded-lg bg-slate-900 border border-slate-700 px-1 py-1"></label>
          <label class="lg:col-span-2"><span class="block text-xs text-slate-400 mb-1">Easting</span>
            <input name="easting" value="{{ $landmark->easting }}" required type="number" step="0.001" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
          <label class="lg:col-span-2"><span class="block text-xs text-slate-400 mb-1">Northing</span>
            <input name="northing" value="{{ $landmark->northing }}" required type="number" step="0.001" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
        </div>
        <div class="grid gap-3 lg:grid-cols-12 items-end">
          <label class="lg:col-span-2"><span class="block text-xs text-slate-400 mb-1">Label angle</span>
            <input name="rotation_deg" value="{{ $landmark->rotation_deg }}" type="number" min="-180" max="180" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
          <label class="lg:col-span-2 flex gap-2 items-center"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked($landmark->active)> Active</label>
          <label class="lg:col-span-3 flex gap-2 items-center"><input type="hidden" name="label_visible" value="0"><input type="checkbox" name="label_visible" value="1" @checked($landmark->label_visible)> Show label</label>
          <label class="lg:col-span-4"><span class="block text-xs text-slate-400 mb-1">Notes</span>
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
          <label class="lg:col-span-4"><span class="block text-xs text-slate-400 mb-1">Name</span>
            <input name="name" value="{{ $sector->name }}" required class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
          <label class="lg:col-span-2"><span class="block text-xs text-slate-400 mb-1">Colour</span>
            <input name="colour" type="color" value="{{ $sector->colour }}" class="h-10 w-full rounded-lg bg-slate-900 border border-slate-700 px-1 py-1"></label>
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
    vertices: [], sectorLayer: null, sectorGeometry: '', sectorColour: @js(old('colour', '#38bdf8')), sectorOpacity: @js(old('fill_opacity', 0.08)),
    async init() {
      this.mapObj = await HwrtMap.create('landmark-map', { skipPrints: true, collapsed: true });
      this.mapObj.map.on('click', event => this.mode === 'sector' ? this.addVertex(event.latlng) : this.setPoint(event.latlng));
    },
    setPoint(latlng) {
      const p = this.mapObj.fromLL(latlng);
      this.e = p.e.toFixed(3);
      this.n = p.n.toFixed(3);
      if (!this.marker) this.marker = L.marker(latlng).addTo(this.mapObj.map);
      else this.marker.setLatLng(latlng);
    },
    addVertex(latlng) {
      const p = this.mapObj.fromLL(latlng);
      this.vertices.push([Number(p.e.toFixed(3)), Number(p.n.toFixed(3))]);
      this.redrawSector();
    },
    undoVertex() {
      this.vertices.pop();
      this.redrawSector();
    },
    clearVertices() {
      this.vertices = [];
      this.redrawSector();
    },
    redrawSector() {
      if (this.sectorLayer) this.sectorLayer.remove();
      this.sectorLayer = null;
      this.sectorGeometry = '';
      if (!this.vertices.length) return;
      const points = this.vertices.map(v => this.mapObj.toLL(v[0], v[1]));
      this.sectorLayer = this.vertices.length >= 3
        ? L.polygon(points, { color: this.sectorColour, weight: 2, fillOpacity: Number(this.sectorOpacity) }).addTo(this.mapObj.map)
        : L.polyline(points, { color: this.sectorColour, weight: 2 }).addTo(this.mapObj.map);
      if (this.vertices.length >= 3) {
        this.sectorGeometry = JSON.stringify({ type: 'Polygon', coordinates: [[...this.vertices, this.vertices[0]]] });
      }
    },
  };
}
</script>
@endpush
