@extends('layouts.app')
@section('title', 'Settings · Users')
@section('content')
<div class="space-y-6">
  <div class="flex flex-wrap items-center gap-3">
    <div><h1 class="text-2xl font-bold">Settings · Users</h1><p class="text-sm text-slate-400 mt-1">Manage accounts and access. Email is optional.</p></div>
    <div class="flex-1"></div>
    <a href="{{ route('settings.users.create') }}" class="rounded-lg bg-emerald-600 hover:bg-emerald-500 px-4 py-2 text-sm font-semibold">+ Add user</a>
    <a href="{{ route('settings.index') }}" class="rounded-lg bg-slate-800 hover:bg-slate-700 px-3 py-2 text-sm">Back to Settings</a>
  </div>
  <div class="overflow-x-auto rounded-xl border border-slate-700 bg-slate-800/70">
    <table class="w-full min-w-[900px] text-sm text-left">
      <thead class="bg-slate-900 text-slate-300 text-xs uppercase tracking-wide"><tr>
        <th scope="col" class="px-4 py-3">Name</th><th scope="col" class="px-4 py-3">Username</th><th scope="col" class="px-4 py-3">Email</th><th scope="col" class="px-4 py-3">Role</th><th scope="col" class="px-4 py-3">Status</th><th scope="col" class="px-4 py-3 text-right">Action</th>
      </tr></thead>
      <tbody class="divide-y divide-slate-700">
        @forelse ($users as $user)
          <tr class="{{ $user->active ? '' : 'text-slate-400' }}">
            <td class="px-4 py-3 font-medium">{{ $user->name }}@if ($user->id === auth()->id()) <span class="text-xs text-slate-400">(you)</span>@endif</td>
            <td class="px-4 py-3">{{ $user->username }}</td><td class="px-4 py-3">{{ $user->email ?: '—' }}</td><td class="px-4 py-3">{{ ucfirst($user->role) }}</td>
            <td class="px-4 py-3">{{ $user->active ? 'Active' : 'Inactive' }}@if ($user->must_change_password) <span class="block text-xs text-amber-300">Password change required</span>@endif</td>
            <td class="px-4 py-3 text-right"><a href="{{ route('settings.users.edit', $user) }}" class="inline-block rounded-lg bg-slate-700 hover:bg-slate-600 px-3 py-2 font-semibold">Edit</a></td>
          </tr>
        @empty
          <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">No users found.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <p class="text-sm text-slate-400"><b>User</b>: board, map, logging, Logbook and Reports. <b>Admin</b>: User access plus Settings and diagnostics.</p>
</div>
@endsection
