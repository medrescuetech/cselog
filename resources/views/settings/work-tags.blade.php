@extends('layouts.app')
@section('title', 'Settings · Activity tags')
@section('content')
<div class="max-w-4xl mx-auto space-y-5">
  <div class="flex flex-wrap items-center gap-3"><div><h1 class="text-2xl font-bold">Activity tags</h1><p class="text-sm text-slate-400">Optional labels for additional work or hazards. A job still has one primary work type.</p></div><div class="flex-1"></div><a href="{{ route('settings.index') }}" class="rounded bg-slate-800 px-3 py-2">Back to Settings</a></div>
  @if ($errors->any()) <div class="rounded bg-red-950/60 border border-red-700 p-3">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div> @endif
  <form method="post" action="{{ route('settings.work-tags.store') }}" class="flex flex-wrap gap-2 rounded border border-slate-700 p-4">@csrf<label class="flex-1">New activity or hazard tag<input name="name" required maxlength="80" class="block w-full rounded bg-slate-900 border border-slate-700 px-3 py-2"></label><button class="self-end rounded bg-emerald-600 px-4 py-2 font-semibold">Add tag</button></form>
  <div class="space-y-2">@foreach ($tags as $tag)<form method="post" action="{{ route('settings.work-tags.update', $tag) }}" class="flex flex-wrap items-end gap-3 rounded border border-slate-700 p-3">@csrf @method('PATCH')<label class="flex-1">Name<input name="name" value="{{ $tag->name }}" required maxlength="80" class="block w-full rounded bg-slate-900 border border-slate-700 px-3 py-2"></label><label class="flex items-center gap-2 pb-2"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked($tag->active)> Active</label><button class="rounded bg-slate-700 px-4 py-2">Save</button></form>@endforeach</div>
  <p class="text-sm text-slate-400">Retire a tag by unchecking Active. Past jobs and reports keep it. Decide the tags with your site work controller.</p>
</div>
@endsection
