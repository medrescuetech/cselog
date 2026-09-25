<!doctype html>
<html lang="en" class="h-full">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'CSEM') · {{ config('app.name') }}</title>
<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
@stack('head')
<style>[x-cloak]{display:none!important} .csem-nodata-black{mix-blend-mode:lighten}</style>
</head>
<body class="h-full bg-slate-900 text-slate-100 flex flex-col">
<nav class="bg-slate-950 border-b border-slate-800">
  <div class="max-w-7xl mx-auto px-3 h-14 flex items-center gap-2 text-sm">
    <a href="{{ route('board') }}" class="font-bold text-lg tracking-tight mr-2">{{ config('app.name') }}</a>
    @php $nav = [['board','Open board'],['map','Map'],['history','History']]; @endphp
    @foreach ($nav as [$r,$label])
      <a href="{{ route($r) }}" class="px-3 py-2 rounded {{ request()->routeIs($r) ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800' }}">{{ $label }}</a>
    @endforeach
    @if (auth()->user()?->atLeast('supervisor'))
      <a href="{{ route('errors.index') }}" class="px-3 py-2 rounded {{ request()->routeIs('errors.*') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800' }}">Errors</a>
    @endif
    <div class="flex-1"></div>
    @if (auth()->user()?->atLeast('logger'))
      <a href="{{ route('entries.create') }}" class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-500 font-semibold">+ Log entry</a>
    @endif
    <form method="post" action="{{ route('logout') }}" class="ml-2">@csrf
      <button class="text-slate-400 hover:text-white px-2 py-2" title="{{ auth()->user()?->email }}">{{ auth()->user()?->name }} ⎋</button>
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

  (() => {
    const recentlySent = new Map();

    window.csemReportError = (kind, message, details = {}) => {
      const text = String(message || 'Unknown browser error').slice(0, 4000);
      const fingerprint = `${kind}|${text}|${location.pathname}`;
      const now = Date.now();
      if (recentlySent.has(fingerprint) && now - recentlySent.get(fingerprint) < 30000) return;
      recentlySent.set(fingerprint, now);

      const context = details.context && typeof details.context === 'object' ? details.context : {};
      const body = {
        kind: String(kind || 'browser').slice(0, 80),
        message: text,
        stack: details.stack ? String(details.stack).slice(0, 12000) : null,
        url: location.href,
        source: details.source ? String(details.source).slice(0, 2000) : null,
        line: Number.isFinite(Number(details.line)) ? Number(details.line) : null,
        column: Number.isFinite(Number(details.column)) ? Number(details.column) : null,
        context,
      };

      fetch('{{ route('api.client-errors') }}', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': window.csrf,
        },
        body: JSON.stringify(body),
      }).catch(() => {});
    };

    window.addEventListener('error', event => {
      const target = event.target;
      if (target && target !== window && (target.src || target.href)) {
        window.csemReportError('resource', 'Browser resource failed to load.', {
          source: target.src || target.href,
          context: { tag: target.tagName || null },
        });
        return;
      }

      window.csemReportError('javascript', event.message || 'JavaScript error', {
        stack: event.error?.stack || null,
        source: event.filename || null,
        line: event.lineno || null,
        column: event.colno || null,
      });
    }, true);

    window.addEventListener('unhandledrejection', event => {
      const reason = event.reason;
      window.csemReportError('promise', reason?.message || String(reason || 'Unhandled promise rejection'), {
        stack: reason?.stack || null,
      });
    });
  })();
</script>
@stack('scripts')
</body>
</html>
