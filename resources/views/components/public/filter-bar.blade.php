@props(['action' => request()->url()])
<form method="GET" action="{{ $action }}" class="grid md:grid-cols-6 gap-3 p-4 bg-white border rounded-2xl">
  {{ $slot }}
  <div class="md:col-span-1 flex items-end">
    <button class="w-full rounded-xl border px-4 py-2 bg-black text-white">Filter</button>
  </div>
</form>
