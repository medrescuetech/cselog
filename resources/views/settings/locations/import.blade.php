@extends('layouts.app')
@section('title', 'Import saved locations')
@section('content')
<div class="max-w-5xl mx-auto space-y-5">
  <div class="flex flex-wrap items-center gap-3"><div><h1 class="text-2xl font-bold">Import saved locations</h1><p class="text-sm text-slate-400">Preview and resolve conflicts before any location is created.</p></div><div class="flex-1"></div><a href="{{ route('settings.locations.index') }}" class="rounded bg-slate-800 px-3 py-2">Back to Locations</a></div>
  @if ($errors->any()) <div class="rounded border border-red-700 bg-red-950/60 p-4" role="alert">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div> @endif
  <section class="rounded-xl border border-slate-700 bg-slate-800/70 p-4 space-y-3 text-sm">
    <p>CSV headers: <code>name,code,easting,northing,epsg,aliases</code>. Use <code>28350</code> in every EPSG cell (MGA Zone 50). Use a pipe <code>|</code> between aliases. Code and aliases may be blank. Up to 500 rows and 1 MB per file.</p>
    <p>Conflicting names/codes or pins within 15 m require manual review. This import creates new locations only; edit existing locations individually. Historic jobs remain unchanged.</p>
    <form method="post" action="{{ route('settings.locations.import.preview') }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3">@csrf<label>Choose CSV<input type="file" name="file" accept=".csv,text/csv" required class="block mt-1"></label><button class="rounded bg-emerald-600 hover:bg-emerald-500 px-4 py-2">Preview CSV</button></form>
  </section>
  @if ($rows !== null)
    <section class="rounded-xl border border-slate-700 p-4 space-y-3"><h2 class="text-lg font-semibold">Preview: {{ count($rows) }} row(s)</h2>
      @if ($errorsFound) <p class="text-amber-300">Resolve every marked row in the source CSV and upload again. Nothing has been imported.</p> @else
        <p class="text-emerald-300">All rows are eligible. Rechecked against the live catalogue when applied.</p>
        <form method="post" action="{{ route('settings.locations.import.apply') }}" onsubmit="return confirm('Create all {{ count($rows) }} saved locations?');">@csrf<input type="hidden" name="token" value="{{ $token }}"><button class="rounded bg-emerald-600 hover:bg-emerald-500 px-4 py-2 font-semibold">Import {{ count($rows) }} locations</button></form>
      @endif
      <div class="overflow-x-auto"><table class="w-full min-w-[900px] text-sm"><thead class="bg-slate-900 text-left"><tr><th class="p-2">Row</th><th class="p-2">Name</th><th class="p-2">Code</th><th class="p-2">Easting</th><th class="p-2">Northing</th><th class="p-2">Result</th></tr></thead><tbody>@foreach ($rows as $row)<tr class="border-t border-slate-700"><td class="p-2">{{ $loop->iteration + 1 }}</td><td class="p-2">{{ $row['name'] }}</td><td class="p-2">{{ $row['code'] ?: '—' }}</td><td class="p-2">{{ $row['easting'] }}</td><td class="p-2">{{ $row['northing'] }}</td><td class="p-2 {{ $row['error'] ? 'text-amber-300' : 'text-emerald-300' }}">{{ $row['error'] ?: 'Ready to create' }}</td></tr>@endforeach</tbody></table></div>
    </section>
  @endif
</div>
@endsection
