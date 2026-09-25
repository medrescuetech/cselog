@extends('layouts.app')
@section('title', 'Settings · Landmarks')
@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/js/hwrt-map.js"></script>
<style>#landmark-map{height:420px}</style>
@endpush

@section('content')
<div class="space-y-5" x-data="landmarkSettings()" x-init="init()">
  <div class="flex items-start gap-3">
    <div>
      <h1 class="text-2xl font-bold">Settings · Landmarks</h1>
      <p class="text-sm text-slate-400 mt-1">Add key landmarks manually. Click the map to populate MGA Zone 50 coordinates.</p>
    </div>
    <div class="flex-1"></div>
    <a href="{{ route('settings.index') }}" class="rounded-lg bg-slate-800 hover:bg-slate-700 px-3 py-2 text-sm">Back to Settings</a>
  </div>

  <div class="grid gap-4 xl:grid-cols-[1fr_420px]">
    <div id="landmark-map" class="rounded-xl border border-slate-700 overflow-hidden"></div>

    <form method="post" action="{{ route('settings.landmarks.store') }}" class="rounded-xl border border-slate-700 bg-slate-800/70 p-4 space-y-3">
      @csrf
      <h2 class="font-semibold text-lg">Add landmark</h2>
      <label class="block"><span class="text-xs text-slate-400">Name</span>
        <input name="name" required maxlength="120" class="mt-1 w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
      <label class="block"><span class="text-xs text-slate-400">Category</span>
        <input name="category" value="manual" maxlength="60" class="mt-1 w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
      <div class="grid grid-cols-2 gap-2">
        <label><span class="text-xs text-slate-400">Easting</span>
          <input name="easting" x-model="e" required type="number" step="0.001" class="mt-1 w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
        <label><span class="text-xs text-slate-400">Northing</span>
          <input name="northing" x-model="n" required type="number" step="0.001" class="mt-1 w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
      </div>
      <input type="hidden" name="active" value="1">
      <div class="text-xs text-slate-500">Click anywhere on the map to fill the coordinates, then name the landmark and save.</div>
      <button class="w-full rounded-lg bg-emerald-600 hover:bg-emerald-500 px-4 py-2 font-semibold">Add landmark</button>
    </form>
  </div>

  <div class="space-y-3">
    @foreach ($landmarks as $landmark)
      <form method="post" action="{{ route('settings.landmarks.update', $landmark) }}" class="rounded-xl border {{ $landmark->active ? 'border-slate-700 bg-slate-800/70' : 'border-slate-800 bg-slate-900/60 opacity-75' }} p-4">
        @csrf @method('PATCH')
        <div class="grid gap-3 lg:grid-cols-12 items-end">
          <label class="lg:col-span-3"><span class="block text-xs text-slate-400 mb-1">Name</span>
            <input name="name" value="{{ $landmark->name }}" required class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
          <label class="lg:col-span-2"><span class="block text-xs text-slate-400 mb-1">Category</span>
            <input name="category" value="{{ $landmark->category }}" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
          <label class="lg:col-span-2"><span class="block text-xs text-slate-400 mb-1">Easting</span>
            <input name="easting" value="{{ $landmark->easting }}" required type="number" step="0.001" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
          <label class="lg:col-span-2"><span class="block text-xs text-slate-400 mb-1">Northing</span>
            <input name="northing" value="{{ $landmark->northing }}" required type="number" step="0.001" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
          <label class="lg:col-span-1 flex gap-2 items-center"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked($landmark->active)> Active</label>
          <div class="lg:col-span-2"><button class="w-full rounded-lg bg-slate-700 hover:bg-slate-600 px-3 py-2">Save</button></div>
        </div>
        <div class="mt-2 text-xs text-slate-500">Source: {{ $landmark->source ?: 'manual' }}</div>
      </form>
    @endforeach
  </div>
</div>
@endsection

@push('scripts')
<script>
function landmarkSettings() {
  return {
    e: '', n: '', mapObj: null, marker: null,
    async init() {
      this.mapObj = await HwrtMap.create('landmark-map', { skipPrints: true, collapsed: true });
      this.mapObj.map.on('click', event => {
        const p = this.mapObj.fromLL(event.latlng);
        this.e = p.e.toFixed(3);
        this.n = p.n.toFixed(3);
        if (!this.marker) {
          this.marker = L.marker(event.latlng).addTo(this.mapObj.map);
        } else {
          this.marker.setLatLng(event.latlng);
        }
      });
    }
  };
}
</script>
@endpush
