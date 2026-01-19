@props(['event'])
@php
  // Build tenant permalink for event
  $tenantDomain = optional($event->tenant)->domain; // adjust column
  $slug         = is_array($event->slug) ? ($event->slug[app()->getLocale()] ?? $event->slug['en'] ?? $event->id) : ($event->slug ?? $event->id);
  $tenantUrl    = $tenantDomain ? "https://{$tenantDomain}/events/{$slug}" : '#';
  $title        = is_array($event->title) ? ($event->title[app()->getLocale()] ?? $event->title['en'] ?? 'Event') : $event->title;
@endphp
<a href="{{ $tenantUrl }}" target="_blank" class="block rounded-2xl bg-white border shadow-sm overflow-hidden hover:shadow-md transition">
  <img src="{{ $event->poster_url ?? 'https://placehold.co/800x500?text=Event' }}" alt="{{ $title }}" class="w-full h-48 object-cover">
  <div class="p-4">
    <div class="text-sm text-gray-500">
      {{ optional($event->venue)->city }}, {{ optional($event->venue)->country }}
      • {{ \Illuminate\Support\Carbon::parse($event->starts_at)->format('M d, Y H:i') }}
    </div>
    <div class="mt-1 font-semibold">{{ $title }}</div>
    @if(!is_null($event->min_price))
      <div class="mt-2 text-sm text-gray-600">From {{ number_format($event->min_price, 2) }} {{ $event->currency ?? 'RON' }}</div>
    @endif
  </div>
</a>
