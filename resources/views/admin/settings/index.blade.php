@extends('layouts.app')

@section('title', 'System Settings & RBAC Management')

@section('content')
<div class="space-y-6">
  <div class="flex items-center justify-between border-b border-slate-800 pb-3">
    <div>
      <h1 class="text-xl font-bold">System Settings & Role-Based Access Control (RBAC)</h1>
      <p class="text-xs text-slate-400">Configure function access levels, manage users, and customize work types.</p>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- User RBAC Access Configuration -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 space-y-4">
      <h2 class="font-semibold text-lg border-b border-slate-800 pb-2">User Access & Roles</h2>
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-slate-950 text-slate-400 text-xs">
            <tr>
              <th class="p-2">User</th>
              <th class="p-2">Email</th>
              <th class="p-2">Current Role</th>
              <th class="p-2">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            @foreach ($users as $u)
              <tr>
                <td class="p-2 font-medium">{{ $u->name }}</td>
                <td class="p-2 text-slate-400">{{ $u->email }}</td>
                <td class="p-2">
                  <span class="inline-block px-2 py-0.5 rounded text-xs font-semibold bg-slate-800 border border-slate-700">
                    {{ $u->role }}
                  </span>
                </td>
                <td class="p-2">
                  <form action="{{ route('admin.settings.users.role', $u) }}" method="post" class="flex items-center gap-1">
                    @csrf
                    <select name="role" class="bg-slate-950 border border-slate-700 rounded px-2 py-1 text-xs">
                      @foreach ($roles as $r)
                        <option value="{{ $r }}" {{ $u->role === $r ? 'selected' : '' }}>{{ $r }}</option>
                      @endforeach
                    </select>
                    <button type="submit" class="px-2 py-1 bg-sky-600 hover:bg-sky-500 rounded text-xs text-white font-medium">Update</button>
                  </form>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <div class="bg-slate-950 border border-slate-800 rounded-lg p-3 text-xs text-slate-400 space-y-1">
        <div class="font-semibold text-slate-200 mb-1">Access Level Key:</div>
        <div><strong class="text-slate-300">map_only:</strong> View only (just the interactive map and map endpoints).</div>
        <div><strong class="text-slate-300">viewer:</strong> Read only (map, open board, history, notes, location search).</div>
        <div><strong class="text-slate-300">logger:</strong> Normal user (create log entries, close entries, add ad-hoc locations).</div>
        <div><strong class="text-slate-300">supervisor:</strong> Supervisor (verify, archive, merge, import/export location catalogue).</div>
        <div><strong class="text-slate-300">admin:</strong> Full administrator (system settings, RBAC role assignment, work type management).</div>
      </div>
    </div>

    <!-- Work Types Management -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl p-4 space-y-4">
      <h2 class="font-semibold text-lg border-b border-slate-800 pb-2">Work Types Configuration</h2>
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="bg-slate-950 text-slate-400 text-xs">
            <tr>
              <th class="p-2">Name</th>
              <th class="p-2">Colour</th>
              <th class="p-2">Status</th>
              <th class="p-2">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            @foreach ($workTypes as $wt)
              <tr>
                <td class="p-2 font-medium">
                  <span class="inline-block w-3 h-3 rounded-full mr-1" style="background-color: {{ $wt->colour }}"></span>
                  {{ $wt->name }}
                  @if ($wt->is_default) <span class="text-xs text-sky-400 font-normal">(default)</span> @endif
                </td>
                <td class="p-2 text-slate-400 text-xs font-mono">{{ $wt->colour }}</td>
                <td class="p-2">
                  <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $wt->active ? 'bg-emerald-950 text-emerald-400 border border-emerald-800' : 'bg-slate-800 text-slate-400' }}">
                    {{ $wt->active ? 'Active' : 'Inactive' }}
                  </span>
                </td>
                <td class="p-2">
                  <form action="{{ route('admin.settings.worktypes.toggle', $wt) }}" method="post">
                    @csrf
                    <button type="submit" class="px-2 py-1 rounded text-xs font-medium {{ $wt->active ? 'bg-amber-800 hover:bg-amber-700 text-white' : 'bg-slate-800 hover:bg-slate-700 text-slate-200' }}">
                      {{ $wt->active ? 'Deactivate' : 'Activate' }}
                    </button>
                  </form>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <!-- Add New Work Type -->
      <form action="{{ route('admin.settings.worktypes.store') }}" method="post" class="bg-slate-950 p-3 rounded-lg border border-slate-800 space-y-3">
        @csrf
        <h3 class="text-xs font-semibold text-slate-300 uppercase tracking-wider">Add New Work Type</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
          <input type="text" name="name" placeholder="Work type name" required class="bg-slate-900 border border-slate-700 rounded px-2 py-1 text-xs text-white">
          <input type="text" name="colour" placeholder="#ff0000" required class="bg-slate-900 border border-slate-700 rounded px-2 py-1 text-xs text-white">
        </div>
        <div class="flex items-center justify-between">
          <label class="flex items-center gap-2 text-xs text-slate-300">
            <input type="checkbox" name="requires_note" value="1" class="rounded bg-slate-900 border-slate-700"> Requires Note
          </label>
          <button type="submit" class="px-3 py-1 bg-emerald-600 hover:bg-emerald-500 rounded text-xs text-white font-medium">+ Add Type</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
