@extends('layouts.app')
@section('title', $user ? 'Edit user' : 'Add user')
@section('content')
@php($editing = $user !== null)
<div class="max-w-3xl mx-auto space-y-6">
  <div class="flex flex-wrap items-center gap-3"><h1 class="text-2xl font-bold">{{ $editing ? 'Edit user · '.$user->name : 'Add user' }}</h1><div class="flex-1"></div><a href="{{ route('settings.users.index') }}" class="rounded-lg bg-slate-800 hover:bg-slate-700 px-3 py-2 text-sm">Back to Users</a></div>
  @if ($errors->any())
    <div class="rounded-lg border border-red-700 bg-red-950/60 p-4 text-sm text-red-100" role="alert"><div class="font-semibold mb-1">Could not save the user.</div><ul class="list-disc pl-5 space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
  @endif
  <form method="post" action="{{ $editing ? route('settings.users.update', $user) : route('settings.users.store') }}" class="rounded-xl border border-slate-700 bg-slate-800/70 p-5 space-y-5">
    @csrf
    @if ($editing) @method('PATCH') @endif
    <div class="grid gap-4 md:grid-cols-2">
      <label><span class="block text-sm text-slate-300 mb-1">Display name</span><input name="name" value="{{ old('name', $user?->name) }}" required maxlength="120" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
      <label><span class="block text-sm text-slate-300 mb-1">Username</span><input name="username" value="{{ old('username', $user?->username) }}" required minlength="3" maxlength="80" autocomplete="off" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
      <label class=""><span class="block text-sm text-slate-300 mb-1">Email (optional)</span><input type="email" name="email" value="{{ old('email', $user?->email) }}" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
      <label><span class="block text-sm text-slate-300 mb-1">Role</span>
        @if ($editing && $user->id === auth()->id())
          <input type="hidden" name="role" value="admin"><span class="block rounded-lg bg-slate-900 border border-slate-700 px-3 py-2">Admin</span>
        @else
          <select name="role" required class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2">@foreach ($roles as $role)<option value="{{ $role }}" @selected(old('role', $user?->role ?? 'user') === $role)>{{ ucfirst($role) }}</option>@endforeach</select>
        @endif
      </label>
      <label class="flex items-center gap-2 self-end rounded-lg bg-slate-900 border border-slate-700 px-3 py-2">
        @if ($editing && $user->id === auth()->id())
          <input type="hidden" name="active" value="1"><input type="checkbox" checked disabled>
        @else
          <input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked(old('active', $editing ? ($user->active ? '1' : '0') : '1') == '1')>
        @endif
        Active
      </label>
      <label><span class="block text-sm text-slate-300 mb-1">{{ $editing ? 'Set/reset password (leave blank to keep)' : 'Password' }}</span><input type="password" name="password" @required(!$editing) minlength="12" autocomplete="new-password" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
      <label><span class="block text-sm text-slate-300 mb-1">Confirm {{ $editing ? 'new ' : '' }}password</span><input type="password" name="password_confirmation" @required(!$editing) minlength="12" autocomplete="new-password" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
    </div>
    <p class="text-xs text-slate-400">New or reset passwords must be changed at the user's next sign-in.</p>
    <div class="flex justify-end gap-3"><a href="{{ route('settings.users.index') }}" class="rounded-lg bg-slate-700 hover:bg-slate-600 px-4 py-2">Cancel</a><button class="rounded-lg bg-emerald-600 hover:bg-emerald-500 px-4 py-2 font-semibold">{{ $editing ? 'Save changes' : 'Create user' }}</button></div>
  </form>
</div>
@endsection
