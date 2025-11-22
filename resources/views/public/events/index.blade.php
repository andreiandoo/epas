@extends('public.layout')
@section('title', 'Events - Tixello')
@section('content')

{{-- Hero Section --}}
<div class="bg-gradient-to-br from-blue-900 to-cyan-900 text-white py-16">
    <div class="max-w-7xl mx-auto px-4">
        <h1 class="text-5xl font-bold mb-4">Discover Events</h1>
        <p class="text-xl text-blue-200">Find concerts, festivals, and performances near you</p>
    </div>
</div>

{{-- Main Content with Sidebar --}}
<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="flex flex-col lg:flex-row gap-8"
         x-data="eventsFilter()"
         x-init="init()">

        {{-- Sidebar Filters --}}
        <aside class="lg:w-80 flex-shrink-0">
            <div class="bg-white rounded-2xl border p-6 sticky top-4">
                <h3 class="text-xl font-bold mb-4">Filters</h3>

                {{-- Search --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Search</label>
                    <input
                        type="text"
                        x-model="searchQuery"
                        @input.debounce.500ms="applyFilters()"
                        placeholder="Search events..."
                        class="w-full px-4 py-2 border rounded-lg"
                    >
                </div>

                {{-- Event Type --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Event Type</label>
                    <select x-model="selectedType" @change="filterGenres(); applyFilters()" class="w-full px-4 py-2 border rounded-lg">
                        <option value="">All Types</option>
                        @foreach($eventTypes as $type)
                            @php
                                $typeSlug = is_array($type->slug) ? ($type->slug[app()->getLocale()] ?? $type->slug['en'] ?? '') : $type->slug;
                                $typeName = $type->getTranslation('name', app()->getLocale()) ?? $type->getTranslation('name', 'en');
                            @endphp
                            <option value="{{ $typeSlug }}">{{ $typeName }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Event Genre --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Event Genre</label>
                    <select x-model="selectedGenre" @change="applyFilters()" class="w-full px-4 py-2 border rounded-lg">
                        <option value="">All Genres</option>
                        <template x-for="genre in filteredGenres" :key="genre.slug">
                            <option :value="genre.slug" x-text="genre.name"></option>
                        </template>
                    </select>
                </div>

                {{-- Location --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Country</label>
                    <select x-model="selectedCountry" @change="loadStates(); applyFilters()" class="w-full px-4 py-2 border rounded-lg">
                        <option value="">All Countries</option>
                        <template x-for="country in countries" :key="country.code">
                            <option :value="country.name" x-text="country.name"></option>
                        </template>
                    </select>
                </div>

                <div class="mb-4" x-show="selectedCountry">
                    <label class="block text-sm font-medium mb-2">State/County</label>
                    <select x-model="selectedState" @change="loadCities(); applyFilters()" class="w-full px-4 py-2 border rounded-lg">
                        <option value="">All States</option>
                        <template x-for="state in states" :key="state.code">
                            <option :value="state.name" x-text="state.name"></option>
                        </template>
                    </select>
                </div>

                <div class="mb-4" x-show="selectedState">
                    <label class="block text-sm font-medium mb-2">City</label>
                    <select x-model="selectedCity" @change="applyFilters()" class="w-full px-4 py-2 border rounded-lg">
                        <option value="">All Cities</option>
                        <template x-for="city in cities" :key="city.code">
                            <option :value="city.name" x-text="city.name"></option>
                        </template>
                    </select>
                </div>

                {{-- Price Range --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Price Range</label>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="number" x-model="minPrice" @change="applyFilters()" placeholder="Min" class="px-4 py-2 border rounded-lg">
                        <input type="number" x-model="maxPrice" @change="applyFilters()" placeholder="Max" class="px-4 py-2 border rounded-lg">
                    </div>
                </div>

                {{-- Date Range --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Date From</label>
                    <input type="date" x-model="dateFrom" @change="applyFilters()" class="w-full px-4 py-2 border rounded-lg">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Date To</label>
                    <input type="date" x-model="dateTo" @change="applyFilters()" class="w-full px-4 py-2 border rounded-lg">
                </div>

                {{-- Reset Button --}}
                <button @click="resetFilters()" class="w-full text-center px-6 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg transition">
                    Reset Filters
                </button>
            </div>
        </aside>

        {{-- Events Grid --}}
        <main class="flex-1">
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse($events as $event)
                    <x-public.event-card :event="$event" />
                @empty
                    <div class="col-span-full text-center py-12 text-gray-500">
                        <div class="text-6xl mb-4">🎫</div>
                        <p class="text-xl">No events found</p>
                    </div>
                @endforelse
            </div>

            {{-- Pagination --}}
            <div class="mt-8">
                {{ $events->links() }}
            </div>
        </main>
    </div>
</div>

<script>
function eventsFilter() {
    return {
        searchQuery: '{{ request("q") }}',
        selectedType: '{{ request("type") }}',
        selectedGenre: '{{ request("event_genre") }}',
        selectedCountry: '{{ request("country") }}',
        selectedState: '{{ request("state") }}',
        selectedCity: '{{ request("city") }}',
        minPrice: '{{ request("min_price") }}',
        maxPrice: '{{ request("max_price") }}',
        dateFrom: '{{ request("from") }}',
        dateTo: '{{ request("to") }}',
        countries: [],
        states: [],
        cities: [],
        eventGenres: @json($eventGenres->map(fn($g) => [
            'slug' => is_array($g->slug) ? ($g->slug[app()->getLocale()] ?? $g->slug['en'] ?? '') : $g->slug,
            'name' => $g->getTranslation('name', app()->getLocale()) ?? $g->getTranslation('name', 'en')
        ])),
        filteredGenres: @json($eventGenres->map(fn($g) => [
            'slug' => is_array($g->slug) ? ($g->slug[app()->getLocale()] ?? $g->slug['en'] ?? '') : $g->slug,
            'name' => $g->getTranslation('name', app()->getLocale()) ?? $g->getTranslation('name', 'en')
        ])),

        async init() {
            await this.loadCountries();
            this.filterGenres();
        },

        async filterGenres() {
            if (!this.selectedType) {
                this.filteredGenres = this.eventGenres;
                return;
            }
            try {
                const response = await fetch('{{ url("/" . app()->getLocale() . "/api/event-genres") }}/' + this.selectedType);
                const data = await response.json();
                if (data.success) {
                    this.filteredGenres = data.data;
                } else {
                    this.filteredGenres = this.eventGenres;
                }
            } catch (e) {
                console.error('Failed to load event genres', e);
                this.filteredGenres = this.eventGenres;
            }
        },

        async loadCountries() {
            try {
                const response = await fetch('{{ route("public.api.countries", ["locale" => app()->getLocale()]) }}');
                const data = await response.json();
                this.countries = Object.entries(data.data).map(([key, value]) => ({ code: key, name: value }));
            } catch (e) {
                console.error('Failed to load countries', e);
            }
        },

        async loadStates() {
            if (!this.selectedCountry) {
                this.states = [];
                this.cities = [];
                this.selectedState = '';
                this.selectedCity = '';
                return;
            }
            try {
                const countryCode = this.selectedCountry.toLowerCase().substring(0, 2);
                const response = await fetch('{{ url("/" . app()->getLocale() . "/api/states") }}/' + countryCode);
                const data = await response.json();
                this.states = Object.entries(data.data).map(([key, value]) => ({ code: key, name: value }));
                this.selectedState = '';
                this.selectedCity = '';
                this.cities = [];
            } catch (e) {
                console.error('Failed to load states', e);
                this.states = [];
            }
        },

        async loadCities() {
            if (!this.selectedCountry || !this.selectedState) {
                this.cities = [];
                this.selectedCity = '';
                return;
            }
            try {
                const countryCode = this.selectedCountry.toLowerCase().substring(0, 2);
                const response = await fetch('{{ url("/" . app()->getLocale() . "/api/cities") }}/' + countryCode + '/' + encodeURIComponent(this.selectedState));
                const data = await response.json();
                this.cities = Object.entries(data.data).map(([key, value]) => ({ code: key, name: value }));
                this.selectedCity = '';
            } catch (e) {
                console.error('Failed to load cities', e);
                this.cities = [];
            }
        },

        applyFilters() {
            const params = new URLSearchParams();
            if (this.searchQuery) params.set('q', this.searchQuery);
            if (this.selectedType) params.set('type', this.selectedType);
            if (this.selectedGenre) params.set('event_genre', this.selectedGenre);
            if (this.selectedCountry) params.set('country', this.selectedCountry);
            if (this.selectedState) params.set('state', this.selectedState);
            if (this.selectedCity) params.set('city', this.selectedCity);
            if (this.minPrice) params.set('min_price', this.minPrice);
            if (this.maxPrice) params.set('max_price', this.maxPrice);
            if (this.dateFrom) params.set('from', this.dateFrom);
            if (this.dateTo) params.set('to', this.dateTo);

            const url = '{{ route("public.events.index", ["locale" => app()->getLocale()]) }}' + (params.toString() ? '?' + params.toString() : '');
            window.location.href = url;
        },

        resetFilters() {
            window.location.href = '{{ route("public.events.index", ["locale" => app()->getLocale()]) }}';
        }
    }
}
</script>

@endsection
