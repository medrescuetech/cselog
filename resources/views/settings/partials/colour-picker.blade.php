@php($eventName = $event ?? 'colour-changed')
<div x-data="colourPicker(@js($value), @js($eventName))" class="space-y-2">
  <input type="hidden" name="{{ $name }}" value="{{ $value }}" x-model="hex">
  <div class="flex items-center gap-2">
    <div class="h-8 w-8 rounded border border-slate-600" :style="`background:${hex}`"></div>
    <input x-model="hex" @input="setHex($event.target.value)" maxlength="7" pattern="#[0-9a-fA-F]{6}" class="w-24 rounded-lg bg-slate-900 border border-slate-700 px-2 py-1 font-mono text-sm">
    <template x-for="colour in standard" :key="colour">
      <button type="button" @click="setHex(colour)" :title="colour" :style="`background:${colour}`"
              class="h-6 w-6 rounded border" :class="hex.toLowerCase() === colour.toLowerCase() ? 'border-white ring-1 ring-white' : 'border-slate-600'"></button>
    </template>
  </div>
  <div class="grid gap-1 text-xs">
    <label class="grid grid-cols-[16px_1fr_32px] items-center gap-2"><span class="text-red-400">R</span><input type="range" min="0" max="255" x-model.number="r" @input="updateFromRgb"><span x-text="r"></span></label>
    <label class="grid grid-cols-[16px_1fr_32px] items-center gap-2"><span class="text-emerald-400">G</span><input type="range" min="0" max="255" x-model.number="g" @input="updateFromRgb"><span x-text="g"></span></label>
    <label class="grid grid-cols-[16px_1fr_32px] items-center gap-2"><span class="text-sky-400">B</span><input type="range" min="0" max="255" x-model.number="b" @input="updateFromRgb"><span x-text="b"></span></label>
  </div>
</div>
