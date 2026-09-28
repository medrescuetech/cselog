@extends('layouts.app')
@section('title', $location ? 'Edit location' : 'Add location')
@push('head')
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}">
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script src="{{ asset('js/hwrt-map.js') }}"></script>
@endpush
@section('content')
@php($editing = $location !== null)
<div class="max-w-5xl mx-auto space-y-5">
  <div class="flex flex-wrap items-center gap-3"><div><h1 class="text-2xl font-bold">{{ $editing ? 'Edit location · '.$location->name : 'Add saved location' }}</h1><p class="text-sm text-slate-400 mt-1">MGA Zone 50 coordinates. A saved location can be selected on future work records.</p></div><div class="flex-1"></div><a href="{{ route('settings.locations.index') }}" class="rounded bg-slate-800 hover:bg-slate-700 px-3 py-2 text-sm">Back to Locations</a></div>
  @if ($errors->any()) <div class="rounded-lg border border-red-700 bg-red-950/60 p-4 text-sm" role="alert"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
  @if ($editing && $location->merged_into_id) <div class="rounded-lg border border-amber-700 bg-amber-950/40 p-3 text-sm">This location was merged into <a class="underline" href="{{ route('settings.locations.edit', $location->merged_into_id) }}">location #{{ $location->merged_into_id }}</a>. Its historic name and coordinates remain on work records.</div> @endif
  @if ($editing && $openJobs) <div class="rounded-lg border border-amber-700 bg-amber-950/40 p-3 text-sm text-amber-100">{{ $openJobs }} open or pending job(s) reference this catalogue location. Moving the saved pin will affect future selections only; existing job snapshots stay at their logged position.</div> @endif
  <form method="post" action="{{ $editing ? route('settings.locations.update', $location) : route('settings.locations.store') }}" class="rounded-xl border border-slate-700 bg-slate-800/70 p-5 space-y-4">
    @csrf @if ($editing) @method('PATCH') @endif
    <div class="grid gap-4 md:grid-cols-2">
      <label><span class="block text-sm mb-1">Name</span><input name="name" value="{{ old('name', $location?->name) }}" maxlength="160" required class="w-full rounded bg-slate-900 border border-slate-700 px-3 py-2"></label>
      <label><span class="block text-sm mb-1">Short code (optional)</span><input name="code" value="{{ old('code', $location?->code) }}" maxlength="60" class="w-full rounded bg-slate-900 border border-slate-700 px-3 py-2"></label>
      <label><span class="block text-sm mb-1">Easting</span><input id="loc-easting" type="number" step="0.001" min="0" max="999999.999" name="easting" value="{{ old('easting', $location?->easting) }}" required class="w-full rounded bg-slate-900 border border-slate-700 px-3 py-2"></label>
      <label><span class="block text-sm mb-1">Northing</span><input id="loc-northing" type="number" step="0.001" min="0" max="99999999.999" name="northing" value="{{ old('northing', $location?->northing) }}" required class="w-full rounded bg-slate-900 border border-slate-700 px-3 py-2"></label>
    </div>
    <label class="block"><span class="block text-sm mb-1">Other names / aliases (comma or line separated)</span><textarea name="aliases_text" rows="2" maxlength="500" class="w-full rounded bg-slate-900 border border-slate-700 px-3 py-2">{{ old('aliases_text', implode(', ', $location?->aliases ?? [])) }}</textarea></label>
    <p class="text-xs text-slate-400">Area is determined from the saved pin. Nearby saved locations are shown below to help avoid duplicates.</p>
    <div id="saved-location-map" class="rounded-lg border border-slate-700" style="height: 360px"></div>
    <p id="saved-location-map-status" class="text-xs text-slate-400">Click the map to set the coordinates, or enter them above. Map availability does not prevent manual entry.</p>
    <div id="saved-location-nearby" class="text-sm text-amber-300"></div>
    @if ($editing) <label class="block"><span class="block text-sm mb-1">Reason for changing coordinates (required if moved)</span><input name="reason" value="{{ old('reason') }}" maxlength="255" class="w-full rounded bg-slate-900 border border-slate-700 px-3 py-2"></label> @endif
    <div class="flex justify-end gap-2"><a href="{{ route('settings.locations.index') }}" class="rounded bg-slate-700 px-4 py-2">Cancel</a>@if (!$editing || !$location->merged_into_id)<button class="rounded bg-emerald-600 hover:bg-emerald-500 px-4 py-2 font-semibold">{{ $editing ? 'Save location' : 'Add location' }}</button>@endif</div>
  </form>
  @if ($editing)
    @if (!$location->merged_into_id)<form method="post" action="{{ route('settings.locations.status', $location) }}" class="rounded-xl border border-slate-700 p-4 space-y-3">@csrf @method('PATCH')
      <h2 class="font-semibold">{{ $location->status === 'active' ? 'Archive location' : 'Reactivate location' }}</h2>
      <p class="text-sm text-slate-400">Archived locations remain in historical records and are hidden from new work selection.</p>
      <input type="hidden" name="status" value="{{ $location->status === 'active' ? 'archived' : 'active' }}">
      <input name="reason" required maxlength="255" placeholder="Reason for change" class="w-full rounded bg-slate-900 border border-slate-700 px-3 py-2">
      <button class="rounded bg-slate-700 hover:bg-slate-600 px-4 py-2">{{ $location->status === 'active' ? 'Archive' : 'Reactivate' }}</button>
    </form>@endif
    @if (!$location->merged_into_id && $mergeTargets->isNotEmpty())
      <form method="post" action="{{ route('settings.locations.merge', $location) }}" class="rounded-xl border border-amber-800 p-4 space-y-3" onsubmit="return confirm('Merge this saved location into the survivor? This changes catalogue links for {{ $linkedJobs }} jobs while preserving their original name and pin.');">@csrf
        <h2 class="font-semibold">Merge duplicate location</h2>
        <p class="text-sm text-slate-400">{{ $linkedJobs }} linked job(s) will point to the survivor for catalogue filters. Their logged name, pin and area will not change. The old name becomes a searchable alias.</p>
        <select name="target_id" required class="w-full rounded bg-slate-900 border border-slate-700 px-3 py-2"><option value="">Select surviving active location…</option>@foreach ($mergeTargets as $target)<option value="{{ $target->id }}">{{ $target->name }}</option>@endforeach</select>
        <input name="reason" required maxlength="255" placeholder="Reason for merging" class="w-full rounded bg-slate-900 border border-slate-700 px-3 py-2">
        <button class="rounded bg-amber-700 hover:bg-amber-600 px-4 py-2">Merge into survivor</button>
      </form>
    @endif
    <section class="rounded-xl border border-slate-700 p-4"><h2 class="font-semibold mb-3">Recent location changes</h2><div class="space-y-2 text-sm">@forelse ($revisions as $revision)<div class="border-b border-slate-700 pb-2"><span class="font-semibold">{{ ucfirst($revision->action) }}</span> · {{ $revision->created_at?->format('d M Y H:i') }} · {{ $revision->actor?->name ?? 'System' }}@if ($revision->reason) · {{ $revision->reason }} @endif</div>@empty<p class="text-slate-400">No recorded changes yet.</p>@endforelse</div></section>
  @endif
</div>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', async () => {
  const e = document.getElementById('loc-easting');
  const n = document.getElementById('loc-northing');
  const info = document.getElementById('saved-location-map-status');
  const nearby = document.getElementById('saved-location-nearby');
  const current = @js($location?->id);
  let marker;
  let map;
  const refresh = async () => {
    const east = Number(e.value), north = Number(n.value);
    if (!e.value || !n.value || !Number.isFinite(east) || !Number.isFinite(north)) return;
    if (map) {
      const point = map.toLL(east, north);
      if (marker) marker.setLatLng(point); else marker = L.marker(point, {draggable: true}).addTo(map.map);
      marker.off('dragend').on('dragend', () => {
        const p = map.fromLL(marker.getLatLng()); e.value = p.e.toFixed(3); n.value = p.n.toFixed(3); refresh();
      });
    }
    try {
      const response = await fetch(`{{ route('api.locations.nearby') }}?easting=${encodeURIComponent(e.value)}&northing=${encodeURIComponent(n.value)}`);
      if (!response.ok) return;
      const data = await response.json();
      const matches = data.nearby.filter(item => item.id !== current).slice(0, 5);
      nearby.textContent = matches.length ? `Nearby saved locations: ${matches.map(item => `${item.name} (${item.distance_m} m)`).join(', ')}` : 'No saved locations nearby.';
      info.textContent = `Area: ${data.area?.name || 'No mapped area'} · Click the map to adjust the pin.`;
    } catch (_) { nearby.textContent = 'Could not check nearby locations; confirm before saving.'; }
  };
  e.addEventListener('change', refresh); n.addEventListener('change', refresh);
  try {
    map = await HwrtMap.create('saved-location-map', {skipPrints: true});
    map.map.on('click', event => {
      const p = map.fromLL(event.latlng);
      e.value = p.e.toFixed(3); n.value = p.n.toFixed(3); refresh();
    });
    refresh();
  } catch (error) { info.textContent = 'Map unavailable: enter MGA coordinates manually. ' + error.message; }
});
</script>
@endpush
