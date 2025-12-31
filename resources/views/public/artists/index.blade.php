@extends('public.layout')
@section('title', 'Artists - Tixello')
@section('content')

{{-- Hero Section --}}
<div class="bg-gradient-to-br from-purple-900 to-indigo-900 text-white py-16">
    <div class="max-w-7xl mx-auto px-4">
        <h1 class="text-5xl font-bold mb-4">Discover Artists</h1>
        <p class="text-xl text-purple-200">Find your favorite performers and discover new talent</p>
    </div>
</div>

{{-- Main Content with Alpine.js --}}
<div class="max-w-7xl mx-auto px-4 py-8"
     x-data="artistsFilter()"
     x-init="init()">

    {{-- Search Bar --}}
    <div class="mb-6">
        <input
            type="text"
            x-model="searchQuery"
            @input.debounce.500ms="applyFilters()"
            placeholder="Search artists by name, city, country..."
            class="w-full px-6 py-4 text-lg border-2 border-gray-300 rounded-2xl focus:border-purple-500 focus:outline-none"
        >
    </div>

    {{-- Filters Row --}}
    <div class="grid md:grid-cols-3 gap-4 mb-6">
        <select x-model="selectedCountry" @change="applyFilters()" class="px-4 py-3 border rounded-xl">
            <option value="">All Countries</option>
            <option value="Romania">Romania</option>
            <option value="United States">United States</option>
            <option value="United Kingdom">United Kingdom</option>
        </select>

        <select x-model="selectedType" @change="applyFilters()" class="px-4 py-3 border rounded-xl">
            <option value="">All Types</option>
            @foreach($artistTypes as $type)
                <option value="{{ $type->id }}">{{ $type->getTranslation('name', app()->getLocale()) ?? $type->getTranslation('name', 'en') }}</option>
            @endforeach
        </select>

        <select x-model="selectedGenre" @change="applyFilters()" class="px-4 py-3 border rounded-xl">
            <option value="">All Genres</option>
            @foreach($artistGenres as $genre)
                <option value="{{ $genre->id }}">{{ $genre->getTranslation('name', app()->getLocale()) ?? $genre->getTranslation('name', 'en') }}</option>
            @endforeach
        </select>
    </div>

    {{-- Reset Button --}}
    @if(request()->hasAny(['q', 'country', 'type', 'genre', 'letter']))
        <div class="mb-6">
            <button @click="resetFilters()" class="inline-block px-6 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg transition">
                Reset Filters
            </button>
        </div>
    @endif

    {{-- A-Z Filter --}}
    <div class="mb-8 flex flex-wrap gap-2">
        @foreach(range('A', 'Z') as $letter)
            <button
                @click="toggleLetter('{{ $letter }}')"
                :class="selectedLetter === '{{ $letter }}' ? 'bg-purple-600 text-white' : 'bg-gray-100 hover:bg-gray-200'"
                class="w-10 h-10 rounded-lg font-semibold transition"
            >
                {{ $letter }}
            </button>
        @endforeach
        <button
            @click="clearLetter()"
            class="px-4 h-10 rounded-lg font-semibold bg-gray-100 hover:bg-gray-200 transition"
        >
            All
        </button>
    </div>

    {{-- Artists Grid --}}
    <div class="grid sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        @forelse($artists as $artist)
            @php
                $slug = is_array($artist->slug) ? ($artist->slug[app()->getLocale()] ?? $artist->slug['en'] ?? $artist->id) : ($artist->slug ?? $artist->id);
            @endphp
            <a href="{{ route('public.artist.show', ['locale' => app()->getLocale(), 'slug' => $slug]) }}" class="group">
                <div class="bg-white rounded-2xl border overflow-hidden hover:shadow-lg transition">
                    @if ($artist->portrait_url ?? false)
                        <img src="{{ $artist->portrait_url }}" alt="{{ $artist->name }}" class="w-full h-64 object-cover group-hover:scale-105 transition duration-300">
                    @elseif ($artist->hero_image_url ?? false)
                        <img src="{{ $artist->hero_image_url }}" alt="{{ $artist->name }}" class="w-full h-64 object-cover group-hover:scale-105 transition duration-300">
                    @else
                        <div class="w-full h-64 bg-gradient-to-br from-purple-100 to-indigo-100 flex items-center justify-center">
                            <span class="text-4xl">🎤</span>
                        </div>
                    @endif
                    <div class="p-4">
                        <h3 class="font-bold text-lg mb-1">{{ $artist->name }}</h3>
                        <p class="text-sm text-gray-500">{{ $artist->city }}{{ $artist->city && $artist->country ? ', ' : '' }}{{ $artist->country }}</p>
                        @if ($artist->artistTypes?->isNotEmpty())
                            <div class="mt-2 flex flex-wrap gap-1">
                                @foreach($artist->artistTypes->take(2) as $type)
                                    <span class="text-xs px-2 py-1 bg-purple-100 text-purple-700 rounded-full">{{ $type->getTranslation('name', app()->getLocale()) ?? $type->getTranslation('name', 'en') }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </a>
        @empty
            <div class="col-span-full text-center py-12 text-gray-500">
                <div class="text-6xl mb-4">🎭</div>
                <p class="text-xl">No artists found</p>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    <div class="mt-8">
        {{ $artists->links() }}
    </div>
</div>

<script>
function artistsFilter() {
    return {
        searchQuery: '{{ request("q") }}',
        selectedCountry: '{{ request("country") }}',
        selectedType: '{{ request("type") }}',
        selectedGenre: '{{ request("genre") }}',
        selectedLetter: '{{ request("letter") }}',

        init() {
            // Initialize
        },

        toggleLetter(letter) {
            this.selectedLetter = this.selectedLetter === letter ? '' : letter;
            this.applyFilters();
        },

        clearLetter() {
            this.selectedLetter = '';
            this.applyFilters();
        },

        applyFilters() {
            const params = new URLSearchParams();
            if (this.searchQuery) params.set('q', this.searchQuery);
            if (this.selectedCountry) params.set('country', this.selectedCountry);
            if (this.selectedType) params.set('type', this.selectedType);
            if (this.selectedGenre) params.set('genre', this.selectedGenre);
            if (this.selectedLetter) params.set('letter', this.selectedLetter);

            const url = '{{ route("public.artists.index", ["locale" => app()->getLocale()]) }}' + (params.toString() ? '?' + params.toString() : '');
            window.location.href = url;
        },

        resetFilters() {
            window.location.href = '{{ route("public.artists.index", ["locale" => app()->getLocale()]) }}';
        }
    }
}
</script>

@endsection
