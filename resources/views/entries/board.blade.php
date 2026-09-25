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

  <div class="overflow-x-auto rounded-xl border border-slate-700 bg-slate-900/40">
    <table class="w-full min-w-[1120px] border-collapse text-sm">
      <thead class="bg-slate-950 text-slate-300 text-xs uppercase tracking-wide">
        <tr>
          <th scope="col" class="px-3 py-3 text-left w-28">HRW ID</th>
          <th scope="col" class="px-3 py-3 text-left w-24">Elapsed</th>
          <th scope="col" class="px-3 py-3 text-left w-40">Type</th>
          <th scope="col" class="px-3 py-3 text-left min-w-[14rem]">Location</th>
          <th scope="col" class="px-3 py-3 text-left w-44">Area</th>
          <th scope="col" class="px-3 py-3 text-left w-28">Permit</th>
          <th scope="col" class="px-3 py-3 text-left min-w-[15rem]">Notes / Reported by</th>
          <th scope="col" class="px-3 py-3 text-left w-24">Opened</th>
          <th scope="col" class="px-3 py-3 text-left w-36">Opened by</th>
          <th scope="col" class="px-3 py-3 text-right w-24">Action</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-700/80">
        <tr x-show="!entries.length">
          <td colspan="10" class="px-3 py-10 text-center text-slate-400 text-lg">
            Nothing open.
          </td>
        </tr>

        <template x-for="e in entries" :key="e.id">
          <tr
            :class="{
              'bg-red-900/45': e.band === 'red',
              'bg-amber-900/35': e.band === 'amber',
              'bg-slate-800/50': e.band === 'none',
              'ring-2 ring-inset ring-emerald-400': e.id === highlight
            }">
            <td class="px-3 py-3 align-top font-mono font-semibold whitespace-nowrap" x-text="e.hrw_ref"></td>
            <td class="px-3 py-3 align-top">
              <div class="font-mono text-xl whitespace-nowrap"
                   :class="e.band === 'red' ? 'animate-pulse text-red-100' : ''"
                   x-text="fmtElapsed((now - Date.parse(e.opened_at))/1000)"></div>
            </td>
            <td class="px-3 py-3 align-top">
              <div class="flex items-center gap-2">
                <span class="inline-block w-3 h-3 rounded-full shrink-0" :style="`background:${e.colour}`"></span>
                <span class="font-medium" x-text="e.type_display || e.type"></span>
              </div>
            </td>
            <td class="px-3 py-3 align-top">
              <div class="font-semibold text-base" x-text="e.location"></div>
            </td>
            <td class="px-3 py-3 align-top text-slate-300" x-text="e.area || '—'"></td>
            <td class="px-3 py-3 align-top font-mono text-slate-300" x-text="e.permit_no || '—'"></td>
            <td class="px-3 py-3 align-top text-slate-300">
              <div x-text="e.notes || '—'"></div>
              <template x-if="e.reported_by">
                <div class="mt-1 text-xs text-slate-500">Reported by <span x-text="e.reported_by"></span></div>
              </template>
            </td>
            <td class="px-3 py-3 align-top text-slate-300 whitespace-nowrap"
                x-text="new Date(e.opened_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})"></td>
            <td class="px-3 py-3 align-top text-slate-300" x-text="e.opened_by || '—'"></td>
            <td class="px-3 py-3 align-top text-right">
              <button @click="closing = e"
                      class="px-4 py-2 rounded-lg bg-slate-700 hover:bg-emerald-600 font-semibold">
                Close
              </button>
            </td>
          </tr>
        </template>
      </tbody>
    </table>
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
      setInterval(() => this.refresh(), {{ config('hwrt.poll_seconds') }} * 1000);
      setTimeout(() => this.highlight = 0, 6000);
    },
    async refresh() {
      try {
        const response = await fetch('/api/open', { headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error(`Open board refresh failed (HTTP ${response.status})`);
        const j = await response.json();
        this.entries = j.entries;
        this.last = Date.now();
      } catch (error) {
        console.error('CSEM board refresh failed', error);
        window.hwrtReportError?.('board-refresh', error.message || 'Open board refresh failed', { stack: error.stack || null });
      }
    },
  };
}
</script>
@endpush
