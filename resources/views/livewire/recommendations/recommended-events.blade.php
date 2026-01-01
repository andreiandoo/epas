<div class="recommended-events-widget">
    @if($loading)
        <div class="animate-pulse">
            <div class="h-6 w-48 bg-gray-200 rounded mb-4"></div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @for($i = 0; $i < $limit; $i++)
                    <div class="bg-gray-200 rounded-2xl h-64"></div>
                @endfor
            </div>
        </div>
    @elseif(empty($recommendations))
        {{-- Empty state - show nothing or generic events --}}
    @else
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                <svg class="w-6 h-6 text-primary-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                </svg>
                {{ $title }}
            </h2>
            <p class="text-gray-500 text-sm mt-1">Based on your interests and activity</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach($recommendations as $rec)
                @php
                    $event = $rec['event'] ?? null;
                    $reasons = $rec['reasons'] ?? [];
                    $score = $rec['score'] ?? 0;
                @endphp

                @if($event)
                    <div class="group relative bg-white dark:bg-gray-800 rounded-2xl shadow-sm overflow-hidden hover:shadow-lg transition-all duration-300 border border-gray-100 dark:border-gray-700">
                        {{-- Recommendation badge --}}
                        <div class="absolute top-3 left-3 z-10">
                            <span class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium bg-primary-500 text-white rounded-full shadow-lg">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                </svg>
                                For You
                            </span>
                        </div>

                        {{-- Event image --}}
                        <a href="{{ $event->url ?? '#' }}" class="block">
                            <div class="aspect-[4/3] overflow-hidden">
                                <img src="{{ $event->poster_url ?? $event->image_url ?? 'https://placehold.co/400x300?text=Event' }}"
                                     alt="{{ $event->name ?? $event->title ?? 'Event' }}"
                                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                            </div>
                        </a>

                        {{-- Event info --}}
                        <div class="p-4">
                            {{-- Date --}}
                            @if($event->start_date ?? $event->starts_at ?? null)
                                <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-2">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    {{ \Carbon\Carbon::parse($event->start_date ?? $event->starts_at)->format('M d, Y') }}
                                </div>
                            @endif

                            {{-- Title --}}
                            <a href="{{ $event->url ?? '#' }}" class="block">
                                <h3 class="font-semibold text-gray-900 dark:text-white group-hover:text-primary-600 transition-colors line-clamp-2">
                                    {{ $event->name ?? $event->title ?? 'Untitled Event' }}
                                </h3>
                            </a>

                            {{-- Venue --}}
                            @if($event->venue_name ?? optional($event->venue)->name ?? null)
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 truncate">
                                    {{ $event->venue_name ?? optional($event->venue)->name }}
                                </p>
                            @endif

                            {{-- Recommendation reason --}}
                            @if($showReasons && !empty($reasons))
                                <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                                    <p class="text-xs text-primary-600 dark:text-primary-400 flex items-center gap-1">
                                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                        </svg>
                                        {{ $reasons[0] ?? 'Recommended for you' }}
                                    </p>
                                </div>
                            @endif

                            {{-- Price --}}
                            @if($event->min_price ?? $event->price ?? null)
                                <div class="mt-3 flex items-center justify-between">
                                    <span class="text-lg font-bold text-gray-900 dark:text-white">
                                        {{ number_format($event->min_price ?? $event->price, 2) }} {{ $event->currency ?? 'RON' }}
                                    </span>
                                    <a href="{{ $event->url ?? '#' }}"
                                       class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors">
                                        View
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif
</div>
