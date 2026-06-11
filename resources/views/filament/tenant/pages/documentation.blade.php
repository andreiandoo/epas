<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Featured Documentation --}}
        @if($this->getFeaturedDocs()->count() > 0)
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                Featured Articles
            </h2>
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                @foreach($this->getFeaturedDocs() as $doc)
                <a href="{{ route('docs.show', $doc->slug) }}" target="_blank"
                   class="block p-4 rounded-lg border border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    <h3 class="font-medium text-gray-900 dark:text-white">
                        {{ $doc->title }}
                    </h3>
                    @if($doc->metadata['description'] ?? false)
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        {{ Str::limit($doc->metadata['description'], 100) }}
                    </p>
                    @endif
                </a>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Documentation by Category --}}
        @foreach($this->getCategories() as $category)
        @if($category->docs->count() > 0)
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                @if($category->icon)
                <x-dynamic-component :component="$category->icon" class="w-5 h-5" />
                @endif
                {{ $category->name }}
            </h2>
            @if($category->description)
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                {{ $category->description }}
            </p>
            @endif
            <div class="space-y-2">
                @foreach($category->docs as $doc)
                <a href="{{ route('docs.show', $doc->slug) }}" target="_blank"
                   class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    <div class="flex-shrink-0">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ $doc->title }}
                        </p>
                        @if($doc->metadata['description'] ?? false)
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                            {{ $doc->metadata['description'] }}
                        </p>
                        @endif
                    </div>
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                    </svg>
                </a>
                @endforeach
            </div>
        </div>
        @endif
        @endforeach

        {{-- Empty State --}}
        @if($this->getCategories()->filter(fn($c) => $c->docs->count() > 0)->count() === 0 && $this->getFeaturedDocs()->count() === 0)
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-12 text-center">
            <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
            </svg>
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                No documentation available
            </h3>
            <p class="text-gray-500 dark:text-gray-400">
                Documentation articles will appear here once they are published.
            </p>
        </div>
        @endif
    </div>
</x-filament-panels::page>
