<!doctype html>
<html lang="en" class="h-full">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'HRWT') · {{ config('app.name') }}</title>
<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
@stack('head')
<style>[x-cloak]{display:none!important} .hrwt-nodata-black{mix-blend-mode:lighten}</style>
</head>
<body class="h-full bg-slate-900 text-slate-100 flex flex-col">
<nav class="bg-slate-950 border-b border-slate-800">
  <div class="max-w-7xl mx-auto px-3 h-14 flex items-center gap-2 text-sm">
    <a href="{{ route(auth()->user()?->role === 'map_only' ? 'map' : 'board') }}" class="font-bold text-lg tracking-tight mr-2">{{ config('app.name') }}</a>
    @if (auth()->user()?->atLeast('viewer'))
      <a href="{{ route('board') }}" class="px-3 py-2 rounded {{ request()->routeIs('board') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800' }}">Open board</a>
    @endif
    <a href="{{ route('map') }}" class="px-3 py-2 rounded {{ request()->routeIs('map') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800' }}">Map</a>
    @if (auth()->user()?->atLeast('viewer'))
      <a href="{{ route('history') }}" class="px-3 py-2 rounded {{ request()->routeIs('history') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800' }}">History</a>
    @endif
    @if (auth()->user()?->atLeast('supervisor'))
      <a href="{{ route('admin.locations.index') }}" class="px-3 py-2 rounded {{ request()->routeIs('admin.locations.*') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800' }}">Locations</a>
    @endif
    @if (auth()->user()?->atLeast('admin'))
      <a href="{{ route('admin.settings.index') }}" class="px-3 py-2 rounded {{ request()->routeIs('admin.settings.*') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800' }}">Settings</a>
    @endif
    <div class="flex-1"></div>
    @if (auth()->user()?->atLeast('logger'))
      <a href="{{ route('entries.create') }}" class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-500 font-semibold">+ Log entry</a>
    @endif
    <form method="post" action="{{ route('logout') }}" class="ml-2">@csrf
      <button class="text-slate-400 hover:text-white px-2 py-2" title="{{ auth()->user()?->email }} ({{ auth()->user()?->role }})">{{ auth()->user()?->name }} ⎋</button>
    </form>
  </div>
</nav>
@if (session('status'))
  <div class="bg-emerald-700 text-white text-sm px-4 py-2 text-center">{{ session('status') }}</div>
@endif
<main class="flex-1 @yield('main-class', 'max-w-7xl w-full mx-auto p-3 sm:p-4')">
  @yield('content')
</main>
<script>
  window.csrf = document.querySelector('meta[name=csrf-token]').content;
  window.fmtElapsed = s => { s = Math.max(0, Math.floor(s)); const h = Math.floor(s/3600), m = Math.floor(s%3600/60); return h ? `${h}:${String(m).padStart(2,'0')}` : `0:${String(m).padStart(2,'0')}`; };
</script>
@stack('scripts')
</body>
</html>
