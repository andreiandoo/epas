@extends('layouts.public')

@section('title', 'Documentation')

@section('content')
<div class="min-h-screen bg-gray-50">
    <!-- Hero Section with Search -->
    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <h1 class="text-4xl font-bold text-center mb-4">Documentation</h1>
            <p class="text-xl text-center text-indigo-100 mb-8">
                Find guides, API references, and documentation for all components
            </p>

            <!-- Search Bar -->
            <div class="max-w-2xl mx-auto">
                <div class="relative" x-data="docSearch()">
                    <input
                        type="text"
                        x-model="query"
                        @input.debounce.300ms="search"
                        @keydown.escape="results = []; showResults = false"
                        @focus="if(results.length) showResults = true"
                        placeholder="Search documentation..."
                        class="w-full px-6 py-4 rounded-lg text-gray-900 placeholder-gray-500 bg-white shadow-lg focus:outline-none focus:ring-2 focus:ring-indigo-300"
                    >
                    <div class="absolute right-4 top-4">
                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>

                    <!-- Autocomplete Results -->
                    <div
                        x-show="showResults && results.length > 0"
                        x-cloak
                        @click.away="showResults = false"
                        class="absolute w-full mt-2 bg-white rounded-lg shadow-xl z-50 max-h-96 overflow-y-auto"
                    >
                        <template x-for="result in results" :key="result.id">
                            <a
                                :href="result.url"
                                class="block px-4 py-3 hover:bg-gray-50 border-b border-gray-100 last:border-0"
                            >
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium text-gray-900" x-text="result.title"></h4>
                                        <p class="text-sm text-gray-500" x-text="result.excerpt"></p>
                                    </div>
                                    <span
                                        class="px-2 py-1 text-xs rounded-full"
                                        :class="{
                                            'bg-blue-100 text-blue-700': result.type === 'api',
                                            'bg-green-100 text-green-700': result.type === 'component',
                                            'bg-yellow-100 text-yellow-700': result.type === 'module',
                                            'bg-purple-100 text-purple-700': result.type === 'guide',
                                            'bg-gray-100 text-gray-700': !['api', 'component', 'module', 'guide'].includes(result.type)
                                        }"
                                        x-text="result.type_label"
                                    ></span>
                                </div>
                            </a>
                        </template>

                        <a
                            :href="'/docs/search?q=' + encodeURIComponent(query)"
                            class="block px-4 py-3 text-center text-indigo-600 hover:bg-indigo-50 font-medium"
                        >
                            View all results
                        </a>
                    </div>

                    <!-- No Results -->
                    <div
                        x-show="showResults && query.length >= 2 && results.length === 0 && !loading"
                        x-cloak
                        class="absolute w-full mt-2 bg-white rounded-lg shadow-xl z-50 p-4 text-center text-gray-500"
                    >
                        No results found for "<span x-text="query"></span>"
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Featured Documentation -->
    @if($featured->count())
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Featured</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($featured as $doc)
            <a href="{{ route('docs.show', $doc->slug) }}" class="block bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow p-6">
                <div class="flex items-center mb-3">
                    <span class="px-2 py-1 text-xs font-medium rounded-full
                        @if($doc->type === 'api') bg-blue-100 text-blue-700
                        @elseif($doc->type === 'component') bg-green-100 text-green-700
                        @elseif($doc->type === 'module') bg-yellow-100 text-yellow-700
                        @else bg-gray-100 text-gray-700
                        @endif">
                        {{ $doc->getTypeLabel() }}
                    </span>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ $doc->title }}</h3>
                <p class="text-sm text-gray-600">{{ Str::limit($doc->excerpt, 100) }}</p>
                <div class="mt-4 text-xs text-gray-400">
                    {{ $doc->read_time }} min read
                </div>
            </a>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Categories -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Browse by Category</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($categories as $category)
            <a href="{{ route('docs.category', $category->slug) }}" class="block bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow p-6 border-l-4" style="border-color: {{ $category->color }}">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-lg font-semibold text-gray-900">{{ $category->name }}</h3>
                    <span class="text-sm text-gray-500">{{ $category->docs_count }} docs</span>
                </div>
                @if($category->description)
                <p class="text-sm text-gray-600">{{ Str::limit($category->description, 100) }}</p>
                @endif
            </a>
            @endforeach
        </div>
    </div>

    <!-- Table of Contents -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">All Documentation</h2>
        <div class="bg-white rounded-lg shadow-md">
            @foreach($tableOfContents as $category)
            @if($category->docs->count())
            <div class="border-b border-gray-200 last:border-0">
                <div class="px-6 py-4 bg-gray-50">
                    <h3 class="font-semibold text-gray-900">{{ $category->name }}</h3>
                </div>
                <ul class="divide-y divide-gray-100">
                    @foreach($category->docs as $doc)
                    <li>
                        <a href="{{ route('docs.show', $doc->slug) }}" class="block px-6 py-3 hover:bg-gray-50">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-700">{{ $doc->title }}</span>
                                <span class="text-xs text-gray-500">{{ $doc->getTypeLabel() }}</span>
                            </div>
                        </a>
                    </li>
                    @endforeach
                </ul>
            </div>
            @endif
            @endforeach
        </div>
    </div>
</div>

@push('scripts')
<script>
function docSearch() {
    return {
        query: '',
        results: [],
        showResults: false,
        loading: false,

        async search() {
            if (this.query.length < 2) {
                this.results = [];
                this.showResults = false;
                return;
            }

            this.loading = true;
            try {
                const response = await fetch(`/api/docs/autocomplete?q=${encodeURIComponent(this.query)}`);
                const data = await response.json();
                this.results = data.data;
                this.showResults = true;
            } catch (error) {
                console.error('Search error:', error);
                this.results = [];
            } finally {
                this.loading = false;
            }
        }
    }
}
</script>
@endpush
@endsection
