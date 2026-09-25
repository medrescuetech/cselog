@extends('layouts.app')
@section('title', 'Logbook')

@section('content')
<div class="space-y-4">
  <div class="flex flex-wrap items-end gap-3">
    <div>
      <h1 class="text-2xl font-bold">Logbook</h1>
      <p class="text-sm text-slate-400 mt-1">Recent high risk work activity and status.</p>
    </div>
    <div class="flex-1"></div>
    <form method="get" class="flex items-end gap-2 text-sm">
      <label><span class="block text-xs text-slate-400 mb-1">Show</span>
        <select name="days" onchange="this.form.submit()" class="rounded bg-slate-800 border border-slate-700 px-3 py-2">
          @foreach ([3,7,14,30] as $d)<option value="{{ $d }}" @selected($days === $d)>Last {{ $d }} days</option>@endforeach
        </select>
      </label>
    </form>
  </div>

  <div class="overflow-x-auto rounded-xl border border-slate-700">
    <table class="w-full min-w-[980px] text-sm">
      <thead class="bg-slate-950 text-slate-300 text-left text-xs uppercase tracking-wide">
        <tr>
          <th class="p-3">HRW ID</th>
          <th class="p-3">Opened</th>
          <th class="p-3">Closed</th>
          <th class="p-3">Status</th>
          <th class="p-3">Type</th>
          <th class="p-3">Location</th>
          <th class="p-3">Area</th>
          <th class="p-3">Permit</th>
          <th class="p-3">Logged by</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-800">
      @forelse ($entries as $e)
        <tr class="{{ $e->status === 'open' ? 'bg-emerald-950/20' : 'hover:bg-slate-800/50' }}">
          <td class="p-3 font-mono font-semibold whitespace-nowrap">{{ $e->hrw_ref }}</td>
          <td class="p-3 whitespace-nowrap">{{ $e->opened_at->format('d M Y H:i') }}</td>
          <td class="p-3 whitespace-nowrap">{{ $e->closed_at?->format('d M Y H:i') ?? '—' }}</td>
          <td class="p-3"><span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $e->status === 'open' ? 'bg-emerald-900/60 text-emerald-200' : 'bg-slate-700 text-slate-200' }}">{{ ucfirst($e->status) }}</span></td>
          <td class="p-3"><span class="inline-block w-2 h-2 rounded-full mr-1" style="background: {{ $e->workType?->colour }}"></span>{{ $e->workType?->is_other && $e->other_description ? 'Other — '.$e->other_description : ($e->workType?->name ?? '—') }}</td>
          <td class="p-3 font-medium">{{ $e->location_label }}</td>
          <td class="p-3">{{ $e->area?->name ?? '—' }}</td>
          <td class="p-3 font-mono">{{ $e->permit_no ?: '—' }}</td>
          <td class="p-3">{{ $e->opener?->name ?? '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="9" class="p-8 text-center text-slate-400">No high risk work was logged in this period.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div>{{ $entries->links() }}</div>
</div>
@endsection
