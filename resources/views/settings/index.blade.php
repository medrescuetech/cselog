@extends('layouts.app')
@section('title', 'Settings')

@section('content')
<div class="space-y-6">
  <div>
    <h1 class="text-2xl font-bold">Settings</h1>
    <p class="text-sm text-slate-400 mt-1">HWRT administration and system configuration.</p>
  </div>

  <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
    <a href="{{ route('settings.users.index') }}" class="rounded-xl border border-slate-700 bg-slate-800/70 p-5 hover:bg-slate-800">
      <div class="text-lg font-semibold">Users</div>
      <p class="text-sm text-slate-400 mt-1">Create users, change User/Admin access, deactivate accounts and reset passwords.</p>
    </a>

    <a href="{{ route('settings.work-types.index') }}" class="rounded-xl border border-slate-700 bg-slate-800/70 p-5 hover:bg-slate-800">
      <div class="text-lg font-semibold">High Risk Work Types</div>
      <p class="text-sm text-slate-400 mt-1">Customise the defined list, colours, Notes prompts, Other and active types.</p>
    </a>

    <a href="#appearance" class="rounded-xl border border-slate-700 bg-slate-800/70 p-5 hover:bg-slate-800">
      <div class="text-lg font-semibold">Appearance</div>
      <p class="text-sm text-slate-400 mt-1">Choose the application-wide dark or light colour scheme.</p>
    </a>

    <a href="#map" class="rounded-xl border border-slate-700 bg-slate-800/70 p-5 hover:bg-slate-800">
      <div class="text-lg font-semibold">Map</div>
      <p class="text-sm text-slate-400 mt-1">View the installed local map package and control manual/automatic refresh.</p>
    </a>
  </div>

  <section id="appearance" class="rounded-xl border border-slate-700 bg-slate-800/70 p-5">
    <h2 class="text-lg font-semibold mb-3">Appearance</h2>
    <form method="post" action="{{ route('settings.appearance') }}" class="flex flex-wrap items-end gap-3">
      @csrf
      <label>
        <span class="block text-xs text-slate-400 mb-1">Colour scheme</span>
        <select name="theme" class="rounded-lg bg-slate-900 border border-slate-700 px-3 py-2 min-w-44">
          <option value="dark" @selected($theme === 'dark')>Dark</option>
          <option value="light" @selected($theme === 'light')>Light</option>
        </select>
      </label>
      <button class="rounded-lg bg-slate-700 hover:bg-slate-600 px-4 py-2 font-semibold">Save appearance</button>
    </form>
  </section>

  <section id="map" class="rounded-xl border border-slate-700 bg-slate-800/70 p-5 space-y-4">
    <div>
      <h2 class="text-lg font-semibold">Map</h2>
      <p class="text-sm text-slate-400 mt-1">
        Runtime map data is served locally from this HWRT server. ArcGIS is contacted only by the explicit refresh job to download public source data.
      </p>
    </div>

    <div class="grid gap-3 md:grid-cols-4 text-sm">
      <div class="rounded-lg border border-slate-700 bg-slate-900/60 p-3">
        <div class="text-xs uppercase tracking-wide text-slate-500">CRS</div>
        <div class="font-semibold">{{ $manifest['crs'] ?? '—' }}</div>
      </div>
      <div class="rounded-lg border border-slate-700 bg-slate-900/60 p-3">
        <div class="text-xs uppercase tracking-wide text-slate-500">Raster layers</div>
        <div class="font-semibold">{{ count($manifest['rasters'] ?? []) }}</div>
      </div>
      <div class="rounded-lg border border-slate-700 bg-slate-900/60 p-3">
        <div class="text-xs uppercase tracking-wide text-slate-500">Vector layers</div>
        <div class="font-semibold">{{ count($manifest['vectors'] ?? []) }}</div>
      </div>
      <div class="rounded-lg border border-slate-700 bg-slate-900/60 p-3">
        <div class="text-xs uppercase tracking-wide text-slate-500">HWRT version</div>
        <div class="font-semibold">v{{ $version }}</div>
      </div>
    </div>

    @php
      $cadence = AppModelsSetting::value('map.refresh_cadence', 'manual');
      $query = AppModelsSetting::value('map.imagery_query', 'Imagery - Site C');
      $override = AppModelsSetting::value('map.imagery_service_override', '');
      $lastAttempt = AppModelsSetting::value('map.last_attempt_at', '');
      $lastSuccess = AppModelsSetting::value('map.last_success_at', '');
      $lastStatus = AppModelsSetting::value('map.last_status', 'never');
      $lastError = AppModelsSetting::value('map.last_error', '');
      $requested = AppModelsSetting::value('map.refresh_requested_at', '');
      $lastService = AppModelsSetting::value('map.last_imagery_service', '');
    @endphp

    <form method="post" action="{{ route('settings.map.update') }}" class="grid gap-3 lg:grid-cols-3">
      @csrf
      <label>
        <span class="block text-xs text-slate-400 mb-1">Refresh cadence</span>
        <select name="refresh_cadence" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2">
          @foreach (['manual' => 'Manual only', 'daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly'] as $value => $label)
            <option value="{{ $value }}" @selected($cadence === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </label>

      <label>
        <span class="block text-xs text-slate-400 mb-1">ArcGIS web-map imagery match</span>
        <input name="imagery_query" value="{{ $query }}" required
               class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2">
      </label>

      <label>
        <span class="block text-xs text-slate-400 mb-1">Service URL override (optional)</span>
        <input name="imagery_service_override" value="{{ $override }}" placeholder="Leave blank to auto-discover"
               class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2">
      </label>

      <div class="lg:col-span-3">
        <button class="rounded-lg bg-slate-700 hover:bg-slate-600 px-4 py-2 font-semibold">Save map settings</button>
      </div>
    </form>

    <div class="rounded-lg border border-slate-700 bg-slate-900/50 p-4 text-sm space-y-1">
      <div><b>Status:</b> {{ $lastStatus }}@if ($requested) · refresh queued @endif</div>
      <div><b>Last attempt:</b> {{ $lastAttempt ?: '—' }}</div>
      <div><b>Last successful refresh:</b> {{ $lastSuccess ?: '—' }}</div>
      <div class="break-all"><b>Last imagery source:</b> {{ $lastService ?: 'current packaged map' }}</div>
      @if ($lastError)
        <div class="text-red-300"><b>Last error:</b> {{ $lastError }}</div>
      @endif
    </div>

    <form method="post" action="{{ route('settings.map.refresh') }}">
      @csrf
      <button class="rounded-lg bg-emerald-600 hover:bg-emerald-500 px-4 py-2 font-semibold">Request map refresh now</button>
      <span class="text-xs text-slate-500 ml-2">Processed by the scheduler; the existing map remains live until a complete replacement validates.</span>
    </form>

    <div class="text-xs text-slate-500">
      Manifest: <code>{{ $manifestPath }}</code>. Refresh requests are download-only; no HWRT jobs, users, notes, pins or database records are sent to ArcGIS.
    </div>
  </section>

  <section class="rounded-xl border border-slate-700 bg-slate-800/70 p-5">
    <h2 class="text-lg font-semibold">Diagnostics</h2>
    <p class="text-sm text-slate-400 mt-1 mb-3">Server, browser and map errors are written to the local HWRT diagnostics log.</p>
    <a href="{{ route('errors.index') }}" class="inline-block rounded-lg bg-slate-700 hover:bg-slate-600 px-4 py-2 font-semibold">Open error log</a>
  </section>
</div>
@endsection
