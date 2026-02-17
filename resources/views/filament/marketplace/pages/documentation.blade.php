<x-filament-panels::page>
    <div class="space-y-8">
        {{-- Featured Docs --}}
        @php $featured = $this->getFeaturedDocs(); @endphp
        @if($featured->isNotEmpty())
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Featured</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($featured as $doc)
                        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-5 hover:shadow-md transition">
                            <div class="flex items-start gap-3">
                                <div class="flex-shrink-0 w-8 h-8 rounded-lg bg-primary-50 dark:bg-primary-500/10 flex items-center justify-center">
                                    <x-heroicon-o-star class="w-4 h-4 text-primary-500" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $doc->title }}</h3>
                                    @if($doc->excerpt)
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 line-clamp-2">{{ $doc->excerpt }}</p>
                                    @endif
                                    <div class="mt-2 flex items-center gap-2">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                            {{ $doc->getTypeLabel() }}
                                        </span>
                                        <span class="text-xs text-gray-400">{{ $doc->read_time }} min read</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Categories with Docs --}}
        @php $categories = $this->getCategories(); @endphp
        @if($categories->isNotEmpty())
            @foreach($categories as $category)
                @if($category->docs->isNotEmpty())
                    <div>
                        <div class="flex items-center gap-2 mb-4">
                            @if($category->icon)
                                <span class="text-lg">{{ $category->icon }}</span>
                            @endif
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ $category->name }}</h2>
                            @if($category->description)
                                <span class="text-sm text-gray-500 dark:text-gray-400">- {{ $category->description }}</span>
                            @endif
                        </div>

                        <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                            <ul role="list" class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($category->docs as $doc)
                                    <li class="px-5 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                        <div class="flex items-center justify-between gap-4">
                                            <div class="flex-1 min-w-0">
                                                <h3 class="text-sm font-medium text-gray-900 dark:text-white">{{ $doc->title }}</h3>
                                                @if($doc->excerpt)
                                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 line-clamp-1">{{ $doc->excerpt }}</p>
                                                @endif
                                            </div>
                                            <div class="flex items-center gap-3 flex-shrink-0">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                                    {{ $doc->getTypeLabel() }}
                                                </span>
                                                <span class="text-xs text-gray-400">{{ $doc->read_time }} min</span>
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif
            @endforeach
        @endif

        {{-- Empty State --}}
        @if($categories->isEmpty() || $categories->every(fn ($c) => $c->docs->isEmpty()))
            <div class="text-center py-12">
                <x-heroicon-o-book-open class="mx-auto h-12 w-12 text-gray-400" />
                <h3 class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">No documentation yet</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Documentation will appear here when published.</p>
            </div>
        @endif
    </div>
</x-filament-panels::page>
