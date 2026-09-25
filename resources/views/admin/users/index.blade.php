@extends('layouts.app')
@section('title', 'Users')

@section('content')
<div class="space-y-6">
  <div>
    <h1 class="text-2xl font-bold">User management</h1>
    <p class="text-sm text-slate-400 mt-1">Create accounts, change access level, deactivate users, or reset passwords.</p>
  </div>

  @if ($errors->any())
    <div class="rounded-lg border border-red-700 bg-red-950/60 p-4 text-sm text-red-100">
      <div class="font-semibold mb-1">Could not save the user.</div>
      <ul class="list-disc pl-5 space-y-1">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <section class="rounded-xl border border-slate-700 bg-slate-800/70 p-4">
    <h2 class="text-lg font-semibold mb-4">Add user</h2>
    <form method="post" action="{{ route('admin.users.store') }}" class="grid gap-3 lg:grid-cols-6">
      @csrf
      <div class="lg:col-span-2">
        <label class="block text-xs text-slate-400 mb-1">Name</label>
        <input name="name" value="{{ old('name') }}" required maxlength="120"
               class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2">
      </div>
      <div class="lg:col-span-2">
        <label class="block text-xs text-slate-400 mb-1">Email</label>
        <input type="email" name="email" value="{{ old('email') }}" required
               class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2">
      </div>
      <div>
        <label class="block text-xs text-slate-400 mb-1">Role</label>
        <select name="role" required class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2">
          @foreach ($roles as $role)
            <option value="{{ $role }}" @selected(old('role', 'logger') === $role)>{{ ucfirst($role) }}</option>
          @endforeach
        </select>
      </div>
      <div class="flex items-end">
        <label class="flex items-center gap-2 rounded-lg bg-slate-900 border border-slate-700 px-3 py-2 w-full">
          <input type="hidden" name="active" value="0">
          <input type="checkbox" name="active" value="1" @checked(old('active', '1') === '1')>
          <span>Active</span>
        </label>
      </div>
      <div class="lg:col-span-2">
        <label class="block text-xs text-slate-400 mb-1">Password</label>
        <input type="password" name="password" required minlength="12" autocomplete="new-password"
               class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2">
      </div>
      <div class="lg:col-span-2">
        <label class="block text-xs text-slate-400 mb-1">Confirm password</label>
        <input type="password" name="password_confirmation" required minlength="12" autocomplete="new-password"
               class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2">
      </div>
      <div class="lg:col-span-2 flex items-end">
        <button class="w-full rounded-lg bg-emerald-600 hover:bg-emerald-500 px-4 py-2 font-semibold">Create user</button>
      </div>
    </form>
  </section>

  <div class="rounded-lg border border-slate-700 bg-slate-800/50 p-3 text-xs text-slate-300">
    <b>Viewer</b>: board, map and history. ·
    <b>Logger</b>: Viewer access plus create/close entries and locations. ·
    <b>Supervisor</b>: Logger access plus error log. ·
    <b>Admin</b>: full access including user management.
  </div>

  <section class="space-y-3">
    @foreach ($users as $user)
      <form method="post" action="{{ route('admin.users.update', $user) }}"
            class="rounded-xl border {{ $user->active ? 'border-slate-700 bg-slate-800/70' : 'border-slate-800 bg-slate-900/60 opacity-75' }} p-4">
        @csrf
        @method('PATCH')
        <div class="grid gap-3 lg:grid-cols-12 items-end">
          <div class="lg:col-span-2">
            <label class="block text-xs text-slate-400 mb-1">Name</label>
            <input name="name" value="{{ $user->name }}" required maxlength="120"
                   class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2">
          </div>
          <div class="lg:col-span-3">
            <label class="block text-xs text-slate-400 mb-1">Email</label>
            <input type="email" name="email" value="{{ $user->email }}" required
                   class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2">
          </div>
          <div class="lg:col-span-2">
            <label class="block text-xs text-slate-400 mb-1">Role</label>
            @if ($user->id === auth()->id())
              <input type="hidden" name="role" value="admin">
              <div class="w-full rounded-lg bg-slate-900/70 border border-slate-700 px-3 py-2 text-slate-300">Admin</div>
            @else
              <select name="role" required class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2">
                @foreach ($roles as $role)
                  <option value="{{ $role }}" @selected($user->role === $role)>{{ ucfirst($role) }}</option>
                @endforeach
              </select>
            @endif
          </div>
          <div class="lg:col-span-2">
            <label class="block text-xs text-slate-400 mb-1">New password</label>
            <input type="password" name="password" minlength="12" autocomplete="new-password" placeholder="Leave unchanged"
                   class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2">
          </div>
          <div class="lg:col-span-2">
            <label class="block text-xs text-slate-400 mb-1">Confirm</label>
            <input type="password" name="password_confirmation" minlength="12" autocomplete="new-password"
                   class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2">
          </div>
          <div class="lg:col-span-1 flex lg:block gap-3">
            <label class="flex items-center gap-2 text-sm mb-2">
              @if ($user->id === auth()->id())
                <input type="hidden" name="active" value="1">
                <input type="checkbox" checked disabled>
              @else
                <input type="hidden" name="active" value="0">
                <input type="checkbox" name="active" value="1" @checked($user->active)>
              @endif
              <span>Active</span>
            </label>
            <button class="w-full rounded-lg bg-slate-700 hover:bg-slate-600 px-3 py-2 text-sm font-semibold">Save</button>
          </div>
        </div>
        <div class="mt-2 text-xs text-slate-500">
          User #{{ $user->id }}
          @if ($user->id === auth()->id()) · This is your account @endif
          @if (! $user->active) · Inactive users cannot sign in @endif
        </div>
      </form>
    @endforeach
  </section>
</div>
@endsection
