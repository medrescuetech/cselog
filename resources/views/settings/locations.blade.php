@extends('layouts.app')
@section('title', 'Settings · Locations')

@section('content')
<div class="space-y-5">
  <div class="flex flex-wrap items-end gap-3">
    <div>
      <h1 class="text-2xl font-bold">Settings · Locations</h1>
      <p class="text-sm text-slate-400 mt-1">Attach one optional PDF to a catalogue location. PDFs are stored privately and require an HWRT login to download.</p>
    </div>
    <div class="flex-1"></div>
    <a href="{{ route('settings.index') }}" class="rounded-lg bg-slate-800 hover:bg-slate-700 px-3 py-2 text-sm">Back to Settings</a>
  </div>

  <form method="get" class="flex gap-2 max-w-xl">
    <input type="search" name="q" value="{{ $q }}" placeholder="Search locations…" class="flex-1 rounded-lg bg-slate-800 border border-slate-700 px-3 py-2">
    <button class="rounded-lg bg-slate-700 hover:bg-slate-600 px-4 py-2">Search</button>
  </form>

  <div class="space-y-3">
    @foreach ($locations as $location)
      <div class="rounded-xl border border-slate-700 bg-slate-800/70 p-4">
        <div class="grid gap-3 md:grid-cols-[1fr_auto] items-start">
          <div>
            <div class="font-semibold text-lg">{{ $location->name }}</div>
            <div class="text-xs text-slate-500">
              {{ $location->area?->name ?? 'No area' }} ·
              E {{ number_format($location->easting, 1) }} N {{ number_format($location->northing, 1) }}
            </div>

            @if ($location->document_path)
              <div class="mt-3 rounded-lg border border-emerald-800 bg-emerald-950/30 p-3">
                <div class="text-sm font-medium">{{ $location->document_name }}</div>
                <div class="text-xs text-slate-500">
                  Uploaded {{ $location->document_uploaded_at?->format('d M Y H:i') ?? '—' }}
                </div>
                <div class="mt-2 flex gap-2">
                  <a href="{{ route('locations.document', $location) }}" target="_blank" class="rounded bg-slate-700 hover:bg-slate-600 px-3 py-1.5 text-sm">Open / download PDF</a>
                  <form method="post" action="{{ route('settings.locations.document.remove', $location) }}" onsubmit="return confirm('Remove this PDF?');">
                    @csrf @method('DELETE')
                    <button class="rounded bg-red-800 hover:bg-red-700 px-3 py-1.5 text-sm">Remove</button>
                  </form>
                </div>
              </div>
            @endif
          </div>

          <form method="post" action="{{ route('settings.locations.document.upload', $location) }}" enctype="multipart/form-data" class="min-w-[260px]">
            @csrf
            <label class="block text-xs text-slate-400 mb-1">{{ $location->document_path ? 'Replace PDF' : 'Attach PDF' }}</label>
            <input type="file" name="document" accept="application/pdf" required class="block w-full text-sm mb-2">
            <button class="rounded-lg bg-emerald-600 hover:bg-emerald-500 px-3 py-2 text-sm font-semibold">Upload</button>
          </form>
        </div>
      </div>
    @endforeach
  </div>

  <div>{{ $locations->links() }}</div>
</div>
@endsection
