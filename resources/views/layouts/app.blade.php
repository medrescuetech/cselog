@php($hwrtTheme = \App\Models\Setting::value('appearance.theme', 'dark'))
<!doctype html>
<html lang="en" class="h-full" data-theme="{{ $hwrtTheme }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'HWRT') · High Risk Work Tracker</title>
<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>
@stack('head')
<style>
[x-cloak]{display:none!important}
.csem-nodata-black{mix-blend-mode:lighten}

/* V2 light scheme. Existing utility markup is retained while the production asset build is localised. */
html[data-theme="light"] body{background:#f8fafc!important;color:#0f172a!important}
html[data-theme="light"] .bg-slate-950{background:#e2e8f0!important}
html[data-theme="light"] .bg-slate-900,
html[data-theme="light"] .bg-slate-900\/40,
html[data-theme="light"] .bg-slate-900\/60,
html[data-theme="light"] .bg-slate-900\/70,
html[data-theme="light"] .bg-slate-900\/90{background:#f8fafc!important}
html[data-theme="light"] .bg-slate-800,
html[data-theme="light"] .bg-slate-800\/50,
html[data-theme="light"] .bg-slate-800\/60,
html[data-theme="light"] .bg-slate-800\/70{background:#fff!important}
html[data-theme="light"] .bg-slate-700{background:#e2e8f0!important;color:#0f172a!important}
html[data-theme="light"] .border-slate-800,
html[data-theme="light"] .border-slate-700,
html[data-theme="light"] .border-slate-600{border-color:#cbd5e1!important}
html[data-theme="light"] .text-slate-100,
html[data-theme="light"] .text-slate-200,
html[data-theme="light"] .text-slate-300{color:#1e293b!important}
html[data-theme="light"] .text-slate-400{color:#475569!important}
html[data-theme="light"] .text-slate-500{color:#64748b!important}
html[data-theme="light"] input,
html[data-theme="light"] select,
html[data-theme="light"] textarea{background:#fff!important;color:#0f172a!important}
html[data-theme="light"] .leaflet-control-layers{background:#fff!important;color:#0f172a!important}
html[data-theme="light"] .leaflet-popup-content-wrapper,
html[data-theme="light"] .leaflet-popup-tip{background:#fff!important;color:#0f172a!important}
</style>
</head>
<body class="h-full bg-slate-900 text-slate-100 flex flex-col">
<nav class="bg-slate-950 border-b border-slate-800">
  <div class="max-w-7xl mx-auto px-3 min-h-14 flex flex-wrap items-center gap-1 text-sm py-1">
    <a href="{{ route('board') }}" class="font-bold text-lg tracking-tight mr-2" title="High Risk Work Tracker">HWRT</a>

    @php
      $nav = [
        ['board', 'Open board'],
        ['map', 'Map'],
        ['entries.create', 'Log work'],
        ['logbook', 'Logbook'],
        ['reports.index', 'Reports'],
      ];
    @endphp
    @foreach ($nav as [$r,$label])
      <a href="{{ route($r) }}" class="px-3 py-2 rounded {{ request()->routeIs($r) ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800' }}">{{ $label }}</a>
    @endforeach

    @if (auth()->user()?->isAdmin())
      <a href="{{ route('settings.index') }}" class="px-3 py-2 rounded {{ request()->routeIs('settings.*') || request()->routeIs('errors.*') ? 'bg-slate-800 text-white' : 'text-slate-300 hover:bg-slate-800' }}">Settings</a>
    @endif

    <div class="flex-1"></div>
    <form method="post" action="{{ route('logout') }}" class="ml-2">@csrf
      <button class="text-slate-400 hover:text-white px-2 py-2" title="{{ auth()->user()?->username }}">
        {{ auth()->user()?->name }} ⎋
      </button>
    </form>
  </div>
</nav>

@if (session('status'))
  <div class="bg-emerald-700 text-white text-sm px-4 py-2 text-center">{{ session('status') }}</div>
@endif

<main class="flex-1 @yield('main-class', 'max-w-7xl w-full mx-auto p-3 sm:p-4 pb-8')">
  @yield('content')
</main>

<div class="fixed bottom-1 left-2 z-[2000] text-[10px] text-slate-500 select-none pointer-events-none">
  HWRT v{{ config('hwrt.version') }}
</div>

<script>
  window.csrf = document.querySelector('meta[name=csrf-token]').content;
  window.fmtElapsed = s => { s = Math.max(0, Math.floor(s)); const h = Math.floor(s/3600), m = Math.floor(s%3600/60); return h ? `${h}:${String(m).padStart(2,'0')}` : `0:${String(m).padStart(2,'0')}`; };

  (() => {
    const recentlySent = new Map();

    window.hwrtReportError = (kind, message, details = {}) => {
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

    // Compatibility during V2 transition.
    window.csemReportError = window.hwrtReportError;

    window.addEventListener('error', event => {
      const target = event.target;
      if (target && target !== window && (target.src || target.href)) {
        window.hwrtReportError('resource', 'Browser resource failed to load.', {
          source: target.src || target.href,
          context: { tag: target.tagName || null },
        });
        return;
      }

      window.hwrtReportError('javascript', event.message || 'JavaScript error', {
        stack: event.error?.stack || null,
        source: event.filename || null,
        line: event.lineno || null,
        column: event.colno || null,
      });
    }, true);

    window.addEventListener('unhandledrejection', event => {
      const reason = event.reason;
      window.hwrtReportError('promise', reason?.message || String(reason || 'Unhandled promise rejection'), {
        stack: reason?.stack || null,
      });
    });
  })();
</script>
@stack('scripts')
</body>
</html>
