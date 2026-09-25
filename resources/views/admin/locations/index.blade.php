@extends('layouts.app')
@section('title', 'Manage Locations')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-bold">Location Catalogue Management</h1>
    <div class="flex gap-2">
      <a href="{{ route('admin.locations.export') }}" class="px-3 py-2 rounded bg-slate-800 hover:bg-slate-700 text-sm font-semibold">Export CSV</a>
      <form action="{{ route('admin.locations.import') }}" method="post" enctype="multipart/form-data" class="flex items-center gap-2">
        @csrf
        <input type="file" name="file" accept=".csv" class="text-xs text-slate-400 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:bg-slate-800 file:text-slate-200 hover:file:bg-slate-700">
        <button class="px-3 py-2 rounded bg-emerald-600 hover:bg-emerald-500 text-sm font-semibold">Import CSV</button>
      </form>
    </div>
  </div>

  @if (session('status'))
    <div class="bg-emerald-900/60 border border-emerald-700 rounded-lg p-3 text-sm">{{ session('status') }}</div>
  @endif

  <form method="get" action="{{ route('admin.locations.index') }}" class="flex gap-3 bg-slate-900 p-3 rounded-lg border border-slate-800">
    <select name="status" class="rounded bg-slate-800 border border-slate-700 px-3 py-2 text-sm">
      <option value="all" @selected($status === 'all')>All Statuses</option>
      <option value="active" @selected($status === 'active')>Active</option>
      <option value="unverified" @selected($status === 'unverified')>Unverified</option>
      <option value="archived" @selected($status === 'archived')>Archived</option>
    </select>
    <input name="q" value="{{ $q }}" placeholder="Search name or code…" class="flex-1 rounded bg-slate-800 border border-slate-700 px-3 py-2 text-sm">
    <button class="px-4 py-2 rounded bg-slate-700 hover:bg-slate-600 text-sm font-medium">Filter</button>
  </form>

  <div class="overflow-x-auto rounded-lg border border-slate-800">
    <table class="w-full text-left text-sm">
      <thead class="bg-slate-900 text-slate-400 border-b border-slate-800">
        <tr>
          <th class="p-3">Name</th>
          <th class="p-3">Coordinates</th>
          <th class="p-3">Area</th>
          <th class="p-3">Status</th>
          <th class="p-3">Usage</th>
          <th class="p-3 text-right">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-800 bg-slate-950">
        @forelse ($locations as $l)
          <tr>
            <td class="p-3">
              <div class="font-semibold">{{ $l->name }}</div>
              @if ($l->code) <div class="text-xs text-slate-400">Code: {{ $l->code }}</div> @endif
              @if ($l->mergedInto) <div class="text-xs text-amber-400">Merged into: {{ $l->mergedInto->name }}</div> @endif
            </td>
            <td class="p-3 font-mono text-xs text-slate-300">E{{ number_format($l->easting, 1) }} N{{ number_format($l->northing, 1) }}</td>
            <td class="p-3 text-slate-300">{{ $l->area?->name ?? '—' }}</td>
            <td class="p-3">
              @if ($l->status === 'archived')
                <span class="px-2 py-1 rounded bg-slate-800 text-xs text-slate-400">Archived</span>
              @elseif (!$l->verified)
                <span class="px-2 py-1 rounded bg-amber-900/60 border border-amber-700 text-xs text-amber-300">Unverified</span>
              @else
                <span class="px-2 py-1 rounded bg-emerald-900/60 border border-emerald-700 text-xs text-emerald-300">Verified</span>
              @endif
            </td>
            <td class="p-3 text-slate-300">{{ $l->usage_count }} times</td>
            <td class="p-3 text-right space-x-1">
              @if ($l->status === 'active')
                @if (!$l->verified)
                  <form action="{{ route('admin.locations.verify', $l) }}" method="post" class="inline">
                    @csrf
                    <button class="px-2 py-1 rounded bg-emerald-700 hover:bg-emerald-600 text-xs">Verify</button>
                  </form>
                @endif
                <form action="{{ route('admin.locations.archive', $l) }}" method="post" class="inline">
                  @csrf
                  <button class="px-2 py-1 rounded bg-slate-800 hover:bg-slate-700 text-xs text-slate-300">Archive</button>
                </form>
                <form action="{{ route('admin.locations.merge', $l) }}" method="post" class="inline-flex gap-1 items-center">
                  @csrf
                  <select name="target_id" class="rounded bg-slate-900 border border-slate-700 text-xs px-1 py-1">
                    <option value="">Merge into…</option>
                    @foreach ($allActive as $t)
                      @if ($t->id !== $l->id)
                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                      @endif
                    @endforeach
                  </select>
                  <button class="px-2 py-1 rounded bg-amber-700 hover:bg-amber-600 text-xs">Merge</button>
                </form>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="p-4 text-center text-slate-400">No locations found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div>{{ $locations->links() }}</div>
</div>
@endsection
