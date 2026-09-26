<!doctype html>
<html lang="en" class="h-full"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · HWRT — High Risk Work Tracker</title><link rel="stylesheet" href="{{ asset('css/app.css') }}"></head>
<body class="h-full bg-slate-900 text-slate-100 flex items-center justify-center p-4">
<form method="post" action="{{ route('login') }}" class="w-full max-w-sm bg-slate-800 rounded-xl p-6 space-y-4 shadow-xl">
  @csrf
  <div>
    <h1 class="text-3xl font-bold">HWRT</h1>
    <p class="text-slate-300">High Risk Work Tracker</p>
    <p class="text-slate-500 text-xs mt-1">v{{ config('hwrt.version') }}</p>
  </div>

  @error('login') <p class="text-red-400 text-sm">{{ $message }}</p> @enderror

  <label class="block"><span class="text-sm text-slate-300">Username or email</span>
    <input name="login" value="{{ old('login') }}" required autofocus autocomplete="username"
           class="mt-1 w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-3 text-lg"></label>

  <label class="block"><span class="text-sm text-slate-300">Password</span>
    <input name="password" type="password" required autocomplete="current-password"
           class="mt-1 w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-3 text-lg"></label>

  <label class="flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" name="remember" class="h-5 w-5"> Keep me signed in on this device</label>
  <button class="w-full rounded-lg bg-red-600 hover:bg-red-500 py-3 text-lg font-semibold">Sign in</button>
</form>
</body></html>
