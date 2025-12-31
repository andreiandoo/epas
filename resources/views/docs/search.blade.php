@extends('layouts.public')

@section('title', 'Search Documentation')

@section('content')
<div class="min-h-screen bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Search Header -->
        <div class="bg-white rounded-lg shadow-md p-8 mb-8">
            <h1 class="text-2xl font-bold text-gray-900 mb-4">Search Documentation</h1>
            <form action="{{ route('docs.search') }}" method="GET" class="relative" x-data="docSearch()">
                <input
                    type="text"
                    name="q"
                    value="{{ $query }}"
                    x-model="query"
                    @input.debounce.300ms="search"
                    placeholder="Search..."
                    class="w-full px-6 py-4 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                >
                <button type="submit" class="absolute right-4 top-4 text-gray-400 hover:text-indigo-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </button>

                <!-- Autocomplete dropdown -->
                <div
                    x-show="showResults && results.length > 0"
                    x-cloak
                    @click.away="showResults = false"
                    class="absolute w-full mt-2 bg-white rounded-lg shadow-xl z-50 max-h-96 overflow-y-auto border border-gray-200"
                >
                    <template x-for="result in results" :key="result.id">
                        <a
                            :href="result.url"
                            class="block px-4 py-3 hover:bg-gray-50 border-b border-gray-100 last:border-0"
                        >
                            <h4 class="font-medium text-gray-900" x-text="result.title"></h4>
                            <p class="text-sm text-gray-500" x-text="result.excerpt"></p>
                        </a>
                    </template>
                </div>
            </form>
        </div>

        <!-- Results -->
        @if($query)
        <div class="mb-4">
            <p class="text-gray-600">
                @if($results->count())
                    Found {{ $results->total() }} results for "<strong>{{ $query }}</strong>"
                @else
                    No results found for "<strong>{{ $query }}</strong>"
                @endif
            </p>
        </div>
        @endif

        @if($results->count())
        <div class="space-y-4">
            @foreach($results as $doc)
            <a href="{{ route('docs.show', $doc->slug) }}" class="block bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow p-6">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-700">
                                {{ $doc->category->name }}
                            </span>
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
                        @if($doc->excerpt)
                        <p class="text-gray-600">{{ Str::limit($doc->excerpt, 200) }}</p>
                        @endif
                    </div>
                </div>
            </a>
            @endforeach
        </div>

        <div class="mt-8">
            {{ $results->appends(['q' => $query])->links() }}
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
function docSearch() {
    return {
        query: '{{ $query }}',
        results: [],
        showResults: false,

        async search() {
            if (this.query.length < 2) {
                this.results = [];
                this.showResults = false;
                return;
            }

            try {
                const response = await fetch(`/api/docs/autocomplete?q=${encodeURIComponent(this.query)}`);
                const data = await response.json();
                this.results = data.data;
                this.showResults = true;
            } catch (error) {
                console.error('Search error:', error);
                this.results = [];
            }
        }
    }
}
</script>
@endpush
@endsection
