@extends('layouts.app')
@section('title', 'Pending work')

@section('content')
<div class="space-y-4">
  <div class="flex flex-wrap items-end gap-3">
    <div>
      <h1 class="text-2xl font-bold">Pending high risk work</h1>
      <p class="text-sm text-slate-400 mt-1">Scheduled in advance. All times are Australia/Perth.</p>
    </div>
    <div class="flex-1"></div>
    <a href="{{ route('entries.create') }}" class="rounded-lg bg-red-600 hover:bg-red-500 px-4 py-2 font-semibold">+ Log / schedule work</a>
  </div>

  <div class="overflow-x-auto rounded-xl border border-slate-700">
    <table class="w-full min-w-[1050px] text-sm">
      <thead class="bg-slate-950 text-slate-300 text-left text-xs uppercase tracking-wide">
        <tr>
          <th class="p-3">HRW ID</th>
          <th class="p-3">Planned start</th>
          <th class="p-3">Type</th>
          <th class="p-3">Location</th>
          <th class="p-3">Area</th>
          <th class="p-3">Permit</th>
          <th class="p-3">Notes</th>
          <th class="p-3">Scheduled by</th>
          <th class="p-3 text-right">Action</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-800">
      @forelse ($entries as $e)
        @php($today = $e->planned_start_at?->isToday())
        <tr class="{{ $today ? 'bg-amber-950/30' : 'hover:bg-slate-800/50' }}">
          <td class="p-3 font-mono font-semibold whitespace-nowrap">{{ $e->hrw_ref }}</td>
          <td class="p-3 whitespace-nowrap">
            {{ $e->planned_start_at?->format('D d M Y H:i') ?? '—' }}
            @if($today)<div class="text-xs text-amber-400">Today</div>@endif
          </td>
          <td class="p-3">{{ $e->workType?->is_other && $e->other_description ? 'Other — '.$e->other_description : ($e->workType?->name ?? '—') }}</td>
          <td class="p-3 font-medium">{{ $e->location_label }}</td>
          <td class="p-3">{{ $e->area?->name ?? '—' }}</td>
          <td class="p-3 font-mono">{{ $e->permit_no ?: '—' }}</td>
          <td class="p-3">{{ $e->notes ?: '—' }}</td>
          <td class="p-3">{{ $e->opener?->name ?? '—' }}</td>
          <td class="p-3">
            <div class="flex justify-end gap-2">
              <form method="post" action="{{ route('entries.start', $e) }}">@csrf
                <button class="rounded-lg bg-emerald-600 hover:bg-emerald-500 px-3 py-2 font-semibold">Start</button>
              </form>
              <form method="post" action="{{ route('entries.cancel', $e) }}" onsubmit="return confirm('Cancel {{ $e->hrw_ref }}?');">@csrf
                <button class="rounded-lg bg-slate-700 hover:bg-red-700 px-3 py-2">Cancel</button>
              </form>
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="9" class="p-8 text-center text-slate-400">No pending high risk work.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div>{{ $entries->links() }}</div>
</div>
@endsection
