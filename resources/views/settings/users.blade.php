@extends('layouts.app')
@section('title', 'Settings · Users')

@section('content')
<div class="space-y-6">
  <div class="flex items-start gap-3">
    <div>
      <h1 class="text-2xl font-bold">Settings · Users</h1>
      <p class="text-sm text-slate-400 mt-1">Accounts use a unique username. Email is optional.</p>
    </div>
    <div class="flex-1"></div>
    <a href="{{ route('settings.index') }}" class="rounded-lg bg-slate-800 hover:bg-slate-700 px-3 py-2 text-sm">Back to Settings</a>
  </div>

  @if ($errors->any())
    <div class="rounded-lg border border-red-700 bg-red-950/60 p-4 text-sm text-red-100">
      <div class="font-semibold mb-1">Could not save the user.</div>
      <ul class="list-disc pl-5 space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
  @endif

  <section class="rounded-xl border border-slate-700 bg-slate-800/70 p-4">
    <h2 class="text-lg font-semibold mb-4">Add user</h2>
    <form method="post" action="{{ route('settings.users.store') }}" class="grid gap-3 lg:grid-cols-12">
      @csrf
      <label class="lg:col-span-3"><span class="block text-xs text-slate-400 mb-1">Display name</span>
        <input name="name" value="{{ old('name') }}" required maxlength="120" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
      <label class="lg:col-span-2"><span class="block text-xs text-slate-400 mb-1">Username</span>
        <input name="username" value="{{ old('username') }}" required maxlength="80" autocomplete="off" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
      <label class="lg:col-span-3"><span class="block text-xs text-slate-400 mb-1">Email (optional)</span>
        <input type="email" name="email" value="{{ old('email') }}" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
      <label class="lg:col-span-2"><span class="block text-xs text-slate-400 mb-1">Role</span>
        <select name="role" required class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2">
          @foreach ($roles as $role)<option value="{{ $role }}" @selected(old('role', 'user') === $role)>{{ ucfirst($role) }}</option>@endforeach
        </select></label>
      <label class="lg:col-span-2 flex items-center gap-2 self-end rounded-lg bg-slate-900 border border-slate-700 px-3 py-2">
        <input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked(old('active', '1') === '1')> Active
      </label>
      <label class="lg:col-span-4"><span class="block text-xs text-slate-400 mb-1">Password</span>
        <input type="password" name="password" required minlength="12" autocomplete="new-password" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
      <label class="lg:col-span-4"><span class="block text-xs text-slate-400 mb-1">Confirm password</span>
        <input type="password" name="password_confirmation" required minlength="12" autocomplete="new-password" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
      <div class="lg:col-span-4 flex items-end"><button class="w-full rounded-lg bg-emerald-600 hover:bg-emerald-500 px-4 py-2 font-semibold">Create user</button></div>
    </form>
  </section>

  <div class="rounded-lg border border-slate-700 bg-slate-800/50 p-3 text-sm text-slate-300">
    <b>User</b>: operational HWRT access including board, map, logging, Logbook and Reports. ·
    <b>Admin</b>: User access plus Settings and diagnostics.
  </div>

  <section class="space-y-3">
    @foreach ($users as $user)
      <form method="post" action="{{ route('settings.users.update', $user) }}" class="rounded-xl border {{ $user->active ? 'border-slate-700 bg-slate-800/70' : 'border-slate-800 bg-slate-900/60 opacity-75' }} p-4">
        @csrf @method('PATCH')
        <div class="grid gap-3 lg:grid-cols-12 items-end">
          <label class="lg:col-span-2"><span class="block text-xs text-slate-400 mb-1">Name</span>
            <input name="name" value="{{ $user->name }}" required class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
          <label class="lg:col-span-2"><span class="block text-xs text-slate-400 mb-1">Username</span>
            <input name="username" value="{{ $user->username }}" required class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
          <label class="lg:col-span-3"><span class="block text-xs text-slate-400 mb-1">Email (optional)</span>
            <input type="email" name="email" value="{{ $user->email }}" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
          <div class="lg:col-span-2">
            <span class="block text-xs text-slate-400 mb-1">Role</span>
            @if ($user->id === auth()->id())
              <input type="hidden" name="role" value="admin">
              <div class="rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2">Admin</div>
            @else
              <select name="role" required class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2">
                @foreach ($roles as $role)<option value="{{ $role }}" @selected($user->role === $role)>{{ ucfirst($role) }}</option>@endforeach
              </select>
            @endif
          </div>
          <label class="lg:col-span-2"><span class="block text-xs text-slate-400 mb-1">Set/reset password</span>
            <input type="password" name="password" minlength="12" autocomplete="new-password" placeholder="Unchanged" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
          <label class="lg:col-span-1 flex items-center gap-2">
            @if ($user->id === auth()->id())
              <input type="hidden" name="active" value="1"><input type="checkbox" checked disabled>
            @else
              <input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked($user->active)>
            @endif
            Active
          </label>
          <label class="lg:col-span-4"><span class="block text-xs text-slate-400 mb-1">Confirm new password</span>
            <input type="password" name="password_confirmation" minlength="12" autocomplete="new-password" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
          <div class="lg:col-span-2"><button class="w-full rounded-lg bg-slate-700 hover:bg-slate-600 px-3 py-2 font-semibold">Save</button></div>
        </div>
        <div class="mt-2 text-xs text-slate-500">User #{{ $user->id }}@if ($user->id === auth()->id()) · your account @endif @if (!$user->active) · inactive @endif · a reset password must be changed at next sign-in</div>
      </form>
    @endforeach
  </section>
</div>
@endsection
