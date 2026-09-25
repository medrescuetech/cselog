@extends('layouts.app')
@section('title', 'Reports')

@section('content')
<div class="space-y-4">
  <div>
    <h1 class="text-2xl font-bold">Reports</h1>
    <p class="text-sm text-slate-400 mt-1">Filter and export high risk work by date range, type, status or all records.</p>
  </div>

  <form method="get" class="grid gap-2 md:grid-cols-7 text-sm">
    <label><span class="block text-xs text-slate-400 mb-1">From</span><input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="w-full rounded bg-slate-800 border border-slate-700 px-2 py-2"></label>
    <label><span class="block text-xs text-slate-400 mb-1">To</span><input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="w-full rounded bg-slate-800 border border-slate-700 px-2 py-2"></label>
    <label><span class="block text-xs text-slate-400 mb-1">Status</span>
      <select name="status" class="w-full rounded bg-slate-800 border border-slate-700 px-2 py-2">
        <option value="">All statuses</option>
        @foreach (['open','closed','cancelled'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>@endforeach
      </select>
    </label>
    <label><span class="block text-xs text-slate-400 mb-1">Type</span>
      <select name="work_type_id" class="w-full rounded bg-slate-800 border border-slate-700 px-2 py-2">
        <option value="">All types</option>
        @foreach ($workTypes as $type)<option value="{{ $type->id }}" @selected(($filters['work_type_id'] ?? '') == $type->id)>{{ $type->name }}</option>@endforeach
      </select>
    </label>
    <label><span class="block text-xs text-slate-400 mb-1">Area</span>
      <select name="area_id" class="w-full rounded bg-slate-800 border border-slate-700 px-2 py-2">
        <option value="">All areas</option>
        @foreach ($areas as $area)<option value="{{ $area->id }}" @selected(($filters['area_id'] ?? '') == $area->id)>{{ $area->name }}</option>@endforeach
      </select>
    </label>
    <label><span class="block text-xs text-slate-400 mb-1">Search</span><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="HRW ID / location / permit / notes" class="w-full rounded bg-slate-800 border border-slate-700 px-2 py-2"></label>
    <div class="flex items-end gap-2">
      <button class="flex-1 rounded bg-slate-700 hover:bg-slate-600 px-3 py-2">Run</button>
      <a href="{{ route('reports.csv', request()->query()) }}" class="rounded bg-emerald-700 hover:bg-emerald-600 px-3 py-2 font-semibold">CSV</a>
    </div>
  </form>

  <div class="grid gap-3 sm:grid-cols-4">
    @foreach (['total' => 'Total', 'open' => 'Open', 'closed' => 'Closed', 'cancelled' => 'Cancelled'] as $key => $label)
      <div class="rounded-xl border border-slate-700 bg-slate-800/70 p-4">
        <div class="text-xs uppercase tracking-wide text-slate-500">{{ $label }}</div>
        <div class="text-3xl font-bold">{{ number_format($summary[$key]) }}</div>
      </div>
    @endforeach
  </div>

  <div class="overflow-x-auto rounded-xl border border-slate-700">
    <table class="w-full min-w-[1100px] text-sm">
      <thead class="bg-slate-950 text-slate-300 text-left text-xs uppercase tracking-wide">
        <tr><th class="p-3">HRW ID</th><th class="p-3">Opened</th><th class="p-3">Closed</th><th class="p-3">Status</th><th class="p-3">Type</th><th class="p-3">Location</th><th class="p-3">Area</th><th class="p-3">Permit</th><th class="p-3">Notes</th><th class="p-3">Logged by</th></tr>
      </thead>
      <tbody class="divide-y divide-slate-800">
      @forelse ($entries as $e)
        <tr class="hover:bg-slate-800/50 align-top">
          <td class="p-3 font-mono font-semibold whitespace-nowrap">{{ $e->hrw_ref }}</td>
          <td class="p-3 whitespace-nowrap">{{ $e->opened_at->format('d M Y H:i') }}</td>
          <td class="p-3 whitespace-nowrap">{{ $e->closed_at?->format('d M Y H:i') ?? '—' }}</td>
          <td class="p-3">{{ ucfirst($e->status) }}</td>
          <td class="p-3">{{ $e->workType?->is_other && $e->other_description ? 'Other — '.$e->other_description : ($e->workType?->name ?? '—') }}</td>
          <td class="p-3 font-medium">{{ $e->location_label }}</td>
          <td class="p-3">{{ $e->area?->name ?? '—' }}</td>
          <td class="p-3 font-mono">{{ $e->permit_no ?: '—' }}</td>
          <td class="p-3 text-slate-300">{{ $e->notes ?: '—' }}</td>
          <td class="p-3">{{ $e->opener?->name ?? '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="10" class="p-8 text-center text-slate-400">No records match these filters.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div>{{ $entries->links() }}</div>
</div>
@endsection
