@props(['label','value'])
<div class="rounded-2xl bg-white shadow-sm p-5 border">
  <div class="text-xs uppercase tracking-wide text-gray-500">{{ $label }}</div>
  <div class="mt-2 text-3xl font-semibold">{{ number_format((int)$value) }}</div>
</div>
