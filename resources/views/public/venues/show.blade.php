@extends('public.layout')

@section('title', $venue->getTranslation('name', app()->getLocale()) ?? $venue->getTranslation('name', 'en') . ' - Tixello')

@section('content')
{{-- Hero Section --}}
<div class="relative bg-gradient-to-br from-indigo-900 to-purple-800 text-white">
    @if ($venue->image_url ?? false)
        <div class="absolute inset-0 opacity-30">
            <img src="{{ \Illuminate\Support\Str::startsWith($venue->image_url, ['http://', 'https://']) ? $venue->image_url : \Illuminate\Support\Facades\Storage::url($venue->image_url) }}" alt="{{ $venue->getTranslation('name', app()->getLocale()) ?? $venue->getTranslation('name', 'en') }}" class="w-full h-full object-cover">
        </div>
    @endif
    <div class="relative max-w-7xl mx-auto px-4 py-16">
        <h1 class="text-5xl font-bold mb-4">{{ $venue->getTranslation('name', app()->getLocale()) ?? $venue->getTranslation('name', 'en') }}</h1>
        <div class="flex flex-wrap gap-4 text-lg">
            @if($venue->address || $venue->city || $venue->country)
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    {{ $venue->address }}{{ $venue->address && $venue->city ? ', ' : '' }}{{ $venue->city }}{{ $venue->city && $venue->country ? ', ' : '' }}{{ $venue->country }}
                </div>
            @endif
            @if($venue->capacity_total)
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    Capacity: {{ number_format($venue->capacity_total) }}
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Main Content --}}
<div class="max-w-7xl mx-auto px-4 py-12">
    <div class="grid lg:grid-cols-3 gap-8">
        {{-- Main Column --}}
        <div class="lg:col-span-2 space-y-8">
            {{-- Description --}}
            @if ($venue->description)
                <section class="bg-white rounded-2xl border p-6">
                    <h2 class="text-2xl font-bold mb-4">About</h2>
                    <div class="prose max-w-none">
                        {!! $venue->getTranslation('description', app()->getLocale()) ?? $venue->getTranslation('description', 'en') !!}
                    </div>
                </section>
            @endif

            {{-- Map --}}
            @if($venue->lat && $venue->lng)
                <section class="bg-white rounded-2xl border p-6">
                    <h2 class="text-2xl font-bold mb-4">Location</h2>
                    <div class="rounded-xl overflow-hidden">
                        <iframe
                            width="100%"
                            height="400"
                            style="border:0"
                            loading="lazy"
                            allowfullscreen
                            referrerpolicy="no-referrer-when-downgrade"
                            src="https://www.google.com/maps?q={{ $venue->lat }},{{ $venue->lng }}&hl={{ app()->getLocale() }}&z=15&output=embed">
                        </iframe>
                    </div>
                </section>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            {{-- Details --}}
            <section class="bg-white rounded-2xl border p-6">
                <h3 class="text-lg font-bold mb-3">Details</h3>
                <dl class="space-y-3 text-sm">
                    @if($venue->capacity_standing || $venue->capacity_seated)
                        <div>
                            <dt class="font-semibold text-gray-700">Capacity</dt>
                            <dd class="text-gray-600">
                                @if($venue->capacity_standing) Standing: {{ number_format($venue->capacity_standing) }}<br>@endif
                                @if($venue->capacity_seated) Seated: {{ number_format($venue->capacity_seated) }}@endif
                            </dd>
                        </div>
                    @endif
                    @if($venue->established_at)
                        <div>
                            <dt class="font-semibold text-gray-700">Established</dt>
                            <dd class="text-gray-600">{{ $venue->established_at->format('Y') }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            {{-- Contact & Social --}}
            <section class="bg-white rounded-2xl border p-6">
                <h3 class="text-lg font-bold mb-3">Contact</h3>
                <div class="space-y-2 text-sm">
                    @if($venue->phone)
                        <a href="tel:{{ $venue->phone }}" class="flex items-center gap-2 text-blue-600 hover:underline">
                            <span>📞</span> {{ $venue->phone }}
                        </a>
                    @endif
                    @if($venue->email)
                        <a href="mailto:{{ $venue->email }}" class="flex items-center gap-2 text-blue-600 hover:underline">
                            <span>✉️</span> {{ $venue->email }}
                        </a>
                    @endif
                    @if($venue->website_url)
                        <a href="{{ $venue->website_url }}" target="_blank" rel="noopener" class="flex items-center gap-2 text-blue-600 hover:underline">
                            <span>🌐</span> Website
                        </a>
                    @endif
                    @if($venue->facebook_url)
                        <a href="{{ $venue->facebook_url }}" target="_blank" rel="noopener" class="flex items-center gap-2 text-blue-600 hover:underline">
                            <span>📘</span> Facebook
                        </a>
                    @endif
                    @if($venue->instagram_url)
                        <a href="{{ $venue->instagram_url }}" target="_blank" rel="noopener" class="flex items-center gap-2 text-blue-600 hover:underline">
                            <span>📷</span> Instagram
                        </a>
                    @endif
                    @if($venue->tiktok_url)
                        <a href="{{ $venue->tiktok_url }}" target="_blank" rel="noopener" class="flex items-center gap-2 text-blue-600 hover:underline">
                            <span>🎵</span> TikTok
                        </a>
                    @endif
                </div>
            </section>
        </div>
    </div>
</div>
@endsection
