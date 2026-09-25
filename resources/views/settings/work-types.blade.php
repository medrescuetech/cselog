@extends('layouts.app')
@section('title', 'Settings · High Risk Work Types')

@section('content')
<div class="space-y-6">
  <div class="flex items-start gap-3">
    <div>
      <h1 class="text-2xl font-bold">Settings · High Risk Work Types</h1>
      <p class="text-sm text-slate-400 mt-1">Customise the operational list. Existing types are retired rather than deleted.</p>
    </div>
    <div class="flex-1"></div>
    <a href="{{ route('settings.index') }}" class="rounded-lg bg-slate-800 hover:bg-slate-700 px-3 py-2 text-sm">Back to Settings</a>
  </div>

  @if ($errors->any())
    <div class="rounded-lg border border-red-700 bg-red-950/60 p-4 text-sm text-red-100">
      <ul class="list-disc pl-5 space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
  @endif

  <section class="rounded-xl border border-slate-700 bg-slate-800/70 p-4">
    <h2 class="text-lg font-semibold mb-4">Add type</h2>
    <form method="post" action="{{ route('settings.work-types.store') }}" class="grid gap-3 lg:grid-cols-12">
      @csrf
      <label class="lg:col-span-3"><span class="block text-xs text-slate-400 mb-1">Name</span>
        <input name="name" required maxlength="80" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
      <label class="lg:col-span-2"><span class="block text-xs text-slate-400 mb-1">Colour</span>
        <input type="color" name="colour" value="#d9534f" class="w-full h-10 rounded-lg bg-slate-900 border border-slate-700 px-1"></label>
      <label class="lg:col-span-1"><span class="block text-xs text-slate-400 mb-1">Order</span>
        <input type="number" name="sort_order" value="{{ ($workTypes->max('sort_order') ?? 0) + 1 }}" min="0" max="9999" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
      <label class="lg:col-span-4"><span class="block text-xs text-slate-400 mb-1">Notes prompt</span>
        <input name="notes_prompt" placeholder="Optional hint shown on the log form" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
      <div class="lg:col-span-2 flex flex-wrap gap-3 items-end text-sm">
        @foreach (['active' => 'Active', 'requires_note' => 'Notes required', 'is_default' => 'Default', 'is_other' => 'Other'] as $name => $label)
          <label class="flex items-center gap-1"><input type="hidden" name="{{ $name }}" value="0"><input type="checkbox" name="{{ $name }}" value="1" @checked($name === 'active')> {{ $label }}</label>
        @endforeach
      </div>
      <div class="lg:col-span-12"><button class="rounded-lg bg-emerald-600 hover:bg-emerald-500 px-4 py-2 font-semibold">Add type</button></div>
    </form>
  </section>

  <section class="space-y-3">
    @foreach ($workTypes as $type)
      <form method="post" action="{{ route('settings.work-types.update', $type) }}" class="rounded-xl border {{ $type->active ? 'border-slate-700 bg-slate-800/70' : 'border-slate-800 bg-slate-900/60 opacity-75' }} p-4">
        @csrf @method('PATCH')
        <div class="grid gap-3 lg:grid-cols-12 items-end">
          <label class="lg:col-span-3"><span class="block text-xs text-slate-400 mb-1">Name</span>
            <input name="name" value="{{ $type->name }}" required class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
          <label class="lg:col-span-1"><span class="block text-xs text-slate-400 mb-1">Colour</span>
            <input type="color" name="colour" value="{{ $type->colour }}" class="w-full h-10 rounded-lg bg-slate-900 border border-slate-700 px-1"></label>
          <label class="lg:col-span-1"><span class="block text-xs text-slate-400 mb-1">Order</span>
            <input type="number" name="sort_order" value="{{ $type->sort_order }}" min="0" max="9999" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
          <label class="lg:col-span-4"><span class="block text-xs text-slate-400 mb-1">Notes prompt</span>
            <input name="notes_prompt" value="{{ $type->notes_prompt }}" class="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-2"></label>
          <div class="lg:col-span-3 flex flex-wrap gap-3 text-sm">
            @foreach (['active' => 'Active', 'requires_note' => 'Notes required', 'is_default' => 'Default', 'is_other' => 'Other'] as $name => $label)
              <label class="flex items-center gap-1"><input type="hidden" name="{{ $name }}" value="0"><input type="checkbox" name="{{ $name }}" value="1" @checked($type->{$name})> {{ $label }}</label>
            @endforeach
          </div>
          <div class="lg:col-span-12"><button class="rounded-lg bg-slate-700 hover:bg-slate-600 px-4 py-2 font-semibold">Save</button></div>
        </div>
      </form>
    @endforeach
  </section>
</div>
@endsection
