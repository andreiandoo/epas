@props(['venue'])
<a href="{{ route('public.venue.show', ['locale' => app()->getLocale(), 'venue' => $venue->slug]) }}" class="block rounded-2xl bg-white border shadow-sm p-4 hover:shadow-md transition">
  <div class="font-semibold">{{ $venue->name }}</div>
  <div class="text-sm text-gray-500">{{ $venue->city }}, {{ $venue->country }}</div>
  @if($venue->capacity_total)
    <div class="text-xs text-gray-400 mt-1">Capacity: {{ number_format($venue->capacity_total) }}</div>
  @endif
</a>
