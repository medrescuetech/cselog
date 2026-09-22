@extends('layouts.app')
@section('title', 'Open board')

@section('content')
<div x-data="board()" x-init="init()">
  <div class="flex items-baseline gap-3 mb-3">
    <h1 class="text-2xl font-bold">OPEN (<span x-text="entries.length"></span>)</h1>
    <span class="text-xs text-slate-500">refreshed <span x-text="ago"></span></span>
    <div class="flex-1"></div>
    <a href="{{ route('map') }}" class="px-3 py-2 rounded bg-slate-800 hover:bg-slate-700 text-sm">Map</a>
  </div>

  <div x-show="!entries.length" class="text-slate-400 py-10 text-center text-lg">Nothing open.</div>

  <div class="grid gap-2">
    <template x-for="e in entries" :key="e.id">
      <div class="rounded-lg px-3 py-3 flex flex-wrap items-center gap-x-4 gap-y-1 border"
           :class="{ 'bg-red-900/50 border-red-700': e.band === 'red', 'bg-amber-900/40 border-amber-700': e.band === 'amber',
                     'bg-slate-800 border-slate-700': e.band === 'none', 'ring-2 ring-emerald-400': e.id === highlight }">
        <div class="font-mono text-2xl w-20" :class="e.band === 'red' ? 'animate-pulse' : ''" x-text="fmtElapsed((now - Date.parse(e.opened_at))/1000)"></div>
        <span class="inline-block w-3 h-3 rounded-full" :style="`background:${e.colour}`"></span>
        <div class="flex-1 min-w-[12rem]">
          <div class="font-semibold text-lg" x-text="e.location"></div>
          <div class="text-xs text-slate-300"><span x-text="e.type"></span> · <span x-text="e.area || '—'"></span>
            <template x-if="e.permit_no"><span> · permit <span x-text="e.permit_no"></span></span></template></div>
        </div>
        <div class="text-sm text-slate-300 flex-1 min-w-[10rem]" x-text="[e.notes, e.reported_by].filter(Boolean).join(' — ')"></div>
        <div class="text-xs text-slate-500" x-text="new Date(e.opened_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) + ' · ' + (e.opened_by || '')"></div>
        @if (auth()->user()->atLeast('logger'))
        <button @click="closing = e" class="px-4 py-2 rounded-lg bg-slate-700 hover:bg-emerald-600 font-semibold">Close</button>
        @endif
      </div>
    </template>
  </div>

  <div x-show="closing" x-cloak class="fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-4" @click.self="closing = null">
    <form method="post" :action="closing && `/entries/${closing.id}/close`" class="w-full max-w-md bg-slate-800 rounded-xl p-5 space-y-3">
      @csrf
      <h2 class="text-xl font-bold">Close <span x-text="closing?.location"></span></h2>
      <div class="text-sm text-slate-300">Open for <span x-text="closing && fmtElapsed((now - Date.parse(closing.opened_at))/1000)"></span></div>
      <textarea name="close_note" rows="2" placeholder="Note (optional)" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></textarea>
      <div class="grid grid-cols-2 gap-3">
        <button type="button" @click="closing = null" class="rounded-lg bg-slate-700 py-3">Cancel</button>
        <button class="rounded-lg bg-emerald-600 hover:bg-emerald-500 py-3 font-semibold">Close entry</button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
function board() {
  return {
    entries: @json($entries),
    now: Date.now(), last: Date.now(), ago: 'just now', closing: null, highlight: {{ (int) session('highlight', 0) }},
    init() {
      setInterval(() => { this.now = Date.now(); this.ago = Math.round((this.now - this.last) / 1000) + 's ago'; }, 1000);
      setInterval(() => this.refresh(), {{ config('csem.poll_seconds') }} * 1000);
      setTimeout(() => this.highlight = 0, 6000);
    },
    async refresh() {
      try { const j = await fetch('/api/open', { headers: { Accept: 'application/json' } }).then(r => r.json()); this.entries = j.entries; this.last = Date.now(); } catch (e) {}
    },
  };
}
</script>
@endpush
