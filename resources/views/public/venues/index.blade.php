@extends('public.layout')
@section('title', 'Venues - Tixello')
@section('content')

{{-- Hero Section --}}
<div class="bg-gradient-to-br from-indigo-900 to-purple-900 text-white py-16">
    <div class="max-w-7xl mx-auto px-4">
        <h1 class="text-5xl font-bold mb-4">Discover Venues</h1>
        <p class="text-xl text-indigo-200">Find the perfect location for live events</p>
    </div>
</div>

{{-- Main Content with Sidebar --}}
<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="flex flex-col lg:flex-row gap-8"
         x-data="venuesFilter()"
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
                        placeholder="Search venues..."
                        class="w-full px-4 py-2 border rounded-lg"
                    >
                </div>

                {{-- Location Filters --}}
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

                {{-- Reset Button --}}
                <button @click="resetFilters()" class="w-full text-center px-6 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg transition">
                    Reset Filters
                </button>
            </div>
        </aside>

        {{-- Venues Grid --}}
        <main class="flex-1">
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse($venues as $venue)
                    <x-public.venue-card :venue="$venue" />
                @empty
                    <div class="col-span-full text-center py-12 text-gray-500">
                        <div class="text-6xl mb-4">🏛️</div>
                        <p class="text-xl">No venues found</p>
                    </div>
                @endforelse
            </div>

            {{-- Pagination --}}
            <div class="mt-8">
                {{ $venues->links() }}
            </div>
        </main>
    </div>
</div>

<script>
function venuesFilter() {
    return {
        searchQuery: '{{ request("q") }}',
        selectedCountry: '{{ request("country") }}',
        selectedState: '{{ request("state") }}',
        selectedCity: '{{ request("city") }}',
        countries: [],
        states: [],
        cities: [],

        async init() {
            await this.loadCountries();
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
            if (this.selectedCountry) params.set('country', this.selectedCountry);
            if (this.selectedState) params.set('state', this.selectedState);
            if (this.selectedCity) params.set('city', this.selectedCity);

            const url = '{{ route("public.venues.index", ["locale" => app()->getLocale()]) }}' + (params.toString() ? '?' + params.toString() : '');
            window.location.href = url;
        },

        resetFilters() {
            window.location.href = '{{ route("public.venues.index", ["locale" => app()->getLocale()]) }}';
        }
    }
}
</script>

@endsection
