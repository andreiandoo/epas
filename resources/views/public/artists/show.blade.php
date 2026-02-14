@extends('public.layout')

@section('title', $artist->name . ' - Tixello')

@section('content')
{{-- Hero Section --}}
<div class="relative bg-gradient-to-br from-gray-900 to-gray-800 text-white">
    @if ($artist->main_image_full_url ?? false)
        <div class="absolute inset-0 opacity-30">
            <img src="{{ $artist->main_image_full_url }}" alt="{{ $artist->name }}" class="w-full h-full object-cover">
        </div>
    @endif
    <div class="relative max-w-7xl mx-auto px-4 py-16">
        <div class="flex flex-col md:flex-row gap-8 items-start">
            @if ($artist->portrait_url ?? false)
                <img src="{{ $artist->portrait_url }}" alt="{{ $artist->name }}" class="w-48 h-48 rounded-2xl object-cover shadow-2xl">
            @endif
            <div class="flex-1">
                <h1 class="text-5xl font-bold mb-4">{{ $artist->name }}</h1>
                <div class="flex flex-wrap gap-4 text-lg mb-4">
                    @if($artist->city || $artist->country)
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            {{ $artist->city }}{{ $artist->city && $artist->country ? ', ' : '' }}{{ $artist->country }}
                        </div>
                    @endif
                </div>
                @if ($artist->artistTypes?->isNotEmpty())
                    <div class="flex flex-wrap gap-2 mb-4">
                        @foreach ($artist->artistTypes as $type)
                            <span class="px-4 py-1.5 bg-white/20 backdrop-blur rounded-full text-sm font-medium">{{ $type->getTranslation('name', app()->getLocale()) }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Main Content --}}
<div class="max-w-7xl mx-auto px-4 py-12">
    <div class="grid lg:grid-cols-3 gap-8">
        {{-- Main Column --}}
        <div class="lg:col-span-2 space-y-8">
            {{-- Bio --}}
            @if ($artist->bio ?? false)
                <section class="bg-white rounded-2xl border p-6">
                    <h2 class="text-2xl font-bold mb-4">About</h2>
                    <div class="prose max-w-none">
                        {{-- SECURITY FIX: Sanitize HTML content to prevent XSS --}}
                        @php
                            $bioContent = is_array($artist->bio) ? ($artist->bio[app()->getLocale()] ?? $artist->bio['en'] ?? reset($artist->bio)) : $artist->bio;
                        @endphp
                        {!! \App\Helpers\HtmlSanitizer::sanitize($bioContent) !!}
                    </div>
                </section>
            @endif

            {{-- Upcoming Events --}}
            @if($artist->events && $artist->events->isNotEmpty())
                <section class="bg-white rounded-2xl border p-6">
                    <h2 class="text-2xl font-bold mb-4">Upcoming Events</h2>
                    <div class="space-y-4">
                        @foreach($artist->events as $event)
                            @php
                                $eventTitle = is_array($event->title) ? ($event->title[app()->getLocale()] ?? $event->title['en'] ?? 'Event') : $event->title;
                                $eventSlug = is_array($event->slug) ? ($event->slug[app()->getLocale()] ?? $event->slug['en'] ?? $event->id) : ($event->slug ?? $event->id);
                            @endphp
                            <div class="flex gap-4 p-4 border rounded-xl hover:shadow-md transition">
                                @if($event->poster_url)
                                    <img src="{{ $event->poster_url }}" alt="{{ $eventTitle }}" class="w-24 h-24 rounded-lg object-cover">
                                @endif
                                <div class="flex-1">
                                    <h3 class="font-semibold text-lg">{{ $eventTitle }}</h3>
                                    <p class="text-sm text-gray-600">
                                        {{ \Carbon\Carbon::parse($event->starts_at)->format('M d, Y • H:i') }}
                                    </p>
                                    @if($event->venue)
                                        <p class="text-sm text-gray-500">{{ $event->venue->name }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Genres --}}
            @if ($artist->artistGenres?->isNotEmpty())
                <section class="bg-white rounded-2xl border p-6">
                    <h3 class="text-lg font-bold mb-3">Genres</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($artist->artistGenres as $genre)
                            <span class="px-3 py-1 bg-gray-100 rounded-full text-sm">{{ $genre->getTranslation('name', app()->getLocale()) }}</span>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Social Links --}}
            @if($artist->website || $artist->facebook_url || $artist->instagram_url || $artist->tiktok_url || $artist->youtube_url || $artist->spotify_url)
                <section class="bg-white rounded-2xl border p-6">
                    <h3 class="text-lg font-bold mb-3">Links</h3>
                    <div class="space-y-2">
                        @if ($artist->website)
                            <a href="{{ $artist->website }}" target="_blank" rel="noopener" class="flex items-center gap-2 text-blue-600 hover:underline">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                                </svg>
                                Website
                            </a>
                        @endif
                        @if ($artist->facebook_url)
                            <a href="{{ $artist->facebook_url }}" target="_blank" rel="noopener" class="flex items-center gap-2 text-blue-600 hover:underline">
                                <span class="w-5 h-5">📘</span> Facebook
                            </a>
                        @endif
                        @if ($artist->instagram_url)
                            <a href="{{ $artist->instagram_url }}" target="_blank" rel="noopener" class="flex items-center gap-2 text-blue-600 hover:underline">
                                <span class="w-5 h-5">📷</span> Instagram
                            </a>
                        @endif
                        @if ($artist->tiktok_url)
                            <a href="{{ $artist->tiktok_url }}" target="_blank" rel="noopener" class="flex items-center gap-2 text-blue-600 hover:underline">
                                <span class="w-5 h-5">🎵</span> TikTok
                            </a>
                        @endif
                        @if ($artist->youtube_url)
                            <a href="{{ $artist->youtube_url }}" target="_blank" rel="noopener" class="flex items-center gap-2 text-blue-600 hover:underline">
                                <span class="w-5 h-5">▶️</span> YouTube
                            </a>
                        @endif
                        @if ($artist->spotify_url)
                            <a href="{{ $artist->spotify_url }}" target="_blank" rel="noopener" class="flex items-center gap-2 text-blue-600 hover:underline">
                                <span class="w-5 h-5">🎧</span> Spotify
                            </a>
                        @endif
                    </div>
                </section>
            @endif
        </div>
    </div>
</div>
@endsection
