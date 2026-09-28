<!doctype html>
<html lang="en" class="h-full"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Change password · HWRT</title><link rel="stylesheet" href="{{ asset('css/app.css') }}"></head>
<body class="h-full bg-slate-900 text-slate-100 flex items-center justify-center p-4">
<form method="post" action="{{ route('password.change.store') }}" class="w-full max-w-sm bg-slate-800 rounded-xl p-6 space-y-4 shadow-xl">
  @csrf
  <div>
    <h1 class="text-2xl font-bold">Change your password</h1>
    <p class="text-slate-300 text-sm mt-2">Set a new password before using HWRT.</p>
  </div>
  @if ($errors->any())
    <div class="text-red-400 text-sm">{{ $errors->first() }}</div>
  @endif
  <label class="block"><span class="text-sm text-slate-300">Current password</span>
    <input name="current_password" type="password" required autocomplete="current-password"
           class="mt-1 w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-3"></label>
  <label class="block"><span class="text-sm text-slate-300">New password (at least 12 characters)</span>
    <input name="password" type="password" required minlength="12" autocomplete="new-password"
           class="mt-1 w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-3"></label>
  <label class="block"><span class="text-sm text-slate-300">Confirm new password</span>
    <input name="password_confirmation" type="password" required minlength="12" autocomplete="new-password"
           class="mt-1 w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-3"></label>
  <button class="w-full rounded-lg bg-red-600 hover:bg-red-500 py-3 font-semibold">Update password</button>
</form>
</body></html>
