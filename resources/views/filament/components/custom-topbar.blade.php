@php
    $localeFlags = [
        'en' => '🇬🇧',
        'ro' => '🇷🇴',
        'de' => '🇩🇪',
        'fr' => '🇫🇷',
        'es' => '🇪🇸'
    ];
    $localeNames = [
        'en' => 'English',
        'ro' => 'Română',
        'de' => 'Deutsch',
        'fr' => 'Français',
        'es' => 'Español'
    ];
    $currentLocale = app()->getLocale();

    // Detect if in tenant or admin panel for search placeholder
    $isAdminPanel = request()->is('admin*');
    $searchPlaceholder = $isAdminPanel
        ? 'Search pages, events, venues, tenants...'
        : 'Search pages, events, orders, tickets...';
@endphp
<div class="sticky top-0 z-20 px-4 fi-custom-topbar">
    <div class="flex items-center justify-between max-w-full gap-4">
        {{-- Center: Global Search --}}
        <div class="flex-1 hidden md:block">
            <div class="epas-global-search">
                <input
                    type="text"
                    id="epas-global-search-input"
                    placeholder="{{ $searchPlaceholder }}"
                    class="dark:bg-gray-800 dark:text-white dark:placeholder-gray-400 dark:border-gray-600"
                    autocomplete="off"
                >
                <div class="search-icon">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <div id="epas-search-results" class="hidden epas-search-results"></div>
            </div>
        </div>

        {{-- Right: Language Selector, Public Site Link & User Menu --}}
        <div class="flex items-center gap-3">
            {{-- Language Selector with Flags --}}
            <div x-data="{ open: false }" class="relative">
                <button
                    @click="open = !open"
                    type="button"
                    class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 transition rounded-lg dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800"
                >
                    <span class="text-lg">{{ $localeFlags[$currentLocale] ?? '🌐' }}</span>
                    <span class="font-medium uppercase">{{ $currentLocale }}</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>

                <div
                    x-show="open"
                    @click.away="open = false"
                    x-cloak
                    class="absolute right-0 z-50 w-48 py-1 mt-2 bg-white border border-gray-200 rounded-lg shadow-lg dark:bg-gray-800 dark:border-gray-700"
                >
                    @foreach($localeNames as $locale => $name)
                        <a
                            href="{{ url()->current() }}?locale={{ $locale }}"
                            class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 {{ $currentLocale === $locale ? 'bg-gray-50 dark:bg-gray-700/50 font-semibold' : '' }}"
                        >
                            <span class="text-lg">{{ $localeFlags[$locale] }}</span>
                            <div class="flex-1">
                                <div class="font-medium">{{ $name }}</div>
                                <div class="text-xs text-gray-500 uppercase">{{ $locale }}</div>
                            </div>
                            @if($currentLocale === $locale)
                                <svg class="w-4 h-4 text-primary-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                </svg>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Public Site Link --}}
            <a
                href="{{ url('/' . $currentLocale) }}"
                target="_blank"
                class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 transition rounded-lg dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800"
                title="View Public Site"
            >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                </svg>
                <span class="hidden md:inline">Public Site</span>
            </a>

            {{-- User Account Menu --}}
            @if(filament()->auth()->check())
                <div x-data="{ open: false }" class="relative">
                    <button
                        @click="open = !open"
                        type="button"
                        class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 transition rounded-lg dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800"
                    >
                        <div class="flex items-center justify-center w-8 h-8 text-xs font-semibold text-white rounded-full bg-primary-500">
                            {{ strtoupper(substr(filament()->auth()->user()->name, 0, 2)) }}
                        </div>
                        <span class="hidden font-medium md:inline">{{ filament()->auth()->user()->name }}</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>

                    <div
                        x-show="open"
                        @click.away="open = false"
                        x-cloak
                        class="absolute right-0 z-50 w-48 py-1 mt-2 bg-white border border-gray-200 rounded-lg shadow-lg dark:bg-gray-800 dark:border-gray-700"
                    >
                        <div class="px-4 py-2 border-b border-gray-200 dark:border-gray-700">
                            <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ filament()->auth()->user()->name }}</div>
                            <div class="text-xs text-gray-500">{{ filament()->auth()->user()->email }}</div>
                        </div>

                        <form method="POST" action="{{ filament()->getLogoutUrl() }}">
                            @csrf
                            <button type="submit" class="w-full px-4 py-2 text-sm text-left text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                    </svg>
                                    Logout
                                </div>
                            </button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
