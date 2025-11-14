@props(['artist'])
@php
  $slug = is_array($artist->slug) ? ($artist->slug[app()->getLocale()] ?? $artist->slug['en'] ?? $artist->id) : ($artist->slug ?? $artist->id);
@endphp
<a href="{{ route('public.artists.show', ['locale' => app()->getLocale(), 'slug' => $slug]) }}" class="block rounded-2xl bg-white border shadow-sm p-4 hover:shadow-md transition">
  <div class="flex items-center gap-4">
    <img class="w-16 h-16 rounded-xl object-cover" src="{{ $artist->photo_url ?? 'https://placehold.co/160x160?text=Artist' }}" alt="{{ $artist->name }}">
    <div>
      <div class="font-semibold">{{ $artist->name }}</div>
      @if(isset($artist->events_count))
        <div class="text-sm text-gray-500">{{ $artist->events_count }} events</div>
      @endif
    </div>
  </div>
</a>
