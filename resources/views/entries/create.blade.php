@extends('layouts.app')
@section('title', 'Log high risk work')
@push('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="/js/hwrt-map.js"></script>
@endpush

@section('content')
<form method="post" action="{{ route('entries.store') }}" class="max-w-xl mx-auto space-y-5" x-data="logForm(@js($workTypes->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'is_other' => $t->is_other, 'requires_note' => $t->requires_note, 'notes_prompt' => $t->notes_prompt])->values()))" x-init="init()">
  @csrf
  <div class="flex items-baseline justify-between">
    <h1 class="text-2xl font-bold">Log high risk work</h1>
    <button type="button" @click="late = !late" class="font-mono text-2xl text-slate-300 hover:text-white" title="Tap to log a late entry">
      <span x-text="clock"></span> <span class="text-sm text-slate-500" x-show="!late">now</span>
    </button>
  </div>

  @if ($errors->any())
    <div class="bg-red-900/60 border border-red-700 rounded-lg p-3 text-sm">
      @foreach ($errors->all() as $e) <div>{{ $e }}</div> @endforeach
    </div>
  @endif

  <div x-show="late" x-cloak class="bg-slate-800 rounded-lg p-3 space-y-2">
    <label class="block text-sm text-slate-300">Logged late — actual time was
      <input type="datetime-local" name="opened_at" x-model="openedAt" :disabled="!late" class="mt-1 w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
    <input name="late_reason" placeholder="Reason (e.g. radio traffic)" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2">
  </div>

  <label class="block"><span class="text-sm text-slate-300">High risk work type</span>
    <select name="work_type_id" x-model.number="workTypeId" class="mt-1 w-full rounded-lg bg-slate-800 border border-slate-700 px-3 py-3 text-lg">
      @foreach ($workTypes as $t)
        <option value="{{ $t->id }}" @selected(old('work_type_id', $defaultType?->id) == $t->id)>{{ $t->name }}</option>
      @endforeach
    </select></label>

  <label class="block" x-show="selectedType()?.is_other" x-cloak>
    <span class="text-sm text-slate-300">Describe the high risk work</span>
    <input name="other_description" value="{{ old('other_description') }}" :required="selectedType()?.is_other"
           class="mt-1 w-full rounded-lg bg-slate-800 border border-slate-700 px-3 py-3"
           placeholder="e.g. pressure testing / lifting operation">
  </label>

  {{-- Location picker --}}
  <div>
    <span class="text-sm text-slate-300">Location</span>
    <input type="hidden" name="location_id" :value="picked?.id || ''">
    <input type="hidden" name="location_label" :value="picked ? '' : adhoc.label">
    <input type="hidden" name="easting" :value="picked ? '' : adhoc.e">
    <input type="hidden" name="northing" :value="picked ? '' : adhoc.n">

    <template x-if="picked || adhoc.e">
      <div class="mt-1 flex items-center gap-3 rounded-lg bg-emerald-900/50 border border-emerald-700 px-3 py-3">
        <div class="flex-1">
          <div class="font-semibold text-lg" x-text="picked ? picked.name : adhoc.label"></div>
          <div class="text-xs text-slate-300" x-text="picked ? (picked.area || '') : (adhoc.area ? adhoc.area + ' · ' : '') + 'ad-hoc pin, not saved to catalogue'"></div>
        </div>
        <button type="button" @click="picked = null; adhoc = {}" class="text-slate-300 hover:text-white px-2 py-2">change</button>
      </div>
    </template>

    <div x-show="!picked && !adhoc.e" class="mt-1 space-y-2">
      <input type="search" x-model="q" @input.debounce.200ms="search()" placeholder="Search locations…" autocomplete="off"
             class="w-full rounded-lg bg-slate-800 border border-slate-700 px-3 py-3 text-lg">
      <div class="text-xs text-slate-500 px-1" x-text="q ? 'Matches' : 'Recent'"></div>
      <div class="grid gap-1">
        <template x-for="l in results" :key="l.id">
          <button type="button" @click="picked = l" class="text-left rounded-lg bg-slate-800 hover:bg-slate-700 px-3 py-3 flex items-center gap-2">
            <span class="flex-1"><span class="font-medium" x-text="l.name"></span>
              <span class="text-xs text-slate-400 ml-2" x-text="l.area || ''"></span></span>
            <span x-show="!l.verified" class="text-[10px] uppercase text-amber-400">unverified</span>
          </button>
        </template>
        <div x-show="q && !results.length" class="text-slate-400 text-sm px-1">No match — drop a pin below.</div>
      </div>
      <button type="button" @click="openPin()" class="w-full rounded-lg border-2 border-dashed border-slate-600 hover:border-slate-400 px-3 py-3 text-lg">＋ New location (drop a pin)</button>
    </div>
  </div>

  <label class="block"><span class="text-sm text-slate-300">Notes <span x-show="selectedType()?.requires_note" class="text-amber-400">(required)</span></span>
    <textarea name="notes" rows="3" :required="selectedType()?.requires_note"
              :placeholder="selectedType()?.notes_prompt || 'High risk work notes…'"
              class="mt-1 w-full rounded-lg bg-slate-800 border border-slate-700 px-3 py-3">{{ old('notes') }}</textarea>
    <div class="mt-1 text-xs text-slate-500" x-show="selectedType()?.notes_prompt" x-text="selectedType()?.notes_prompt"></div>
  </label>
  <div class="grid grid-cols-2 gap-3">
    <label class="block"><span class="text-sm text-slate-300">Permit no.</span>
      <input name="permit_no" value="{{ old('permit_no') }}" class="mt-1 w-full rounded-lg bg-slate-800 border border-slate-700 px-3 py-3"></label>
    <label class="block"><span class="text-sm text-slate-300">Reported by</span>
      <input name="reported_by" value="{{ old('reported_by') }}" placeholder="Ch.2 – Dave" class="mt-1 w-full rounded-lg bg-slate-800 border border-slate-700 px-3 py-3"></label>
  </div>

  <button :disabled="!picked && !adhoc.e" class="w-full rounded-xl bg-red-600 hover:bg-red-500 disabled:opacity-40 py-4 text-xl font-bold">SUBMIT HIGH RISK WORK</button>

  {{-- S2: pin drop --}}
  <div x-show="pinOpen" x-cloak class="fixed inset-0 z-50 bg-slate-950 flex flex-col">
    <div class="flex items-center gap-3 p-3 bg-slate-900 border-b border-slate-800">
      <div class="flex-1 font-semibold">Tap the map to place the pin, drag to adjust</div>
      <div class="font-mono text-xs text-slate-400" x-text="pin.e ? `E ${pin.e.toFixed(1)} N ${pin.n.toFixed(1)}` : ''"></div>
      <button type="button" @click="pinOpen = false" class="px-3 py-2 rounded bg-slate-800">Cancel</button>
    </div>
    <div id="pinmap" class="flex-1"></div>
    <div x-show="pin.e" class="p-3 bg-slate-900 border-t border-slate-800 space-y-3">
      <div x-show="pin.nearby.length" class="rounded-lg bg-amber-900/50 border border-amber-700 p-3 text-sm">
        This is close to <template x-for="nb in pin.nearby.slice(0,2)"><span>
          <button type="button" @click="usePin(nb)" class="underline font-semibold" x-text="nb.name"></button>
          <span class="text-slate-300" x-text="`(${nb.distance_m} m) `"></span></span></template>
        — use that instead?
      </div>
      <div class="text-sm text-slate-300">Area: <span class="font-medium" x-text="pin.area || '—'"></span></div>
      <div class="font-semibold">Save this location for next time?</div>
      <input x-model="pin.name" placeholder="Name, e.g. Sump 4 – north wall" class="w-full rounded-lg bg-slate-800 border border-slate-700 px-3 py-3 text-lg">
      <p class="text-red-400 text-sm" x-show="pin.error" x-text="pin.error"></p>
      <div class="grid grid-cols-2 gap-3">
        <button type="button" @click="adhocPin()" class="rounded-lg bg-slate-700 hover:bg-slate-600 py-3">No, just this once</button>
        <button type="button" @click="savePin()" :disabled="!pin.name" class="rounded-lg bg-emerald-600 hover:bg-emerald-500 disabled:opacity-40 py-3 font-semibold">Yes, save</button>
      </div>
    </div>
  </div>
</form>
@endsection

@push('scripts')
<script>
function logForm(types) {
  return {
    types,
    workTypeId: {{ (int) old('work_type_id', $defaultType?->id ?? 0) }},
    selectedType() { return this.types.find(t => Number(t.id) === Number(this.workTypeId)) || null; },
    clock: '', late: false, openedAt: '',
    q: '', results: [], picked: null, adhoc: {},
    pinOpen: false, pin: { e: null, n: null, name: '', area: null, nearby: [], error: '' }, mapObj: null, marker: null,
    init() {
      const tick = () => { const d = new Date(); this.clock = d.toTimeString().slice(0, 5); if (!this.late) this.openedAt = new Date(d - d.getTimezoneOffset() * 6e4).toISOString().slice(0, 16); };
      tick(); setInterval(tick, 1000);
      this.search();
    },
    async search() { this.results = await fetch('/api/locations?q=' + encodeURIComponent(this.q)).then(r => r.json()); },
    async openPin() {
      this.pinOpen = true; this.pin = { e: null, n: null, name: this.q, area: null, nearby: [], error: '' };
      await this.$nextTick();
      if (!this.mapObj) {
        this.mapObj = await HwrtMap.create('pinmap', { skipPrints: true });
        this.mapObj.map.on('click', ev => this.place(this.mapObj.fromLL(ev.latlng)));
      } else { this.mapObj.map.invalidateSize(); }
      if (this.marker) { this.marker.remove(); this.marker = null; }
    },
    async place({ e, n }) {
      this.pin.e = e; this.pin.n = n;
      const ll = this.mapObj.toLL(e, n);
      if (!this.marker) {
        this.marker = L.marker(ll, { draggable: true }).addTo(this.mapObj.map);
        this.marker.on('dragend', () => this.place(this.mapObj.fromLL(this.marker.getLatLng())));
      } else this.marker.setLatLng(ll);
      const r = await fetch(`/api/locations/nearby?easting=${e}&northing=${n}`).then(r => r.json());
      this.pin.area = r.area?.name || null; this.pin.nearby = r.nearby;
    },
    usePin(nb) { this.picked = { id: nb.id, name: nb.name, area: this.pin.area }; this.pinOpen = false; },
    adhocPin() {
      this.adhoc = { label: this.pin.name || `Pin E${Math.round(this.pin.e)} N${Math.round(this.pin.n)}`, e: this.pin.e, n: this.pin.n, area: this.pin.area };
      this.pinOpen = false;
    },
    async savePin() {
      this.pin.error = '';
      const r = await fetch('/api/locations', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.csrf, Accept: 'application/json' },
        body: JSON.stringify({ name: this.pin.name, easting: this.pin.e, northing: this.pin.n }) });
      const j = await r.json();
      if (!r.ok) { this.pin.error = j.message || 'Could not save'; return; }
      this.picked = { id: j.id, name: j.name, area: j.area?.name }; this.pinOpen = false;
    },
  };
}
</script>
@endpush
