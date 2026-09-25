@extends('layouts.app')
@section('title', 'Errors')

@section('content')
<div class="space-y-4">
  <div class="flex flex-wrap items-center gap-3">
    <div>
      <h1 class="text-2xl font-bold">Error log</h1>
      <p class="text-sm text-slate-400">Server exceptions and browser/map errors from <code>storage/logs/csem-errors.log</code>.</p>
    </div>
    <div class="flex-1"></div>
    <a href="{{ route('errors.index', ['lines' => $lines]) }}" class="px-3 py-2 rounded bg-slate-700 hover:bg-slate-600 text-sm">Refresh</a>
    @if ($exists)
      <a href="{{ route('errors.download') }}" class="px-3 py-2 rounded bg-slate-700 hover:bg-slate-600 text-sm">Download .log</a>
    @endif
    @if (auth()->user()?->atLeast('admin'))
      <form method="post" action="{{ route('errors.clear') }}" onsubmit="return confirm('Clear the current error log?');">
        @csrf
        <button class="px-3 py-2 rounded bg-red-700 hover:bg-red-600 text-sm">Clear</button>
      </form>
    @endif
  </div>

  <div class="grid gap-3 sm:grid-cols-3">
    <div class="rounded-lg bg-slate-800 border border-slate-700 p-3">
      <div class="text-xs uppercase tracking-wide text-slate-400">Status</div>
      <div class="font-semibold">{{ $exists ? 'Log file present' : 'No errors logged yet' }}</div>
    </div>
    <div class="rounded-lg bg-slate-800 border border-slate-700 p-3">
      <div class="text-xs uppercase tracking-wide text-slate-400">Size</div>
      <div class="font-semibold">{{ number_format($size / 1024, 1) }} KB</div>
    </div>
    <div class="rounded-lg bg-slate-800 border border-slate-700 p-3">
      <div class="text-xs uppercase tracking-wide text-slate-400">Last modified</div>
      <div class="font-semibold">{{ $modified ? date('Y-m-d H:i:s', $modified) : '—' }}</div>
    </div>
  </div>

  <form method="get" action="{{ route('errors.index') }}" class="flex items-center gap-2 text-sm">
    <label for="lines" class="text-slate-300">Lines</label>
    <select id="lines" name="lines" onchange="this.form.submit()" class="rounded bg-slate-800 border border-slate-700 px-2 py-1">
      @foreach ([100, 300, 500, 1000] as $option)
        <option value="{{ $option }}" @selected($lines === $option)>{{ $option }}</option>
      @endforeach
    </select>
  </form>

  @if ($content !== '')
    <pre class="overflow-auto whitespace-pre-wrap break-words rounded-lg bg-black/60 border border-slate-700 p-4 text-xs leading-5 max-h-[72vh]">{{ $content }}</pre>
  @else
    <div class="rounded-lg border border-slate-700 bg-slate-800 p-6 text-slate-300">
      No entries in <code>csem-errors.log</code>.
    </div>
  @endif
</div>

<script>
  setTimeout(() => location.reload(), 30000);
</script>
@endsection
