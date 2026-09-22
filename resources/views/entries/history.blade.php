@extends('layouts.app')
@section('title', 'History')

@section('content')
<h1 class="text-2xl font-bold mb-3">History</h1>
<form method="get" class="grid grid-cols-2 md:grid-cols-7 gap-2 mb-4 text-sm">
  <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="rounded bg-slate-800 border border-slate-700 px-2 py-2">
  <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="rounded bg-slate-800 border border-slate-700 px-2 py-2">
  <select name="status" class="rounded bg-slate-800 border border-slate-700 px-2 py-2">
    <option value="">Any status</option>
    @foreach (['open', 'closed', 'cancelled'] as $s) <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ ucfirst($s) }}</option> @endforeach
  </select>
  <select name="work_type_id" class="rounded bg-slate-800 border border-slate-700 px-2 py-2">
    <option value="">Any type</option>
    @foreach ($workTypes as $t) <option value="{{ $t->id }}" @selected(($filters['work_type_id'] ?? '') == $t->id)>{{ $t->name }}</option> @endforeach
  </select>
  <select name="area_id" class="rounded bg-slate-800 border border-slate-700 px-2 py-2">
    <option value="">Any area</option>
    @foreach ($areas as $a) <option value="{{ $a->id }}" @selected(($filters['area_id'] ?? '') == $a->id)>{{ $a->name }}</option> @endforeach
  </select>
  <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Location / notes / permit" class="rounded bg-slate-800 border border-slate-700 px-2 py-2">
  <div class="flex gap-2">
    <button class="flex-1 rounded bg-slate-700 hover:bg-slate-600 px-3 py-2">Filter</button>
    <a href="{{ route('history.csv', request()->query()) }}" class="rounded bg-slate-800 hover:bg-slate-700 px-3 py-2" title="Export CSV">CSV</a>
  </div>
</form>

<div class="overflow-x-auto rounded-lg border border-slate-800">
<table class="w-full text-sm">
  <thead class="bg-slate-800 text-slate-300 text-left">
    <tr><th class="p-2">Opened</th><th class="p-2">Closed</th><th class="p-2">Duration</th><th class="p-2">Location</th><th class="p-2">Type</th><th class="p-2">Area</th><th class="p-2">Notes</th><th class="p-2">Logged by</th><th class="p-2">Closed by</th></tr>
  </thead>
  <tbody>
  @forelse ($entries as $e)
    <tr class="border-t border-slate-800 hover:bg-slate-800/60 align-top" x-data="{ open: false }" @click="open = !open">
      <td class="p-2 whitespace-nowrap">{{ $e->opened_at->format('d M H:i') }}</td>
      <td class="p-2 whitespace-nowrap">{{ $e->closed_at?->format('d M H:i') ?? '—' }}</td>
      <td class="p-2 font-mono">{{ $e->status === 'open' ? 'open' : gmdate('G:i', $e->elapsedSeconds()) }}</td>
      <td class="p-2 font-medium">{{ $e->location_label }}@if (! $e->location_id) <span class="text-[10px] uppercase text-slate-400">ad-hoc</span>@endif
        <div x-show="open" x-cloak class="text-xs text-slate-400 font-normal mt-1 space-y-0.5">
          <div>E {{ number_format($e->easting, 1) }} N {{ number_format($e->northing, 1) }}@if ($e->permit_no) · permit {{ $e->permit_no }}@endif</div>
          @foreach ($e->events as $ev)
            <div>{{ $ev->occurred_at->format('d M H:i:s') }} — {{ $ev->event }} by {{ $ev->actor?->name ?? '—' }}@if ($ev->changes) <span class="text-slate-500">{{ json_encode($ev->changes) }}</span>@endif</div>
          @endforeach
        </div></td>
      <td class="p-2"><span class="inline-block w-2 h-2 rounded-full mr-1" style="background: {{ $e->workType->colour }}"></span>{{ $e->workType->name }}</td>
      <td class="p-2">{{ $e->area?->name ?? '—' }}</td>
      <td class="p-2 text-slate-300">{{ $e->notes }}@if ($e->close_note) <div class="text-slate-500">close: {{ $e->close_note }}</div>@endif</td>
      <td class="p-2 whitespace-nowrap">{{ $e->opener?->name }}</td>
      <td class="p-2 whitespace-nowrap">{{ $e->closer?->name ?? '—' }}</td>
    </tr>
  @empty
    <tr><td colspan="9" class="p-6 text-center text-slate-400">No entries match.</td></tr>
  @endforelse
  </tbody>
</table>
</div>
<div class="mt-3">{{ $entries->links() }}</div>
@endsection
